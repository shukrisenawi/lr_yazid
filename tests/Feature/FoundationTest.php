<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\Membership;
use App\Models\MembershipPlan;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FoundationTest extends TestCase
{
    use RefreshDatabase;

    public function test_customer_cannot_access_administrator_dashboard(): void
    {
        $this->actingAs(User::factory()->create())->get('/admin/dashboard')->assertForbidden();
    }

    public function test_administrator_can_access_dashboard(): void
    {
        $admin = User::factory()->create();
        $admin->role = UserRole::Admin;
        $admin->save();
        $this->actingAs($admin)->get('/admin/dashboard')->assertOk();
        $this->actingAs($admin)->get('/vip/dashboard')->assertForbidden();
    }

    public function test_guest_cannot_access_account(): void
    {
        $this->get('/dashboard')->assertRedirect('/login');
        $this->get('/admin/dashboard')->assertRedirect('/login');
    }

    public function test_registration_normalizes_phone_and_cannot_set_role(): void
    {
        $this->post('/register', [
            'name' => 'Customer',
            'email' => 'CUSTOMER@example.com',
            'phone' => '012-345 6789',
            'password' => 'password',
            'password_confirmation' => 'password',
            'role' => 'admin',
        ])->assertSessionHasNoErrors();

        $this->assertDatabaseHas('users', [
            'email' => 'customer@example.com',
            'phone' => '+60123456789',
            'role' => 'customer',
        ]);
    }

    public function test_invalid_phone_is_rejected(): void
    {
        $this->post('/register', [
            'name' => 'Customer', 'email' => 'customer@example.com', 'phone' => '+441234567890',
            'password' => 'password', 'password_confirmation' => 'password',
        ])->assertSessionHasErrors('phone');
        $this->assertGuest();
    }

    public function test_membership_checks_actual_exclusive_expiry_and_start(): void
    {
        $this->freezeTime();
        $user = User::factory()->create();
        $plan = MembershipPlan::create(['name' => 'Annual VIP', 'price_sen' => 10000]);
        $membership = Membership::create([
            'user_id' => $user->id, 'membership_plan_id' => $plan->id,
            'status' => 'active', 'starts_at' => now()->subDay(), 'expires_at' => now()->addYear(),
        ]);
        $this->actingAs($user)->get('/vip/dashboard')->assertOk();
        $membership->update(['expires_at' => now()]);
        $this->get('/vip/dashboard')->assertForbidden();
        $membership->update(['starts_at' => now()->addDay(), 'expires_at' => now()->addYear()]);
        $this->get('/vip/dashboard')->assertForbidden();
        $this->get('/dashboard')->assertOk();
    }

    public function test_normal_member_cannot_access_vip_dashboard(): void
    {
        $this->actingAs(User::factory()->create())->get('/vip/dashboard')->assertForbidden();
    }

    public function test_administrator_command_validates_password(): void
    {
        $this->artisan('app:create-admin')
            ->expectsQuestion('Full name', 'Administrator')
            ->expectsQuestion('Email address', 'admin@example.com')
            ->expectsQuestion('Password (at least 12 characters, mixed case, numbers and symbols)', 'short')
            ->assertFailed();
        $this->assertDatabaseMissing('users', ['email' => 'admin@example.com']);
    }
}
