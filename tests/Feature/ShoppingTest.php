<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\Address;
use App\Models\Cart;
use App\Models\CartItem;
use App\Models\CommerceSetting;
use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use App\Services\CheckoutService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class ShoppingTest extends TestCase
{
    use RefreshDatabase;

    private function product(int $stock = 10): Product
    {
        $slug = (string) Str::uuid();

        return Product::create(['name' => 'Bottle', 'slug' => $slug, 'sku' => $slug, 'price_sen' => 8900, 'stock' => $stock, 'status' => 'active']);
    }

    private function address(User $user): Address
    {
        return $user->addresses()->create([
            'full_name' => 'Customer', 'phone' => '+60123456789', 'line1' => '12 Sample Street',
            'city' => 'Shah Alam', 'state' => 'Selangor', 'postcode' => '40100', 'is_default' => true,
        ]);
    }

    private function checkout(User $user, Product $product, int $quantity = 1): Order
    {
        $address = $this->address($user);
        $this->actingAs($user)->post('/cart/items', ['product_id' => $product->id, 'quantity' => $quantity])->assertSessionHasNoErrors();
        $this->post('/checkout', ['checkout_key' => (string) Str::uuid(), 'address_id' => $address->id])->assertSessionHasNoErrors();

        return $user->orders()->latest('id')->firstOrFail();
    }

    public function test_guest_cart_persists_updates_removes_and_clears(): void
    {
        $product = $this->product();
        $this->post('/cart/items', ['product_id' => $product->id, 'quantity' => 2])->assertSessionHasNoErrors();
        $this->get('/cart')->assertInertia(fn (Assert $page) => $page->where('cartCount', 2)->where('cart.items.0.quantity', 2));
        $this->post('/cart/items', ['product_id' => $product->id, 'quantity' => 1])->assertSessionHasNoErrors();
        $item = CartItem::firstOrFail();
        $this->assertSame(3, $item->quantity);
        $this->patch("/cart/items/$item->id", ['quantity' => 4])->assertSessionHasNoErrors();
        $this->assertSame(4, $item->fresh()->quantity);
        $this->delete("/cart/items/$item->id")->assertRedirect();
        $this->assertDatabaseCount('cart_items', 0);
        $this->post('/cart/items', ['product_id' => $product->id, 'quantity' => 1]);
        $this->delete('/cart')->assertRedirect();
        $this->assertDatabaseCount('cart_items', 0);
    }

    public function test_cart_rejects_invalid_quantities_unavailable_and_foreign_variants(): void
    {
        $product = $this->product(2);
        foreach ([0, -1, 3, 100] as $quantity) {
            $this->post('/cart/items', ['product_id' => $product->id, 'quantity' => $quantity])->assertSessionHasErrors('quantity');
        }
        $variant = $product->variants()->create(['name' => 'Black', 'sku' => 'BLACK', 'price_sen' => 9000, 'stock' => 2]);
        $this->post('/cart/items', ['product_id' => $product->id, 'quantity' => 1])->assertSessionHasErrors('cart');
        $other = $this->product();
        $this->post('/cart/items', ['product_id' => $other->id, 'product_variant_id' => $variant->id, 'quantity' => 1])->assertSessionHasErrors('cart');
        $product->update(['status' => 'archived']);
        $this->post('/cart/items', ['product_id' => $product->id, 'product_variant_id' => $variant->id, 'quantity' => 1])->assertSessionHasErrors('cart');
    }

    public function test_cart_items_are_owned_and_cannot_be_changed_by_another_user(): void
    {
        $owner = User::factory()->create();
        $this->actingAs($owner)->post('/cart/items', ['product_id' => $this->product()->id, 'quantity' => 1]);
        $item = CartItem::firstOrFail();
        $this->actingAs(User::factory()->create())->patch("/cart/items/$item->id", ['quantity' => 2])->assertNotFound();
        $this->delete("/cart/items/$item->id")->assertNotFound();
    }

    public function test_guest_cart_merges_on_real_login_with_stock_cap(): void
    {
        $user = User::factory()->create();
        $product = $this->product(4);
        $cart = Cart::create(['user_id' => $user->id]);
        $cart->items()->create(['product_id' => $product->id, 'item_key' => "$product->id:0", 'quantity' => 3]);
        $this->post('/cart/items', ['product_id' => $product->id, 'quantity' => 2])->assertSessionHasNoErrors();
        $this->post('/login', ['email' => $user->email, 'password' => 'password'])->assertSessionHasNoErrors();
        $this->assertAuthenticatedAs($user);
        $this->assertSame(4, $cart->items()->firstOrFail()->quantity);
        $this->assertDatabaseCount('carts', 1);
    }

    public function test_checkout_requires_authentication(): void
    {
        $this->get('/checkout')->assertRedirect('/login');
        $this->post('/checkout', [])->assertRedirect('/login');
    }

    public function test_checkout_recalculates_prices_and_preserves_snapshots(): void
    {
        $user = User::factory()->create();
        $product = $this->product();
        $address = $this->address($user);
        $this->actingAs($user)->post('/cart/items', ['product_id' => $product->id, 'quantity' => 2]);
        $product->update(['price_sen' => 7900]);
        $this->post('/checkout', [
            'checkout_key' => (string) Str::uuid(), 'address_id' => $address->id,
            'total_sen' => 1, 'unit_price_sen' => 1,
        ])->assertSessionHasNoErrors();
        $order = Order::firstOrFail();
        $this->assertSame(15800, $order->subtotal_sen);
        $this->assertSame(1000, $order->shipping_fee_sen);
        $this->assertSame(16800, $order->total_sen);
        $this->assertSame('pending', $order->payment_status);
        $this->assertSame(10, $product->fresh()->stock);
        $this->assertDatabaseCount('stock_reservations', 1);
        $this->assertDatabaseCount('cart_items', 0);
        $product->update(['name' => 'Changed', 'price_sen' => 100]);
        $address->update(['line1' => 'Changed address']);
        $this->assertSame('Bottle', $order->items()->firstOrFail()->product_name);
        $this->assertSame(7900, $order->items()->firstOrFail()->unit_price_sen);
        $this->assertSame('12 Sample Street', $order->shipping_address['line1']);
    }

    public function test_duplicate_checkout_is_idempotent_after_cart_is_cleared(): void
    {
        $user = User::factory()->create();
        $address = $this->address($user);
        $product = $this->product();
        $this->actingAs($user)->post('/cart/items', ['product_id' => $product->id, 'quantity' => 1]);
        $data = ['checkout_key' => (string) Str::uuid(), 'address_id' => $address->id];
        $this->post('/checkout', $data)->assertSessionHasNoErrors();
        $this->post('/checkout', $data)->assertSessionHasNoErrors();
        $this->assertDatabaseCount('orders', 1);
        $this->assertDatabaseCount('stock_reservations', 1);
    }

    public function test_reserved_stock_cannot_be_oversold_and_failure_rolls_back(): void
    {
        $product = $this->product(1);
        $first = User::factory()->create();
        $second = User::factory()->create();
        $secondAddress = $this->address($second);
        $this->actingAs($second)->post('/cart/items', ['product_id' => $product->id, 'quantity' => 1]);
        $this->checkout($first, $product);
        $this->actingAs($second)->post('/checkout', ['checkout_key' => (string) Str::uuid(), 'address_id' => $secondAddress->id])->assertSessionHasErrors('quantity');
        $this->assertDatabaseCount('orders', 1);
        $this->assertDatabaseCount('stock_reservations', 1);
        $this->assertSame(1, $product->fresh()->stock);
    }

    public function test_variant_stock_and_prices_are_independent(): void
    {
        $product = $this->product(0);
        $variant = $product->variants()->create(['name' => 'Black', 'sku' => 'BLACK', 'price_sen' => 9500, 'stock' => 1]);
        $user = User::factory()->create();
        $address = $this->address($user);
        $this->actingAs($user)->post('/cart/items', ['product_id' => $product->id, 'product_variant_id' => $variant->id, 'quantity' => 1])->assertSessionHasNoErrors();
        $this->post('/checkout', ['checkout_key' => (string) Str::uuid(), 'address_id' => $address->id])->assertSessionHasNoErrors();
        $this->assertSame(9500, Order::firstOrFail()->subtotal_sen);
        $this->assertSame($variant->id, Order::firstOrFail()->reservations()->firstOrFail()->product_variant_id);
    }

    public function test_foreign_address_is_rejected_and_cart_preserved(): void
    {
        $user = User::factory()->create();
        $foreign = $this->address(User::factory()->create());
        $this->actingAs($user)->post('/cart/items', ['product_id' => $this->product()->id, 'quantity' => 1]);
        $this->post('/checkout', ['checkout_key' => (string) Str::uuid(), 'address_id' => $foreign->id])->assertSessionHasErrors('address_id');
        $this->assertDatabaseCount('orders', 0);
        $this->assertDatabaseCount('cart_items', 1);
    }

    public function test_cancellation_releases_reservations_without_changing_inventory(): void
    {
        $user = User::factory()->create();
        $product = $this->product(1);
        $order = $this->checkout($user, $product);
        $this->post("/orders/$order->id/cancel")->assertSessionHasNoErrors();
        $this->assertSame('cancelled', $order->fresh()->payment_status);
        $this->assertSame('released', $order->reservations()->firstOrFail()->status);
        $this->assertSame(1, $product->fresh()->stock);
    }

    public function test_expiry_is_idempotent_and_expired_holds_do_not_block_checkout(): void
    {
        $this->freezeTime();
        $product = $this->product(1);
        $order = $this->checkout(User::factory()->create(), $product);
        $this->travel(31)->minutes();
        $this->checkout(User::factory()->create(), $product);
        $this->assertSame(1, app(CheckoutService::class)->expire());
        $this->assertSame(0, app(CheckoutService::class)->expire());
        $this->assertSame('reservation_expired', $order->fresh()->cancellation_reason);
    }

    public function test_order_ownership_and_paid_cancellation_are_protected(): void
    {
        $owner = User::factory()->create();
        $order = $this->checkout($owner, $this->product());
        $this->actingAs(User::factory()->create())->get("/orders/$order->id")->assertForbidden();
        $this->post("/orders/$order->id/cancel")->assertForbidden();
        $order->update(['payment_status' => 'paid']);
        $this->actingAs($owner)->post("/orders/$order->id/cancel")->assertForbidden();
    }

    public function test_shipping_configuration_is_admin_only_and_can_disable_checkout(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user)->put('/admin/shipping', ['shipping_fee_sen' => 500])->assertForbidden();
        $user->role = UserRole::Admin;
        $user->save();
        $this->put('/admin/shipping', ['shipping_fee_sen' => 500, 'shipping_enabled' => false, 'reservation_minutes' => 15])->assertSessionHasNoErrors();
        $this->assertSame(500, CommerceSetting::current()->shipping_fee_sen);
        $address = $this->address($user);
        $this->post('/cart/items', ['product_id' => $this->product()->id, 'quantity' => 1]);
        $this->post('/checkout', ['checkout_key' => (string) Str::uuid(), 'address_id' => $address->id])->assertSessionHasErrors('cart');
        $this->assertDatabaseCount('orders', 0);
    }

    public function test_empty_cart_and_archived_products_cannot_be_checked_out(): void
    {
        $user = User::factory()->create();
        $address = $this->address($user);
        $this->actingAs($user)->post('/checkout', ['checkout_key' => (string) Str::uuid(), 'address_id' => $address->id])->assertSessionHasErrors('cart');
        $product = $this->product();
        $this->post('/cart/items', ['product_id' => $product->id, 'quantity' => 1]);
        $product->update(['status' => 'archived']);
        $this->post('/checkout', ['checkout_key' => (string) Str::uuid(), 'address_id' => $address->id])->assertSessionHasErrors('cart');
        $this->assertDatabaseCount('orders', 0);
    }

    public function test_account_with_order_history_cannot_be_deleted(): void
    {
        $user = User::factory()->create();
        $this->checkout($user, $this->product());
        $this->delete('/settings/profile', ['password' => 'password'])->assertSessionHasErrors('password');
        $this->assertModelExists($user);
    }
}
