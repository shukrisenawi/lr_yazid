<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Http\Middleware\HandleInertiaRequests;
use App\Models\Order;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Tests\TestCase;

class MenuBadgeTest extends TestCase
{
    use RefreshDatabase;

    public function test_order_badge_counts_only_paid_orders_awaiting_processing(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin]);
        $order = Order::create([
            'user_id' => $admin->id, 'reference' => 'BADGE-ORDER', 'checkout_key' => 'badge-checkout',
            'customer_name' => $admin->name, 'customer_email' => $admin->email,
            'customer_phone' => '0123456789', 'subtotal_sen' => 1000, 'shipping_fee_sen' => 0,
            'total_sen' => 1000, 'currency' => 'MYR', 'payment_status' => 'paid',
            'fulfillment_status' => 'new', 'expires_at' => now()->addHour(),
        ]);
        $request = Request::create('/admin/orders');
        $request->setUserResolver(fn () => $admin);
        $request->setLaravelSession(app('session.store'));
        $badges = app(HandleInertiaRequests::class)->share($request)['menuBadges'];

        $this->assertSame(1, $badges()['/admin/orders']);
        $this->assertSame(0, $badges()['/admin/payments']);
        $this->assertSame(0, $badges()['/admin/appointments']);
        $order->update(['fulfillment_status' => 'processing']);
        $this->assertSame(0, $badges()['/admin/orders']);
        $order->update(['fulfillment_status' => 'new', 'payment_status' => 'pending']);
        $this->assertSame(0, $badges()['/admin/orders']);
    }

    public function test_customers_and_guests_cannot_receive_administrator_counts(): void
    {
        foreach ([null, User::factory()->create()] as $user) {
            $request = Request::create('/');
            $request->setUserResolver(fn () => $user);
            $request->setLaravelSession(app('session.store'));
            $badges = app(HandleInertiaRequests::class)->share($request)['menuBadges'];
            $this->assertSame([], $badges());
        }
    }
}
