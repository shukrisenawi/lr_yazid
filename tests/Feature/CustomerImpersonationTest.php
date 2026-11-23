<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class CustomerImpersonationTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_login_as_customer_and_return_to_customer_list(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin]);
        $customer = User::factory()->create();

        $this->actingAs($admin)->withSession(['cart' => ['admin-cart']])
            ->post("/admin/customers/{$customer->id}/login")
            ->assertRedirect('/dashboard')
            ->assertSessionHas('impersonation.admin_id', $admin->id)
            ->assertSessionMissing('cart');
        $this->assertAuthenticatedAs($customer);
        $this->get('/dashboard')->assertInertia(fn (Assert $page) => $page
            ->where('auth.user.id', $customer->id)->where('auth.impersonating', true));
        $this->get('/admin/customers')->assertForbidden();
        $this->post("/admin/customers/{$admin->id}/login")->assertForbidden();

        $this->post('/impersonation/stop')
            ->assertRedirect('/admin/customers')->assertSessionMissing('impersonation');
        $this->assertAuthenticatedAs($admin);
        $this->get('/admin/customers')->assertInertia(fn (Assert $page) => $page
            ->where('auth.impersonating', false));
    }

    public function test_guest_and_customer_cannot_start_or_stop_impersonation(): void
    {
        $customer = User::factory()->create();
        $this->post("/admin/customers/{$customer->id}/login")->assertRedirect('/login');
        $this->post('/impersonation/stop')->assertRedirect('/login');
        $this->actingAs($customer)->post("/admin/customers/{$customer->id}/login")->assertForbidden();
        $this->post('/impersonation/stop')->assertForbidden();
        $this->assertAuthenticatedAs($customer);
    }

    public function test_admin_cannot_impersonate_another_admin_or_a_missing_customer(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin]);
        $other = User::factory()->create(['role' => UserRole::Admin]);
        $this->actingAs($admin)->post("/admin/customers/{$other->id}/login")->assertNotFound();
        $this->post('/admin/customers/999999/login')->assertNotFound();
        $this->assertAuthenticatedAs($admin);
    }

    public function test_unverified_customer_can_return_without_verifying_email(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin]);
        $customer = User::factory()->unverified()->create();
        $this->actingAs($admin)->post("/admin/customers/{$customer->id}/login")->assertRedirect('/dashboard');
        $this->get('/dashboard')->assertRedirect('/email/verify');
        $this->get('/email/verify')->assertInertia(fn (Assert $page) => $page->where('auth.impersonating', true));
        $this->post('/impersonation/stop')->assertRedirect('/admin/customers');
        $this->assertAuthenticatedAs($admin);
    }

    public function test_return_is_rejected_if_original_admin_has_lost_admin_access(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin]);
        $customer = User::factory()->create();
        $this->actingAs($admin)->post("/admin/customers/{$customer->id}/login");
        $admin->role = UserRole::Customer;
        $admin->save();

        $this->post('/impersonation/stop')->assertForbidden();
        $this->assertAuthenticatedAs($customer);
    }

    public function test_session_identity_must_match_current_customer(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin]);
        $customer = User::factory()->create();
        $this->actingAs($customer)->withSession(['impersonation' => [
            'admin_id' => $admin->id, 'customer_id' => $customer->id + 1,
        ]])->post('/impersonation/stop')->assertForbidden();
        $this->assertAuthenticatedAs($customer);
    }

    public function test_logout_discards_return_to_admin_access(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin]);
        $customer = User::factory()->create();
        $this->actingAs($admin)->post("/admin/customers/{$customer->id}/login");
        $this->post('/logout')->assertSessionMissing('impersonation');
        $this->post('/impersonation/stop')->assertRedirect('/login');
        $this->assertGuest();
    }
}
