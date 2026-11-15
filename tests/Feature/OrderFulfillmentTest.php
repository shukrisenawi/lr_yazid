<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class OrderFulfillmentTest extends TestCase
{
    use RefreshDatabase;

    private function order(): Order
    {
        $user = User::factory()->create();
        $order = Order::create(['user_id' => $user->id, 'reference' => 'QA-'.Str::random(20), 'checkout_key' => (string) Str::uuid(),
            'customer_name' => $user->name, 'customer_email' => $user->email, 'customer_phone' => '+60123456789',
            'shipping_address' => ['full_name' => 'Snapshot Name', 'line1' => 'Snapshot address'], 'subtotal_sen' => 1000, 'shipping_fee_sen' => 100,
            'total_sen' => 1100, 'payment_status' => 'paid', 'fulfillment_status' => 'new', 'expires_at' => now()->addHour()]);
        $product = Product::create(['name' => 'QA product', 'slug' => (string) Str::uuid(), 'sku' => (string) Str::uuid(), 'price_sen' => 9999, 'stock' => 3, 'status' => 'active']);
        $order->items()->create(['product_id' => $product->id, 'product_name' => 'Purchased name', 'sku' => $product->sku,
            'type' => 'physical', 'quantity' => 1, 'unit_price_sen' => 1000, 'line_total_sen' => 1000]);
        $order->payments()->create(['reference' => 'QA-'.Str::random(20), 'environment' => 'sandbox', 'secret_key_snapshot' => 'test-only', 'amount_sen' => 1100, 'status' => 'paid']);

        return $order;
    }

    private function admin(): void
    {
        $admin = User::factory()->create();
        $admin->role = UserRole::Admin;
        $admin->save();
        $this->actingAs($admin);
    }

    public function test_only_admin_can_access_and_update_orders(): void
    {
        $order = $this->order();
        $this->actingAs($order->user)->get('/admin/orders')->assertForbidden();
        $this->get("/admin/orders/$order->id")->assertForbidden();
        $this->put("/admin/orders/$order->id", ['fulfillment_status' => 'processing', 'reason' => 'QA update'])->assertForbidden();
    }

    public function test_progression_tracking_audit_and_idempotency_without_changing_payment_or_prices(): void
    {
        $order = $this->order();
        $this->admin();
        $url = "/admin/orders/$order->id";
        $this->put($url, ['fulfillment_status' => 'shipped', 'reason' => 'Cannot skip stages'])->assertSessionHasErrors('fulfillment_status');
        foreach (['processing', 'packed'] as $status) {
            $this->put($url, ['fulfillment_status' => $status, 'reason' => 'Warehouse processing'])->assertSessionHasNoErrors();
        }
        $this->put($url, ['fulfillment_status' => 'shipped', 'reason' => 'Dispatch package'])->assertSessionHasErrors('tracking_number');
        $data = ['fulfillment_status' => 'shipped', 'courier_name' => 'QA Courier', 'tracking_number' => 'QA123', 'reason' => 'Dispatch package'];
        $this->put($url, $data)->assertSessionHasNoErrors();
        $this->put($url, $data)->assertSessionHasNoErrors();
        $this->assertSame(3, $order->fulfillmentHistory()->count());
        $this->put($url, [...$data, 'fulfillment_status' => 'delivered'])->assertSessionHasNoErrors();
        $this->put($url, [...$data, 'fulfillment_status' => 'packed'])->assertSessionHasErrors('fulfillment_status');
        $this->assertSame('paid', $order->fresh()->payment_status);
        $this->assertSame(1100, $order->fresh()->total_sen);
        $this->assertSame(1000, $order->items()->firstOrFail()->unit_price_sen);
    }

    public function test_unpaid_review_flagged_and_membership_only_orders_cannot_be_fulfilled(): void
    {
        $order = $this->order();
        $this->admin();
        $data = ['fulfillment_status' => 'processing', 'reason' => 'Warehouse processing'];
        $order->update(['payment_status' => 'pending']);
        $this->put("/admin/orders/$order->id", $data)->assertSessionHasErrors('fulfillment');
        $order->update(['payment_status' => 'paid']);
        $order->payments()->update(['requires_review' => true]);
        $this->put("/admin/orders/$order->id", $data)->assertSessionHasErrors('fulfillment');
        $order->payments()->update(['requires_review' => false]);
        $order->items()->update(['type' => 'membership']);
        $this->put("/admin/orders/$order->id", $data)->assertSessionHasErrors('fulfillment');
        $this->assertSame(0, $order->fulfillmentHistory()->count());
    }

    public function test_admin_search_and_details_use_order_snapshots_and_hide_payment_secrets(): void
    {
        $order = $this->order();
        $this->admin();
        $this->get('/admin/orders?search='.$order->reference.'&payment=paid')->assertOk();
        $this->get("/admin/orders/$order->id")->assertOk()->assertDontSee('test-only');
    }
}
