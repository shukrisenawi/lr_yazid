<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\Appointment;
use App\Models\AppointmentService;
use App\Models\MembershipPlan;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class AdminCustomerTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        $user = User::factory()->create();
        $user->role = UserRole::Admin;
        $user->save();
        $this->actingAs($user);

        return $user;
    }

    public function test_customer_and_guest_cannot_access_customer_management(): void
    {
        $customer = User::factory()->create();
        $this->get('/admin/customers')->assertRedirect('/login');
        $this->actingAs($customer)->get('/admin/customers')->assertForbidden();
        $this->get("/admin/customers/$customer->id")->assertForbidden();
    }

    public function test_admin_can_search_customers_without_exposing_credentials_or_admin_accounts(): void
    {
        $this->admin();
        $customer = User::factory()->create(['name' => 'Searchable Customer']);
        $this->get('/admin/customers?search=Searchable')->assertInertia(fn (Assert $page) => $page
            ->has('customers.data', 1)->where('customers.data.0.id', $customer->id)->missing('customers.data.0.password')->missing('customers.data.0.two_factor_secret'));
        $this->get('/admin/customers')->assertInertia(fn (Assert $page) => $page->has('customers.data', 1));
    }

    public function test_filters_use_actual_expiry_not_only_status_flag(): void
    {
        $this->freezeTime();
        $this->admin();
        $plan = MembershipPlan::create(['name' => 'VIP', 'price_sen' => 19900, 'duration_months' => 12]);
        $active = User::factory()->create();
        $expired = User::factory()->create();
        $normal = User::factory()->create();
        foreach ([[$active, now()->addDays(7)], [$expired, now()]] as [$user, $expiry]) {
            $user->memberships()->create(['membership_plan_id' => $plan->id, 'status' => 'active', 'starts_at' => now()->subYear(), 'expires_at' => $expiry]);
        }
        foreach (['active' => $active, 'expiring' => $active, 'expired' => $expired, 'normal' => $normal] as $filter => $user) {
            $this->get("/admin/customers?membership=$filter")->assertInertia(fn (Assert $page) => $page->has('customers.data', 1)->where('customers.data.0.id', $user->id));
        }
    }

    public function test_profile_sections_are_owner_scoped_and_reject_admin_profiles(): void
    {
        $admin = $this->admin();
        $customer = User::factory()->create();
        foreach (['orders', 'memberships', 'programs', 'appointments'] as $section) {
            $this->get("/admin/customers/$customer->id?section=$section")->assertInertia(fn (Assert $page) => $page
                ->where('customer.id', $customer->id)->missing('customer.password')->has('records.data', 0));
        }
        $this->get("/admin/customers/$admin->id")->assertNotFound();
        $this->get("/admin/customers/$customer->id?section=invalid")->assertSessionHasErrors('section');
    }

    public function test_profile_never_mixes_appointment_history_between_customers(): void
    {
        $admin = $this->admin();
        $first = User::factory()->create();
        $second = User::factory()->create();
        $service = AppointmentService::create(['name' => 'Consultation', 'location' => 'KL']);
        $slot = $service->slots()->create(['created_by' => $admin->id, 'starts_at' => now()->addDays(3), 'ends_at' => now()->addDays(3)->addHour(), 'capacity' => 2, 'location' => 'KL']);
        foreach ([$first, $second] as $user) {
            Appointment::create(['user_id' => $user->id, 'reference' => 'APT-CUSTOMER-'.$user->id,
                'appointment_service_id' => $service->id, 'appointment_slot_id' => $slot->id, 'purpose' => 'Private customer purpose', 'status' => 'pending']);
        }
        $this->get("/admin/customers/$first->id?section=appointments")->assertInertia(fn (Assert $page) => $page
            ->has('records.data', 1)->where('records.data.0.user_id', $first->id));
    }
}
