<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\Cart;
use App\Models\CommerceNoticeDelivery;
use App\Models\Order;
use App\Models\Payment;
use App\Models\Product;
use App\Models\User;
use App\Notifications\CommerceNotice;
use App\Services\CheckoutService;
use App\Services\PaymentVerificationService;
use Illuminate\Auth\Events\Verified;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Factory;
use Illuminate\Notifications\SendQueuedNotifications;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;
use LogicException;
use RuntimeException;
use Tests\TestCase;

class CommerceNotificationTest extends TestCase
{
    use RefreshDatabase;

    private const SECRET = 'notice-test-secret-not-real';

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        Http::preventStrayRequests();
        config(['mail.default' => 'array', 'mail.mailers.array' => ['transport' => 'array']]);
        $this->user = User::factory()->create();
        $this->actingAs($this->user);
    }

    private function cart(): array
    {
        $product = Product::create(['name' => 'Notice QA product', 'slug' => Str::uuid(), 'sku' => Str::uuid(), 'price_sen' => 1000, 'stock' => 10, 'status' => 'active']);
        $address = $this->user->addresses()->create(['full_name' => 'Notice QA', 'phone' => '+60123456789',
            'line1' => 'Private QA address', 'city' => 'Shah Alam', 'state' => 'Selangor', 'postcode' => '40100']);
        $cart = Cart::firstOrCreate(['user_id' => $this->user->id]);
        $cart->items()->create(['product_id' => $product->id, 'item_key' => "$product->id:0", 'quantity' => 1]);

        return [$cart, $address, $product];
    }

    private function order(): Order
    {
        [, $address] = $this->cart();

        return app(CheckoutService::class)->create($this->user, (string) Str::uuid(), $address->id, null);
    }

    private function payment(Order $order): Payment
    {
        return $order->payments()->create(['reference' => 'NOTICE-PAY-'.Str::random(20), 'environment' => 'sandbox',
            'bill_code' => 'notice'.Str::random(12), 'amount_sen' => $order->total_sen, 'status' => 'pending', 'secret_key_snapshot' => self::SECRET]);
    }

    private function provider(Payment $payment, string $status = '1', string $reference = 'NOTICE-TX'): void
    {
        Http::swap(new Factory);
        Http::preventStrayRequests();
        Http::fake(['*/getBillTransactions' => Http::response([[
            'billExternalReferenceNo' => $payment->reference, 'billpaymentInvoiceNo' => $reference,
            'billpaymentStatus' => $status, 'billpaymentAmount' => $this->amount($payment->amount_sen),
        ]])]);
    }

    private function amount(int $sen): string
    {
        return intdiv($sen, 100).'.'.sprintf('%02d', $sen % 100);
    }

    private function notice(string $kind): array
    {
        $delivery = CommerceNoticeDelivery::where('kind', $kind)->firstOrFail();

        return [$delivery, new CommerceNotice($delivery->id, $delivery->notification_id)];
    }

    public function test_welcome_is_queued_once_after_email_verification_not_for_an_unverified_account(): void
    {
        $unverified = User::factory()->unverified()->create();
        event(new Verified($unverified));
        $this->assertDatabaseCount('commerce_notice_deliveries', 0);
        event(new Verified($this->user));
        event(new Verified($this->user));
        $this->assertDatabaseCount('commerce_notice_deliveries', 1);
        $this->assertSame(2, DB::table('jobs')->where('queue', 'commerce-notices')->count());
        $this->assertSame(0, $this->user->notifications()->count());
        Http::assertNothingSent();
    }

    public function test_deleted_nonfinancial_accounts_do_not_leave_deliverable_welcome_jobs(): void
    {
        event(new Verified($this->user));
        [, $notice] = $this->notice('welcome');
        $job = new SendQueuedNotifications($this->user, $notice, ['mail']);
        $this->assertTrue($job->deleteWhenMissingModels);
        $this->user->delete();
        $this->assertDatabaseCount('users', 0);
        $this->assertDatabaseCount('commerce_notice_deliveries', 0);
    }

    public function test_checkout_retries_queue_one_immutable_confirmation_without_payment_or_address_secrets(): void
    {
        $order = $this->order();
        app(CheckoutService::class)->create($this->user, $order->checkout_key, null, null);
        [$delivery, $notice] = $this->notice('order_placed');
        $original = $this->amount($order->total_sen);
        $order->update(['total_sen' => 999999, 'customer_name' => 'Changed customer']);
        $this->assertStringContainsString($original, $notice->toArray($this->user)['message']);
        $this->assertStringContainsString('order creation only', $notice->toArray($this->user)['message']);
        $this->assertStringNotContainsString('Private QA address', json_encode($delivery->snapshot));
        $this->assertStringNotContainsString($order->checkout_key, json_encode($delivery->snapshot));
        $this->assertDatabaseCount('commerce_notice_deliveries', 1);
        $this->assertSame(2, DB::table('jobs')->count());
        $this->assertStringNotContainsString($this->user->email, DB::table('jobs')->first()->payload);
    }

    public function test_outbox_and_queue_jobs_roll_back_with_checkout_and_cross_database_queues_are_rejected(): void
    {
        config(['queue.connections.database.after_commit' => true]);
        [$cart, $address, $product] = $this->cart();
        try {
            DB::transaction(function () use ($address): void {
                app(CheckoutService::class)->create($this->user, (string) Str::uuid(), $address->id, null);
                $this->assertDatabaseCount('commerce_notice_deliveries', 1);
                $this->assertSame(2, DB::table('jobs')->count());
                throw new RuntimeException('Injected transaction rollback.');
            });
        } catch (RuntimeException $error) {
            $this->assertSame('Injected transaction rollback.', $error->getMessage());
        }
        $this->assertDatabaseCount('orders', 0);
        $this->assertDatabaseCount('commerce_notice_deliveries', 0);
        $this->assertSame(0, DB::table('jobs')->count());
        $this->assertSame(1, $cart->items()->count());
        $this->assertSame(10, $product->fresh()->stock);
        config(['queue.connections.database.connection' => 'another_database']);
        try {
            app(CheckoutService::class)->create($this->user, (string) Str::uuid(), $address->id, null);
            $this->fail('Cross-database commerce queue must be rejected.');
        } catch (LogicException $error) {
            $this->assertStringContainsString('business database transaction', $error->getMessage());
        }
        $this->assertDatabaseCount('orders', 0);
    }

    public function test_verified_success_callbacks_enqueue_once_while_browser_returns_and_invalid_callbacks_do_not(): void
    {
        $order = $this->order();
        $payment = $this->payment($order);
        $this->get('/payments/return?'.http_build_query(['order_id' => $payment->reference, 'billcode' => $payment->bill_code, 'status_id' => '1']))->assertOk();
        $this->assertSame(0, CommerceNoticeDelivery::where('kind', 'payment_success')->count());
        $payload = ['status' => '1', 'order_id' => $payment->reference, 'refno' => 'NOTICE-TX',
            'billcode' => $payment->bill_code, 'amount' => $this->amount($payment->amount_sen), 'hash' => str_repeat('0', 32)];
        $this->post('/payments/toyyibpay/callback', $payload)->assertForbidden();
        $this->provider($payment);
        $payload['hash'] = md5(self::SECRET.'1'.$payment->reference.'NOTICE-TX'.'ok');
        $this->post('/payments/toyyibpay/callback', $payload)->assertOk();
        $this->post('/payments/toyyibpay/callback', $payload)->assertOk();
        $this->assertSame(1, CommerceNoticeDelivery::where('kind', 'payment_success')->count());
        [, $notice] = $this->notice('payment_success');
        $this->assertStringContainsString('Sandbox test payment, not a live payment', $notice->toArray($this->user)['message']);
        $this->user->notifyNow($notice, ['database', 'mail']);
        $this->assertSame(1, $this->user->notifications()->count());
        $this->assertCount(1, Mail::getSymfonyTransport()->messages());
        $this->assertSame('paid', $order->fresh()->payment_status);
        $this->assertSame(4, DB::table('jobs')->count());
    }

    public function test_failed_attempt_notices_are_deduplicated_and_suppressed_after_later_success(): void
    {
        $payment = $this->payment($this->order());
        $this->provider($payment, '3', 'NOTICE-FAILED');
        app(PaymentVerificationService::class)->reconcile($payment);
        app(PaymentVerificationService::class)->reconcile($payment);
        $this->assertSame(1, CommerceNoticeDelivery::where('kind', 'payment_failed')->count());
        [$failed, $notice] = $this->notice('payment_failed');
        $this->provider($payment, '1', 'NOTICE-SUCCESS');
        app(PaymentVerificationService::class)->reconcile($payment);
        $this->user->notifyNow($notice, ['database', 'mail']);
        $this->assertSame('payment_state_changed', $failed->fresh()->mail_suppression_reason);
        $this->assertNotNull($failed->fresh()->database_suppressed_at);
        $this->assertNull($failed->fresh()->mail_sent_at);
        $this->assertSame(0, $this->user->notifications()->count());
        $this->assertSame(1, CommerceNoticeDelivery::where('kind', 'payment_success')->count());
    }

    public function test_failure_notices_are_suppressed_when_a_newer_attempt_has_started(): void
    {
        $order = $this->order();
        $payment = $this->payment($order);
        $this->provider($payment, '3');
        app(PaymentVerificationService::class)->reconcile($payment);
        [$delivery, $notice] = $this->notice('payment_failed');
        $this->payment($order);
        $this->user->notifyNow($notice, ['database', 'mail']);
        $this->assertSame('newer_payment_attempt', $delivery->fresh()->database_suppression_reason);
        $this->assertSame('newer_payment_attempt', $delivery->fresh()->mail_suppression_reason);
    }

    public function test_verified_failure_delivers_without_marking_the_order_paid_or_consuming_stock(): void
    {
        $order = $this->order();
        $payment = $this->payment($order);
        $this->provider($payment, '3');
        app(PaymentVerificationService::class)->reconcile($payment);
        [$delivery, $notice] = $this->notice('payment_failed');
        $this->user->notifyNow($notice, ['database', 'mail']);
        $this->assertNotNull($delivery->fresh()->database_sent_at);
        $this->assertNotNull($delivery->fresh()->mail_sent_at);
        $this->assertSame('failed', $order->fresh()->payment_status);
        $this->assertSame(10, Product::firstOrFail()->stock);
        $this->assertCount(1, Mail::getSymfonyTransport()->messages());
    }

    public function test_late_review_payments_never_generate_a_success_confirmation(): void
    {
        $order = $this->order();
        $payment = $this->payment($order);
        $order->update(['expires_at' => now()->subMinute()]);
        $this->provider($payment);
        $this->assertSame('requires_review', app(PaymentVerificationService::class)->reconcile($payment));
        $this->assertSame(0, CommerceNoticeDelivery::where('kind', 'payment_success')->count());
        $this->assertTrue($payment->fresh()->requires_review);
    }

    public function test_reconciliation_does_not_backfill_a_historical_failed_attempt(): void
    {
        $order = $this->order();
        $payment = $this->payment($order);
        $order->update(['payment_status' => 'failed']);
        $payment->update(['status' => 'failed']);
        $this->provider($payment, '3');
        app(PaymentVerificationService::class)->reconcile($payment);
        $this->assertSame(0, CommerceNoticeDelivery::where('kind', 'payment_failed')->count());
        $this->assertSame(2, DB::table('jobs')->count());
        $this->assertSame('failed', $order->fresh()->payment_status);
    }

    public function test_channel_tracking_and_stable_notification_ids_prevent_completed_retry_duplicates(): void
    {
        $this->order();
        [$delivery, $notice] = $this->notice('order_placed');
        $this->user->notifyNow($notice, ['database', 'mail']);
        $this->user->notifyNow($notice, ['database', 'mail']);
        $this->assertNotNull($delivery->fresh()->database_sent_at);
        $this->assertNotNull($delivery->fresh()->mail_sent_at);
        $this->assertSame(1, $this->user->notifications()->count());
        $this->assertSame($delivery->notification_id, $this->user->notifications()->firstOrFail()->id);
        $this->assertCount(1, Mail::getSymfonyTransport()->messages());
        $delivery->update(['database_sent_at' => null]);
        $this->user->notifyNow($notice, ['database']);
        $this->assertNotNull($delivery->fresh()->database_sent_at);
        $this->assertSame(1, $this->user->notifications()->count());
    }

    public function test_email_changes_suppress_old_recipient_mail_but_keep_owner_order_history(): void
    {
        $this->order();
        [$delivery, $notice] = $this->notice('order_placed');
        $staleUser = $this->user->fresh();
        $this->user->update(['email' => 'changed.notice@example.com']);
        $staleUser->notifyNow($notice, ['database', 'mail']);
        $this->assertSame('email_changed', $delivery->fresh()->mail_suppression_reason);
        $this->assertNotNull($delivery->fresh()->database_sent_at);
        $this->assertCount(0, Mail::getSymfonyTransport()->messages());
    }

    public function test_unverified_email_cannot_receive_mail_from_an_older_queued_notice(): void
    {
        $this->order();
        [$delivery, $notice] = $this->notice('order_placed');
        $this->user->forceFill(['email_verified_at' => null])->save();
        $this->user->notifyNow($notice, ['mail']);
        $this->assertSame('email_unverified', $delivery->fresh()->mail_suppression_reason);
        $this->assertNull($delivery->fresh()->mail_sent_at);
        $this->assertCount(0, Mail::getSymfonyTransport()->messages());
    }

    public function test_notice_cannot_be_delivered_to_another_user_and_success_is_suppressed_if_review_flagged(): void
    {
        $order = $this->order();
        $payment = $this->payment($order);
        $this->provider($payment);
        app(PaymentVerificationService::class)->reconcile($payment);
        [$delivery, $notice] = $this->notice('payment_success');
        $other = User::factory()->create();
        $other->notifyNow($notice, ['database', 'mail']);
        $this->assertSame(0, $other->notifications()->count());
        $this->assertNull($delivery->fresh()->database_sent_at);
        $payment->update(['requires_review' => true]);
        $this->user->notifyNow($notice, ['database', 'mail']);
        $this->assertSame('payment_state_changed', $delivery->fresh()->database_suppression_reason);
        $this->assertSame('payment_state_changed', $delivery->fresh()->mail_suppression_reason);
    }

    public function test_delivery_directory_is_admin_only_filtered_and_never_exposes_snapshots_or_credentials(): void
    {
        $order = $this->order();
        $payment = $this->payment($order);
        $this->provider($payment);
        app(PaymentVerificationService::class)->reconcile($payment);
        $this->get('/admin/commerce-notices')->assertForbidden();
        $admin = User::factory()->create();
        $admin->role = UserRole::Admin;
        $admin->save();
        $this->actingAs($admin)->get('/admin/commerce-notices?kind=payment_success&search='.$order->reference)
            ->assertDontSee(self::SECRET)->assertInertia(fn (Assert $page) => $page->component('admin/CommerceNotices')
            ->has('deliveries.data', 1)->where('deliveries.data.0.order.reference', $order->reference)
            ->missing('deliveries.data.0.snapshot')->missing('deliveries.data.0.notification_id')
            ->missing('deliveries.data.0.payment.secret_key_snapshot'));
        $this->get('/admin/commerce-notices?kind=untrusted')->assertSessionHasErrors('kind');
    }

    public function test_directory_channel_filters_distinguish_waiting_processed_and_suppressed(): void
    {
        $this->order();
        [$delivery] = $this->notice('order_placed');
        $this->user->role = UserRole::Admin;
        $this->user->save();
        $this->get('/admin/commerce-notices?delivery=waiting')->assertInertia(fn (Assert $page) => $page->has('deliveries.data', 1));
        $delivery->update(['database_sent_at' => now(), 'mail_suppressed_at' => now(), 'mail_suppression_reason' => 'email_changed']);
        $this->get('/admin/commerce-notices?delivery=waiting')->assertInertia(fn (Assert $page) => $page->has('deliveries.data', 0));
        $this->get('/admin/commerce-notices?delivery=suppressed')->assertInertia(fn (Assert $page) => $page->has('deliveries.data', 1));
        $delivery->update(['mail_suppressed_at' => null, 'mail_suppression_reason' => null, 'mail_sent_at' => now()]);
        $this->get('/admin/commerce-notices?delivery=processed')->assertInertia(fn (Assert $page) => $page->has('deliveries.data', 1));
    }
}
