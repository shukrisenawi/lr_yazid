<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminCustomerAccessTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_personal_pages_redirect_to_management(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin]);

        foreach (['/dashboard', '/cart', '/membership', '/vip-membership', '/checkout', '/orders', '/addresses', '/appointments', '/my-programs'] as $path) {
            $this->actingAs($admin)->get($path)->assertRedirect(route('admin.dashboard'));
        }
    }

    public function test_admin_cannot_modify_cart_or_checkout(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin]);

        $this->actingAs($admin)->post('/cart/items', [])->assertForbidden();
        $this->actingAs($admin)->delete('/cart')->assertForbidden();
        $this->actingAs($admin)->post('/checkout', [])->assertForbidden();
    }

    public function test_customer_and_guest_can_still_view_cart(): void
    {
        $this->get('/cart')->assertOk();
        $this->actingAs(User::factory()->create())->get('/cart')->assertOk();
    }
}
