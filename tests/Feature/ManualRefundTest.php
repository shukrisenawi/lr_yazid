<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\ManualRefund;
use App\Models\Order;
use App\Models\Payment;
use App\Models\User;
use App\Services\AdminReportService;
use App\Services\ManualRefundService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

class ManualRefundTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private Order $order;

    private Payment $payment;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(now()->setDate(2026, 11, 15)->setTime(16, 0));
        Http::preventStrayRequests();
        $user = User::factory()->create();
        $this->admin = User::factory()->create();
        $this->admin->role = UserRole::Admin;
        $this->admin->save();
        $this->actingAs($this->admin);
        $this->order = Order::create(['user_id' => $user->id, 'reference' => 'REFUND-QA-'.Str::random(10),
            'checkout_key' => (string) Str::uuid(), 'customer_name' => $user->name, 'customer_email' => $user->email,
            'customer_phone' => '+60123456789', 'subtotal_sen' => 10000, 'shipping_fee_sen' => 0, 'total_sen' => 10000,
            'payment_status' => 'paid', 'fulfillment_status' => null, 'expires_at' => now()->subDay()]);
        $this->payment = $this->order->payments()->create(['reference' => 'PAY-'.Str::random(20),
            'environment' => 'sandbox', 'status' => 'paid', 'amount_sen' => 10000, 'secret_key_snapshot' => 'not-real',
            'paid_at' => now()->subDay(), 'checked_at' => now(), 'transaction_reference' => 'TX-'.Str::random(10)]);
    }

    private function data(array $overrides = []): array
    {
        return [...['idempotency_key' => (string) Str::uuid(), 'payment_id' => $this->payment->id,
            'amount' => '25.10', 'external_reference' => 'bank-qa-'.Str::random(10), 'method' => 'bank_transfer',
            'refunded_at' => '2026-11-15T15:00', 'reason' => 'External refund already completed', 'confirmed' => true], ...$overrides];
    }

    private function url(): string
    {
        return "/admin/orders/{$this->order->id}/manual-refunds";
    }

    public function test_only_admin_can_record_and_service_also_enforces_role(): void
    {
        $this->actingAs($this->order->user)->post($this->url(), $this->data())->assertForbidden();
        try {
            app(ManualRefundService::class)->record($this->order, $this->order->user, $this->data());
            $this->fail('Non-admin service call must fail.');
        } catch (HttpException $error) {
            $this->assertSame(403, $error->getStatusCode());
        }
        $this->assertDatabaseCount('manual_refunds', 0);
    }

    public function test_record_is_exact_idempotent_audited_and_does_not_change_financial_or_operational_state(): void
    {
        $data = $this->data(['amount' => '0.10']);
        $before = $this->payment->fresh()->getRawOriginal();
        $this->from("/admin/orders/{$this->order->id}")->post($this->url(), $data)->assertSessionHasNoErrors();
        $this->post($this->url(), $data)->assertSessionHasNoErrors();
        $refund = ManualRefund::firstOrFail();
        $this->assertSame(10, $refund->amount_sen);
        $this->assertSame(strtoupper($data['external_reference']), $refund->external_reference);
        $this->assertSame($this->admin->id, $refund->recorded_by);
        $this->assertSame('sandbox', $refund->environment);
        $this->assertSame('2026-11-15 15:00', $refund->refunded_at->format('Y-m-d H:i'));
        $this->assertDatabaseCount('manual_refunds', 1);
        $this->assertSame($before, $this->payment->fresh()->getRawOriginal());
        $this->assertSame('paid', $this->order->fresh()->payment_status);
        $this->assertNull($this->order->fresh()->fulfillment_status);
        $this->assertDatabaseCount('membership_transactions', 0);
        $this->assertSame(0, DB::table('jobs')->count());
        Http::assertNothingSent();
    }

    public function test_key_cannot_be_reused_for_different_data_or_another_actor(): void
    {
        $data = $this->data();
        $this->post($this->url(), $data)->assertSessionHasNoErrors();
        foreach (['amount' => '25.11', 'reason' => 'Changed audit reason', 'external_reference' => 'DIFFERENT-REF'] as $key => $value) {
            $this->post($this->url(), [...$data, $key => $value])->assertSessionHasErrors('refund');
        }
        $otherAdmin = User::factory()->create();
        $otherAdmin->role = UserRole::Admin;
        $otherAdmin->save();
        $this->actingAs($otherAdmin)->post($this->url(), $data)->assertSessionHasErrors('refund');
        $this->assertDatabaseCount('manual_refunds', 1);
    }

    public function test_partial_records_are_capped_by_each_verified_payment(): void
    {
        $this->post($this->url(), $this->data(['amount' => '60']))->assertSessionHasNoErrors();
        $this->post($this->url(), $this->data(['amount' => '40.01']))->assertSessionHasErrors('amount');
        $this->post($this->url(), $this->data(['amount' => '40.00']))->assertSessionHasNoErrors();
        $this->post($this->url(), $this->data(['amount' => '0.01']))->assertSessionHasErrors('amount');
        $this->assertSame(10000, ManualRefund::sum('amount_sen'));
        $this->assertDatabaseCount('manual_refunds', 2);
    }

    public function test_external_reference_is_normalized_and_unique_per_environment(): void
    {
        $this->post($this->url(), $this->data(['external_reference' => 'bank-reference']))->assertSessionHasNoErrors();
        $this->post($this->url(), $this->data(['external_reference' => 'BANK-REFERENCE']))->assertSessionHasErrors('refund');
        $this->payment->update(['environment' => 'production']);
        $this->post($this->url(), $this->data(['external_reference' => 'BANK-REFERENCE']))->assertSessionHasNoErrors();
        $this->assertDatabaseCount('manual_refunds', 2);
    }

    public function test_invalid_amounts_dates_acknowledgements_and_references_are_rejected(): void
    {
        foreach (['0', '-1', '1e2', '1,000.00', '1.001', '01.00', '10000000', 'NaN', '10 0'] as $amount) {
            $this->post($this->url(), $this->data(['amount' => $amount]))->assertSessionHasErrors('amount');
        }
        foreach (['2026-11-15T16:01', '2026-11-13T12:00', '2026-02-30T12:00', '2026-11-15T15:00:00Z'] as $time) {
            $this->post($this->url(), $this->data(['refunded_at' => $time]))->assertSessionHasErrors('refunded_at');
        }
        $this->post($this->url(), $this->data(['confirmed' => false]))->assertSessionHasErrors('confirmed');
        $this->post($this->url(), $this->data(['external_reference' => '<script>bad</script>']))->assertSessionHasErrors('external_reference');
        $this->post($this->url(), $this->data(['reason' => '    x    ']))->assertSessionHasErrors('reason');
        $this->assertDatabaseCount('manual_refunds', 0);
    }

    public function test_pending_unchecked_undated_mismatched_or_foreign_payments_cannot_be_refunded(): void
    {
        $original = $this->payment->getAttributes();
        foreach (['status' => 'pending', 'checked_at' => null, 'paid_at' => null, 'transaction_reference' => '', 'amount_sen' => 9999] as $key => $value) {
            $this->payment->setRawAttributes($original);
            $this->payment->update([$key => $value]);
            $this->post($this->url(), $this->data())->assertSessionHasErrors('payment_id');
        }
        $this->payment->setRawAttributes($original);
        $this->payment->save();
        $this->post($this->url(), $this->data(['payment_id' => 999999]))->assertSessionHasErrors('payment_id');
        $foreignOrder = $this->order->replicate();
        $foreignOrder->reference = 'FOREIGN-REFUND-QA';
        $foreignOrder->checkout_key = (string) Str::uuid();
        $foreignOrder->user_id = User::factory()->create()->id;
        $foreignOrder->save();
        $foreignPayment = $this->payment->replicate();
        $foreignPayment->order_id = $foreignOrder->id;
        $foreignPayment->reference = 'FOREIGN-PAYMENT-QA';
        $foreignPayment->transaction_reference = 'FOREIGN-TX-QA';
        $foreignPayment->save();
        $this->post($this->url(), $this->data(['payment_id' => $foreignPayment->id]))->assertSessionHasErrors('payment_id');
        $this->order->update(['currency' => 'USD']);
        $this->post($this->url(), $this->data())->assertSessionHasErrors('payment_id');
        $this->assertDatabaseCount('manual_refunds', 0);
    }

    public function test_review_flags_are_retained_and_duplicate_paid_attempts_have_independent_balances(): void
    {
        $this->payment->update(['requires_review' => true, 'review_reason' => 'payment_after_reservation_ended']);
        $second = $this->payment->replicate();
        $second->reference = 'PAY-SECOND';
        $second->transaction_reference = 'TX-SECOND';
        $second->save();
        $this->post($this->url(), $this->data(['amount' => '100.00']))->assertSessionHasNoErrors();
        $this->post($this->url(), $this->data(['payment_id' => $second->id, 'amount' => '100.00']))->assertSessionHasNoErrors();
        $this->assertSame(20000, ManualRefund::sum('amount_sen'));
        $this->assertTrue($this->payment->fresh()->requires_review);
        $this->assertSame('payment_after_reservation_ended', $this->payment->fresh()->review_reason);
    }

    public function test_refund_creation_rolls_back_with_outer_transaction(): void
    {
        DB::beginTransaction();
        app(ManualRefundService::class)->record($this->order, $this->admin, $this->data());
        $this->assertDatabaseCount('manual_refunds', 1);
        DB::rollBack();
        $this->assertDatabaseCount('manual_refunds', 0);
    }

    public function test_customer_only_sees_owner_scoped_minimal_refund_details(): void
    {
        $data = $this->data(['reason' => 'Private admin audit detail', 'external_reference' => 'PRIVATE-BANK-REF']);
        $this->post($this->url(), $data)->assertSessionHasNoErrors();
        $this->get("/admin/orders/{$this->order->id}")->assertInertia(fn (Assert $page) => $page
            ->has('order.manual_refunds', 1)->missing('order.manual_refunds.0.idempotency_key'));
        $this->actingAs($this->order->user)->get("/orders/{$this->order->id}")
            ->assertDontSee('PRIVATE-BANK-REF')->assertDontSee('Private admin audit detail')
            ->assertInertia(fn (Assert $page) => $page->has('order.manual_refunds', 1)
                ->missing('order.manual_refunds.0.recorded_by')->missing('order.manual_refunds.0.external_reference')
                ->missing('order.manual_refunds.0.reason')->missing('order.manual_refunds.0.idempotency_key'));
        $this->actingAs(User::factory()->create())->get("/orders/{$this->order->id}")->assertForbidden();
    }

    public function test_reports_separate_manual_refunds_by_refund_date_and_environment_without_netting_gross(): void
    {
        $this->post($this->url(), $this->data())->assertSessionHasNoErrors();
        $filters = ['from' => '2026-11-15', 'to' => '2026-11-15', 'environment' => 'sandbox'];
        $report = app(AdminReportService::class)->generate($filters);
        $this->assertSame(0, $report['summary']['collected_sen']);
        $this->assertSame(2510, $report['summary']['manual_refund_sen']);
        $this->assertSame(1, $report['summary']['manual_refunds']);
        $this->assertSame(2510, $report['monthly'][0]['manual_refund_sen']);
        $other = app(AdminReportService::class)->generate([...$filters, 'environment' => 'production']);
        $this->assertSame(0, $other['summary']['manual_refund_sen']);
        $paidDate = app(AdminReportService::class)->generate([...$filters, 'from' => '2026-11-14', 'to' => '2026-11-14']);
        $this->assertSame(10000, $paidDate['summary']['collected_sen']);
        $this->assertSame(0, $paidDate['summary']['manual_refund_sen']);
        $csv = $this->get('/admin/reports/export?'.http_build_query($filters))->assertOk()->streamedContent();
        $this->assertStringContainsString('Manually recorded refunds', $csv);
        $this->assertStringContainsString('25.10', $csv);
    }
}
