<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\Appointment;
use App\Models\AppointmentService;
use App\Models\MembershipPlan;
use App\Models\MembershipTransaction;
use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use App\Services\AdminReportService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class AdminReportTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private User $customer;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(now()->setDate(2026, 11, 15)->setTime(12, 0));
        $this->admin = User::factory()->create();
        $this->admin->role = UserRole::Admin;
        $this->admin->save();
        $this->customer = User::factory()->create(['created_at' => now()->subMonth()]);
        $this->actingAs($this->admin);
    }

    private function order(int $amount = 1100, string $environment = 'production', array $orderChanges = [], array $paymentChanges = []): Order
    {
        $product = Product::create(['name' => 'QA product', 'slug' => Str::uuid(), 'sku' => Str::uuid(), 'price_sen' => $amount - 100, 'stock' => 2, 'status' => 'active']);
        $order = Order::create([
            'user_id' => $this->customer->id, 'reference' => 'REPORT-'.Str::random(20), 'checkout_key' => Str::uuid(),
            'customer_name' => 'Private report customer', 'customer_email' => $this->customer->email,
            'customer_phone' => '+60123456789', 'subtotal_sen' => $amount - 100, 'shipping_fee_sen' => 100,
            'total_sen' => $amount, 'currency' => 'MYR', 'payment_status' => 'paid', 'expires_at' => now()->addHour(), ...$orderChanges,
        ]);
        $order->items()->create(['product_id' => $product->id, 'product_name' => $product->name, 'sku' => $product->sku, 'type' => 'physical',
            'quantity' => 1, 'unit_price_sen' => $amount - 100, 'line_total_sen' => $amount - 100]);
        $order->payments()->create(['reference' => 'R-PAY-'.Str::random(20), 'environment' => $environment,
            'amount_sen' => $amount, 'status' => 'paid', 'requires_review' => false,
            'transaction_reference' => 'TX-'.Str::random(20), 'paid_at' => now(), 'checked_at' => now(),
            'secret_key_snapshot' => 'private-report-secret', ...$paymentChanges]);

        return $order;
    }

    private function report(array $changes = []): array
    {
        return app(AdminReportService::class)->generate(['from' => '2026-11-01', 'to' => '2026-11-30', 'environment' => 'production', ...$changes]);
    }

    public function test_reports_dashboard_and_exports_require_a_verified_administrator(): void
    {
        foreach (['/admin/reports', '/admin/reports/export', '/admin/dashboard'] as $url) {
            $this->actingAs($this->customer)->get($url)->assertForbidden();
        }
        $this->admin->forceFill(['email_verified_at' => null])->save();
        $this->actingAs($this->admin)->get('/admin/reports')->assertRedirect('/email/verify');
        auth()->logout();
        $this->get('/admin/reports')->assertRedirect('/login');
    }

    public function test_empty_reports_default_to_production_and_include_zero_months_without_sample_sales(): void
    {
        $this->get('/admin/reports')->assertInertia(fn (Assert $page) => $page
            ->component('admin/reports/Index')->where('report.filters.environment', 'production')
            ->where('report.filters.from', '2026-06-01')->where('report.filters.to', '2026-11-15')
            ->where('report.summary.collected_sen', 0)->where('report.summary.paid_orders', 0)
            ->has('report.monthly', 6)->where('report.monthly.0.month', '2026-06')->where('report.monthly.5.month', '2026-11'));
        $this->get('/admin/dashboard')->assertInertia(fn (Assert $page) => $page->component('admin/Dashboard')->where('report.current.customers', 1));
    }

    public function test_date_order_range_limit_and_environment_inputs_are_validated(): void
    {
        foreach (['from=invalid', 'from=2026-12-01&to=2026-11-01', 'from=2025-01-01&to=2026-11-01', 'environment=all'] as $query) {
            $this->get('/admin/reports?'.$query)->assertSessionHasErrors();
            $this->get('/admin/reports/export?'.$query)->assertSessionHasErrors();
        }
        $this->get('/admin/reports?from=2026-01-01&to=2026-12-31')->assertSessionHasNoErrors()->assertOk();
    }

    public function test_financial_totals_exclude_pending_failed_refunded_review_mismatched_undated_and_unverified_records(): void
    {
        $valid = $this->order(1001);
        $this->order(2002, 'sandbox');
        $this->order(orderChanges: ['payment_status' => 'pending'], paymentChanges: ['status' => 'pending']);
        $this->order(orderChanges: ['payment_status' => 'failed'], paymentChanges: ['status' => 'failed']);
        $this->order(orderChanges: ['payment_status' => 'refunded']);
        $this->order(paymentChanges: ['requires_review' => true]);
        $this->order(paymentChanges: ['amount_sen' => 1099]);
        $this->order(paymentChanges: ['paid_at' => null]);
        $this->order(paymentChanges: ['checked_at' => null]);
        $this->order(paymentChanges: ['transaction_reference' => null]);
        $this->order(orderChanges: ['currency' => 'USD']);
        $report = $this->report();
        $this->assertSame(1001, $report['summary']['collected_sen']);
        $this->assertSame(1, $report['summary']['paid_orders']);
        $this->assertSame(6, $report['summary']['excluded_paid_attempts']);
        $this->assertSame(1, $report['current']['undated_paid_attempts']);
        $this->assertSame(2002, $this->report(['environment' => 'sandbox'])['summary']['collected_sen']);
        $this->assertSame('paid', $valid->fresh()->payment_status);
        $this->assertSame(11, Order::count());
    }

    public function test_duplicate_paid_attempts_and_review_flags_on_other_attempts_exclude_the_order(): void
    {
        $duplicate = $this->order();
        $duplicate->payments()->create(['reference' => 'DUPLICATE', 'environment' => 'production', 'amount_sen' => 1100,
            'status' => 'paid', 'transaction_reference' => 'DUP-TX', 'paid_at' => now(), 'checked_at' => now(), 'secret_key_snapshot' => 'test-only']);
        $flagged = $this->order();
        $flagged->payments()->create(['reference' => 'FLAGGED', 'environment' => 'sandbox', 'amount_sen' => 1100, 'status' => 'pending', 'requires_review' => true, 'secret_key_snapshot' => 'test-only']);
        $report = $this->report();
        $this->assertSame(0, $report['summary']['paid_orders']);
        $this->assertSame(0, $report['summary']['collected_sen']);
        $this->assertSame(3, $report['summary']['excluded_paid_attempts']);
    }

    public function test_sales_use_payment_dates_with_inclusive_days_while_order_trends_use_creation_dates(): void
    {
        $this->order(1001, orderChanges: ['created_at' => '2026-10-01 12:00:00'], paymentChanges: ['paid_at' => '2026-11-15 00:00:00']);
        $this->order(2002, paymentChanges: ['paid_at' => '2026-11-15 23:59:59']);
        $this->order(3003, paymentChanges: ['paid_at' => '2026-11-16 00:00:00']);
        $this->order(4004, paymentChanges: ['paid_at' => '2026-11-14 23:59:59']);
        $report = $this->report(['from' => '2026-11-15', 'to' => '2026-11-15']);
        $this->assertSame(3003, $report['summary']['collected_sen']);
        $this->assertSame(2, $report['summary']['paid_orders']);
        $this->assertSame(3, $report['summary']['orders_created']);
        $this->assertSame(3003, $report['monthly'][0]['collected_sen']);
    }

    public function test_membership_revenue_and_activation_renewal_counts_use_verified_purchases_not_manual_grants(): void
    {
        $plan = MembershipPlan::create(['name' => 'QA membership', 'price_sen' => 1000, 'duration_months' => 12]);
        $membership = $this->customer->memberships()->create(['membership_plan_id' => $plan->id, 'status' => 'active', 'starts_at' => now()->subDay(), 'expires_at' => now()->addYear()]);
        foreach (['activation', 'renewal'] as $type) {
            $order = $this->order();
            $item = $order->items()->firstOrFail();
            $item->update(['type' => 'membership', 'membership_plan_id' => $plan->id, 'membership_months' => 12]);
            MembershipTransaction::create(['user_id' => $this->customer->id, 'membership_id' => $membership->id, 'payment_id' => $order->payments()->firstOrFail()->id,
                'order_item_id' => $item->id, 'type' => $type, 'amount_sen' => 1000,
                'starts_at' => now(), 'expires_at' => now()->addYear(), 'paid_at' => now()]);
        }
        $report = $this->report();
        $this->assertSame(2200, $report['summary']['collected_sen']);
        $this->assertSame(2000, $report['summary']['membership_sen']);
        $this->assertSame(1, $report['summary']['vip_activations']);
        $this->assertSame(1, $report['summary']['vip_renewals']);
        $this->assertSame(1, $report['monthly'][0]['vip_renewals']);
    }

    public function test_current_membership_counts_are_distinct_and_use_exclusive_expiry_not_scheduler_status(): void
    {
        $plan = MembershipPlan::create(['name' => 'QA plan', 'price_sen' => 1000]);
        $otherPlan = MembershipPlan::create(['name' => 'Other plan', 'price_sen' => 1000]);
        foreach ([$plan, $otherPlan] as $memberPlan) {
            $this->customer->memberships()->create(['membership_plan_id' => $memberPlan->id, 'starts_at' => now()->subDay(), 'expires_at' => now()->addDays(30)]);
        }
        $expired = User::factory()->create();
        $expired->memberships()->create(['membership_plan_id' => $plan->id, 'starts_at' => now()->subYear(), 'expires_at' => now()]);
        $this->admin->memberships()->create(['membership_plan_id' => $plan->id, 'starts_at' => now()->subDay(), 'expires_at' => now()->addYear()]);
        $current = $this->report(['from' => '2026-01-01', 'to' => '2026-01-31'])['current'];
        $this->assertSame(2, $current['customers']);
        $this->assertSame(1, $current['active_vip']);
        $this->assertSame(1, $current['normal_customers']);
        $this->assertSame(1, $current['expired_vip']);
        $this->assertSame(1, $current['expiring_vip']);
    }

    public function test_appointment_reports_separate_request_cohorts_from_current_upcoming_schedule(): void
    {
        $service = AppointmentService::create(['name' => 'QA service', 'location' => 'QA location']);
        $slot = $service->slots()->create(['created_by' => $this->admin->id, 'starts_at' => now()->addHour(), 'ends_at' => now()->addHours(2), 'capacity' => 2, 'location' => 'QA location']);
        foreach (['pending', 'approved', 'cancelled'] as $status) {
            Appointment::create(['reference' => Str::uuid(), 'user_id' => $this->customer->id,
                'appointment_service_id' => $service->id, 'appointment_slot_id' => $slot->id, 'purpose' => 'Private appointment purpose',
                'status' => $status, 'scheduled_starts_at' => $slot->starts_at, 'scheduled_ends_at' => $slot->ends_at]);
        }
        $report = $this->report();
        $this->assertSame(3, $report['summary']['appointment_requests']);
        $this->assertSame(1, $report['appointments']['cancelled']);
        $this->assertSame(1, $report['current']['pending_appointments']);
        $this->assertSame(1, $report['current']['today_appointments']);
        $this->assertSame(1, $report['current']['upcoming_appointments']);
        $this->assertSame(0, $this->report(['from' => '2026-01-01', 'to' => '2026-01-31'])['summary']['appointment_requests']);
    }

    public function test_csv_matches_applied_filters_preserves_exact_money_and_exposes_no_personal_data_or_secrets(): void
    {
        $this->order(12345);
        $this->order(50000, 'sandbox');
        $response = $this->get('/admin/reports/export?from=2026-11-01&to=2026-11-30&environment=production')
            ->assertOk()->assertHeader('Content-Type', 'text/csv; charset=UTF-8');
        $csv = $response->streamedContent();
        $this->assertStringContainsString('123.45', $csv);
        $this->assertStringNotContainsString('500.00', $csv);
        $this->assertStringNotContainsString('private-report-secret', $csv);
        $this->assertStringNotContainsString('Private report customer', $csv);
        $this->assertStringNotContainsString($this->customer->email, $csv);
        $this->get('/admin/reports?from=2026-11-01&to=2026-11-30&environment=production')
            ->assertInertia(fn (Assert $page) => $page->where('report.summary.collected_sen', 12345)->missing('payments')->missing('customers'));
    }
}
