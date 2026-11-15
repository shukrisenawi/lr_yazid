<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\Cart;
use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use App\Services\CheckoutService;
use App\Services\PaymentVerificationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class OrderCancellationTest extends TestCase
{
    use RefreshDatabase;

    private function order(): Order
    {
        $user = User::factory()->create();
        $product = Product::create(['name' => 'Cancellation QA', 'slug' => Str::uuid(), 'sku' => Str::uuid(),
            'price_sen' => 1000, 'stock' => 10, 'status' => 'active']);
        $address = $user->addresses()->create(['full_name' => 'Cancellation QA', 'phone' => '+60123456789',
            'line1' => 'QA address', 'city' => 'Shah Alam', 'state' => 'Selangor', 'postcode' => '40100']);
        $cart = Cart::create(['user_id' => $user->id]);
        $cart->items()->create(['product_id' => $product->id, 'item_key' => "$product->id:0", 'quantity' => 2]);

        return app(CheckoutService::class)->create($user, (string) Str::uuid(), $address->id, null);
    }

    private function admin(): User
    {
        $admin = User::factory()->create();
        $admin->role = UserRole::Admin;
        $admin->save();
        $this->actingAs($admin);

        return $admin;
    }

    public function test_customer_cannot_use_administrator_cancellation_endpoint(): void
    {
        $order = $this->order();
        $this->actingAs($order->user)->post("/admin/orders/$order->id/cancel", ['reason' => 'Customer request'])->assertForbidden();
        $this->assertSame('pending', $order->fresh()->payment_status);
        $this->assertDatabaseCount('order_cancellations', 0);
    }

    public function test_admin_reason_is_required_and_unpaid_cancellation_preserves_history_and_stock(): void
    {
        $order = $this->order();
        $admin = $this->admin();
        $url = "/admin/orders/$order->id/cancel";
        $this->get("/admin/orders/$order->id")->assertInertia(fn (Assert $page) => $page->where('canCancel', true));
        foreach (['', 'abc', str_repeat('x', 1001)] as $reason) {
            $this->post($url, ['reason' => $reason])->assertSessionHasErrors('reason');
        }
        $this->from("/admin/orders/$order->id")->post($url, ['reason' => 'Cancelled at customer request'])->assertSessionHasNoErrors();
        $fresh = $order->fresh();
        $this->assertSame('cancelled', $fresh->payment_status);
        $this->assertSame('cancelled', $fresh->fulfillment_status);
        $this->assertSame(0, $fresh->reservations()->where('status', 'active')->count());
        $this->assertSame(1, $fresh->reservations()->where('status', 'released')->count());
        $this->assertSame(10, Product::firstOrFail()->stock);
        $this->assertSame(1, $fresh->items()->count());
        $this->assertSame($order->total_sen, $fresh->total_sen);
        $this->assertDatabaseHas('order_cancellations', ['order_id' => $order->id, 'actor_id' => $admin->id,
            'source' => 'admin', 'previous_payment_status' => 'pending', 'reason' => 'Cancelled at customer request']);
        $this->post($url, ['reason' => 'Overwrite attempt rejected'])->assertSessionHasErrors('order');
        $this->assertDatabaseCount('order_cancellations', 1);
        $this->assertSame('Cancelled at customer request', $order->fresh()->cancellation_reason);
        $this->get("/admin/orders/$order->id")->assertInertia(fn (Assert $page) => $page->where('canCancel', false)
            ->where('order.cancellation.actor_id', $admin->id));
    }

    public function test_paid_review_and_progressed_orders_cannot_be_cancelled(): void
    {
        $order = $this->order();
        $this->admin();
        $url = "/admin/orders/$order->id/cancel";
        foreach (['paid', 'refunded', 'cancelled'] as $status) {
            $order->update(['payment_status' => $status]);
            $this->post($url, ['reason' => 'Must not cancel this order'])->assertSessionHasErrors('order');
        }
        $order->update(['payment_status' => 'pending', 'fulfillment_status' => 'packed']);
        $this->post($url, ['reason' => 'Must not cancel this order'])->assertSessionHasErrors('order');
        $order->update(['fulfillment_status' => 'new']);
        $payment = $order->payments()->create(['reference' => 'QA-'.Str::uuid(), 'environment' => 'sandbox',
            'secret_key_snapshot' => 'not-real', 'status' => 'pending', 'requires_review' => true, 'amount_sen' => $order->total_sen]);
        $this->post($url, ['reason' => 'Must not cancel review order'])->assertSessionHasErrors('order');
        $payment->update(['requires_review' => false, 'status' => 'paid']);
        $this->post($url, ['reason' => 'Must not cancel paid attempt'])->assertSessionHasErrors('order');
        $this->assertDatabaseCount('order_cancellations', 0);
        $this->assertSame(1, $order->reservations()->where('status', 'active')->count());
    }

    public function test_customer_and_expiry_paths_keep_their_source_and_actor(): void
    {
        $customerOrder = $this->order();
        app(CheckoutService::class)->cancel($customerOrder);
        $this->assertDatabaseHas('order_cancellations', ['order_id' => $customerOrder->id, 'source' => 'customer', 'actor_id' => $customerOrder->user_id]);
        $expiredOrder = $this->order();
        $expiredOrder->update(['payment_status' => 'failed', 'expires_at' => now()->subMinute()]);
        $this->assertSame(1, app(CheckoutService::class)->expire());
        $this->assertDatabaseHas('order_cancellations', ['order_id' => $expiredOrder->id, 'source' => 'expiry', 'actor_id' => null,
            'reason' => 'reservation_expired', 'previous_payment_status' => 'failed']);
    }

    public function test_audit_and_reservation_release_roll_back_with_the_transaction(): void
    {
        $order = $this->order();
        $admin = $this->admin();
        DB::beginTransaction();
        app(CheckoutService::class)->cancel($order, 'Rollback cancellation QA', $admin);
        $this->assertDatabaseCount('order_cancellations', 1);
        DB::rollBack();
        $this->assertDatabaseCount('order_cancellations', 0);
        $this->assertSame('pending', $order->fresh()->payment_status);
        $this->assertSame(1, $order->reservations()->where('status', 'active')->count());
    }

    public function test_provider_payment_after_cancellation_requires_review_without_consuming_inventory(): void
    {
        $order = $this->order();
        $payment = $order->payments()->create(['reference' => 'QA-'.Str::uuid(), 'environment' => 'sandbox',
            'bill_code' => 'late-cancel-qa', 'secret_key_snapshot' => 'not-real', 'status' => 'pending', 'amount_sen' => $order->total_sen]);
        app(CheckoutService::class)->cancel($order, 'Cancelled before payment', $this->admin());
        Http::preventStrayRequests();
        Http::fake(['*/getBillTransactions' => Http::response([[
            'billExternalReferenceNo' => $payment->reference, 'billpaymentInvoiceNo' => 'QA-LATE-CANCEL',
            'billpaymentStatus' => '1', 'billpaymentAmount' => number_format($order->total_sen / 100, 2, '.', ''),
        ]])]);
        $this->assertSame('requires_review', app(PaymentVerificationService::class)->reconcile($payment));
        $this->assertTrue($payment->fresh()->requires_review);
        $this->assertSame('paid', $order->fresh()->payment_status);
        $this->assertSame('cancelled', $order->fresh()->fulfillment_status);
        $this->assertSame('payment_after_reservation_ended', $payment->fresh()->review_reason);
        $this->assertSame('Cancelled before payment', $order->fresh()->cancellation_reason);
        $this->assertSame(10, Product::firstOrFail()->stock);
        $this->assertDatabaseCount('order_cancellations', 1);
        $this->assertDatabaseMissing('commerce_notice_deliveries', ['kind' => 'payment_success']);
    }
}
