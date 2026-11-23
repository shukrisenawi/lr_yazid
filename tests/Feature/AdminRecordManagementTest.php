<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\Appointment;
use App\Models\AppointmentService;
use App\Models\AppointmentSlot;
use App\Models\ContentPage;
use App\Models\HomepageBanner;
use App\Models\Membership;
use App\Models\MembershipPlan;
use App\Models\Order;
use App\Models\Payment;
use App\Models\Product;
use App\Models\User;
use App\Models\VipProgram;
use App\Services\MembershipService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class AdminRecordManagementTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        Notification::fake();
        $this->admin = User::factory()->create();
        $this->admin->role = UserRole::Admin;
        $this->admin->save();
    }

    private function order(): Order
    {
        return Order::create([
            'user_id' => User::factory()->create()->id, 'reference' => Str::uuid(), 'checkout_key' => Str::uuid(),
            'customer_name' => 'Record QA', 'customer_email' => 'qa@example.com', 'customer_phone' => '+60123456789',
            'subtotal_sen' => 1000, 'shipping_fee_sen' => 0, 'total_sen' => 1000,
            'payment_status' => 'cancelled', 'fulfillment_status' => 'cancelled', 'expires_at' => now()->addHour(),
        ]);
    }

    private function payment(Order $order, string $status = 'paid'): Payment
    {
        return $order->payments()->create([
            'reference' => Str::uuid(), 'environment' => 'sandbox', 'secret_key_snapshot' => 'test-only',
            'amount_sen' => 1000, 'status' => $status, 'paid_at' => now(),
        ]);
    }

    public function test_only_verified_admins_can_delete_or_archive_and_modules_are_allowlisted(): void
    {
        $banner = HomepageBanner::create(['title' => 'QA banner']);
        $order = $this->order();
        $delete = "/admin/records/banners/$banner->id";
        $archive = "/admin/records/orders/$order->id/archive";
        $this->delete($delete)->assertRedirect('/login');
        $this->actingAs(User::factory()->create())->delete($delete)->assertForbidden();
        $this->patch($archive, ['reason' => 'QA archive', 'archived' => true])->assertForbidden();
        $this->admin->email_verified_at = null;
        $this->admin->save();
        $this->actingAs($this->admin)->delete($delete)->assertRedirect('/email/verify');
        $this->admin->email_verified_at = now();
        $this->admin->save();
        $this->actingAs($this->admin)->delete("/admin/records/orders/$order->id", ['reason' => 'Never delete transactions'])->assertNotFound();
        $this->delete("/admin/records/users/{$this->admin->id}", ['reason' => 'Invalid module'])->assertNotFound();
        $this->delete("/admin/records/customers/{$this->admin->id}", ['reason' => 'Never delete administrators'])->assertNotFound();
        $this->assertDatabaseCount('admin_record_actions', 0);
    }

    public function test_content_and_banner_deletion_preserves_revisions_and_removes_public_content(): void
    {
        $page = ContentPage::create(['title' => 'QA page', 'slug' => 'qa-page', 'content' => '<p>QA</p>', 'status' => 'published']);
        DB::table('content_page_revisions')->insert([
            'content_page_id' => $page->id, 'admin_id' => $this->admin->id, 'version' => 1,
            'snapshot' => '{}', 'created_at' => now(), 'updated_at' => now(),
        ]);
        $banner = HomepageBanner::create(['title' => 'QA banner', 'status' => 'published']);
        $this->actingAs($this->admin);
        foreach (['content-pages' => $page, 'banners' => $banner] as $module => $model) {
            $url = "/admin/records/$module/$model->id";
            $this->delete($url)->assertSessionHasNoErrors()->assertRedirect("/admin/$module");
            $this->assertSoftDeleted($model);
            $this->assertDatabaseHas('admin_record_actions', ['module' => $module, 'record_id' => $model->id, 'admin_id' => $this->admin->id, 'action' => 'deleted',
                'reason' => 'Confirmed deletion from the administrator list.']);
            $this->delete($url, ['reason' => 'Duplicate deletion'])->assertNotFound();
        }
        $this->assertDatabaseCount('content_page_revisions', 1);
        $this->get('/pages/qa-page')->assertNotFound();
    }

    public function test_customer_deletion_preserves_customers_with_transaction_history(): void
    {
        $order = $this->order();
        $empty = User::factory()->create();
        $this->actingAs($this->admin)->delete("/admin/records/customers/$order->user_id", ['reason' => 'Delete customer'])->assertSessionHasErrors('record');
        $this->assertDatabaseHas('orders', ['id' => $order->id]);
        $this->assertDatabaseHas('users', ['id' => $order->user_id]);
        $this->delete("/admin/records/customers/$empty->id", ['reason' => 'Remove unused customer'])->assertSessionHasNoErrors()->assertRedirect('/admin/customers');
        $this->assertDatabaseMissing('users', ['id' => $empty->id]);
    }

    public function test_program_with_registrations_must_be_cancelled_before_deletion(): void
    {
        $program = VipProgram::create([
            'title' => 'QA program', 'slug' => 'qa-program', 'starts_at' => now()->addDays(3), 'ends_at' => now()->addDays(3)->addHour(),
            'registration_opens_at' => now(), 'registration_closes_at' => now()->addDays(2), 'capacity' => 10, 'location' => 'QA',
        ]);
        $registration = $program->registrations()->create(['user_id' => User::factory()->create()->id, 'reference' => Str::uuid(), 'registered_at' => now()]);
        $url = "/admin/records/programs/$program->id";
        $this->actingAs($this->admin)->delete($url, ['reason' => 'Remove program'])->assertSessionHasErrors('record');
        $registration->update(['status' => 'cancelled']);
        $this->delete($url, ['reason' => 'Remove cancelled program'])->assertSessionHasNoErrors();
        $this->assertSoftDeleted($program);
        $this->assertSame($program->id, $registration->fresh()->program->id);
    }

    public function test_active_appointments_block_deletion_and_history_survives_service_deletion(): void
    {
        $service = AppointmentService::create(['name' => 'QA service', 'location' => 'QA']);
        $slot = AppointmentSlot::create(['appointment_service_id' => $service->id, 'created_by' => $this->admin->id,
            'starts_at' => now()->addDay(), 'ends_at' => now()->addDay()->addHour(), 'capacity' => 1, 'location' => 'QA']);
        $appointment = Appointment::create(['reference' => Str::uuid(), 'user_id' => User::factory()->create()->id,
            'appointment_service_id' => $service->id, 'appointment_slot_id' => $slot->id, 'purpose' => 'QA booking']);
        $this->actingAs($this->admin);
        foreach (['appointments' => $appointment, 'appointment-services' => $service] as $module => $model) {
            $this->delete("/admin/records/$module/$model->id", ['reason' => 'Remove active record'])->assertSessionHasErrors('record');
        }
        $appointment->update(['status' => 'cancelled']);
        $this->delete("/admin/records/appointments/$appointment->id", ['reason' => 'Remove cancelled booking'])->assertSessionHasNoErrors();
        $this->delete("/admin/records/appointment-services/$service->id", ['reason' => 'Remove unused service'])->assertSessionHasNoErrors();
        $this->assertSoftDeleted($appointment);
        $this->assertSoftDeleted($service);
        $this->assertFalse($slot->fresh()->service->is_active);
    }

    public function test_membership_delete_requires_expiry_and_plan_delete_disables_sales_but_keeps_history(): void
    {
        $product = Product::create(['name' => 'QA VIP', 'slug' => 'qa-vip', 'sku' => 'QA-VIP', 'price_sen' => 1000, 'type' => 'membership', 'status' => 'active']);
        $plan = MembershipPlan::create(['name' => 'QA plan', 'price_sen' => 1000, 'product_id' => $product->id]);
        $membership = Membership::create(['user_id' => User::factory()->create()->id, 'membership_plan_id' => $plan->id,
            'starts_at' => now()->subYear(), 'expires_at' => now()->addDay(), 'status' => 'active']);
        $this->actingAs($this->admin)->delete("/admin/records/memberships/$membership->id", ['reason' => 'Remove active membership'])->assertSessionHasErrors('record');
        $membership->update(['expires_at' => now()->subDay(), 'status' => 'expired']);
        $this->delete("/admin/records/memberships/$membership->id", ['reason' => 'Remove expired membership'])->assertSessionHasNoErrors();
        $this->delete("/admin/records/membership-plans/$plan->id", ['reason' => 'Retire old membership plan'])->assertSessionHasNoErrors();
        $this->assertSoftDeleted($membership);
        $this->assertSoftDeleted($plan);
        $this->assertSame('inactive', $product->fresh()->status);
        $this->assertSame($plan->id, $membership->fresh()->plan->id);
    }

    public function test_archiving_and_restoring_preserves_transactions_and_filters_lists(): void
    {
        $order = $this->order();
        $payment = $this->payment($order);
        $this->actingAs($this->admin);
        foreach (['orders' => $order, 'payments' => $payment] as $module => $model) {
            $url = "/admin/records/$module/$model->id/archive";
            $this->patch($url, ['archived' => true])->assertSessionHasErrors('reason');
            $this->patch($url, ['archived' => true, 'reason' => 'Archive completed transaction'])->assertSessionHasNoErrors();
            $this->assertNotNull($model->fresh()->archived_at);
            $this->get("/admin/$module")->assertInertia(fn (Assert $page) => $page->has("$module.data", 0));
            $this->get("/admin/$module?archive=archived")->assertInertia(fn (Assert $page) => $page->has("$module.data", 1));
            $this->get("/admin/$module?archive=all")->assertInertia(fn (Assert $page) => $page->has("$module.data", 1));
            $this->patch($url, ['archived' => false, 'reason' => 'Restore for follow up'])->assertSessionHasNoErrors();
            $this->assertNull($model->fresh()->archived_at);
        }
        $this->assertSame(1000, $order->fresh()->total_sen);
        $this->assertSame('paid', $payment->fresh()->status);
        $this->assertDatabaseCount('admin_record_actions', 4);
    }

    public function test_unresolved_transactions_cannot_be_hidden_in_archive(): void
    {
        $order = $this->order();
        $payment = $this->payment($order, 'pending');
        $this->actingAs($this->admin);
        $data = ['archived' => true, 'reason' => 'Archive unresolved transaction'];
        $this->patch("/admin/records/orders/$order->id/archive", $data)->assertSessionHasErrors('record');
        $this->patch("/admin/records/payments/$payment->id/archive", $data)->assertSessionHasErrors('record');
        $payment->update(['status' => 'failed', 'requires_review' => true]);
        $this->patch("/admin/records/payments/$payment->id/archive", $data)->assertSessionHasErrors('record');
        $payment->update(['requires_review' => false]);
        $order->update(['payment_status' => 'pending', 'fulfillment_status' => 'new']);
        $this->patch("/admin/records/orders/$order->id/archive", $data)->assertSessionHasErrors('record');
        $this->assertDatabaseCount('admin_record_actions', 0);
    }

    public function test_deleting_revoked_membership_does_not_bypass_payment_activation_protection(): void
    {
        $order = $this->order();
        $product = Product::create(['name' => 'QA VIP', 'slug' => 'qa-vip', 'sku' => 'QA-VIP', 'price_sen' => 1000]);
        $plan = MembershipPlan::create(['name' => 'QA plan', 'price_sen' => 1000]);
        $membership = Membership::create(['user_id' => $order->user_id, 'membership_plan_id' => $plan->id,
            'starts_at' => now()->subYear(), 'expires_at' => now()->subDay(), 'status' => 'revoked']);
        $order->items()->create(['product_id' => $product->id, 'product_name' => 'QA VIP', 'sku' => 'QA-VIP',
            'type' => 'membership', 'membership_plan_id' => $plan->id, 'membership_months' => 12, 'quantity' => 1, 'unit_price_sen' => 1000, 'line_total_sen' => 1000]);
        $this->actingAs($this->admin)->delete("/admin/records/memberships/$membership->id", ['reason' => 'Remove revoked membership'])->assertSessionHasNoErrors();
        $this->assertFalse(app(MembershipService::class)->canActivate($order));
        $membership->update(['status' => 'expired']);
        $payment = $this->payment($order);
        app(MembershipService::class)->activateVerified($order, $payment);
        $this->assertNull($membership->fresh()->deleted_at);
        $this->assertSame('active', $membership->fresh()->status);
        $this->assertSame(1, Membership::withTrashed()->where('user_id', $order->user_id)->count());
    }
}
