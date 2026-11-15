<?php

namespace Tests\Feature;

use App\Models\Cart;
use App\Models\Category;
use App\Models\MembershipPlan;
use App\Models\Order;
use App\Models\Product;
use App\Models\StockReservation;
use App\Models\User;
use App\Services\CartService;
use App\Services\CatalogCardService;
use App\Services\InventoryService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class StorefrontPerformanceTest extends TestCase
{
    use RefreshDatabase;

    private function product(int $index): Product
    {
        return Product::create(['name' => 'Performance product '.$index, 'slug' => 'performance-'.$index,
            'sku' => 'PERF-'.$index, 'price_sen' => 1000, 'stock' => 100, 'status' => 'active']);
    }

    public function test_cart_display_query_count_is_bounded_for_twenty_distinct_products(): void
    {
        $user = User::factory()->create();
        $cart = Cart::create(['user_id' => $user->id]);
        for ($index = 0; $index < 20; $index++) {
            $product = $this->product($index);
            $product->images()->create(['path' => 'performance/cover-'.$index.'.webp', 'position' => 0]);
            $cart->items()->create(['product_id' => $product->id, 'item_key' => "$product->id:0", 'quantity' => 1]);
        }
        $request = Request::create('/cart');
        $request->setUserResolver(fn () => $user);
        DB::enableQueryLog();
        DB::flushQueryLog();
        try {
            $summary = app(CartService::class)->summary($request);
            $count = count(DB::getQueryLog());
        } finally {
            DB::disableQueryLog();
        }
        $this->assertCount(20, $summary['items']);
        $this->assertSame(20000, $summary['subtotal_sen']);
        $this->assertTrue($summary['valid']);
        $this->assertLessThanOrEqual(10, $count, 'Twenty-product cart queries: '.$count);
        if (getenv('STOREFRONT_METRICS') === '1') {
            fwrite(STDOUT, "Twenty-product cart queries: $count\n");
        }
    }

    public function test_twelve_catalog_cards_have_a_bounded_payload_without_full_descriptions_or_galleries(): void
    {
        for ($index = 0; $index < 12; $index++) {
            $product = $this->product($index);
            $product->update(['description' => str_repeat('Long catalog description. ', 1000)]);
            for ($image = 0; $image < 6; $image++) {
                $product->images()->create(['path' => 'performance/'.$index.'-'.$image.'.webp',
                    'alt' => 'Gallery image '.$image, 'position' => $image]);
            }
        }
        $response = $this->get('/products')->assertOk();
        $rows = $response->viewData('page')['props']['products']['data'];
        $bytes = strlen(json_encode($rows, JSON_THROW_ON_ERROR));
        $this->assertCount(12, $rows);
        $this->assertLessThan(20000, $bytes, 'Twelve catalog card JSON bytes: '.$bytes);
        if (getenv('STOREFRONT_METRICS') === '1') {
            fwrite(STDOUT, "Twelve catalog card JSON bytes: $bytes\n");
        }
        foreach ($rows as $row) {
            $this->assertArrayNotHasKey('description', $row);
            $this->assertCount(1, $row['images']);
        }
    }

    private function assertDisplayParity(Cart $cart): void
    {
        $inventory = app(InventoryService::class);
        $items = $cart->items()->with(['product' => fn ($query) => $query->withCount('variants'), 'variant'])->get();
        $display = $inventory->forCartDisplay($items);
        foreach ($items as $item) {
            $expected = ['unit_price_sen' => $item->variant->price_sen ?? $item->product->current_price_sen, 'available' => 0, 'error' => null];
            try {
                $selection = $inventory->selection($item->product_id, $item->product_variant_id);
                $expected['unit_price_sen'] = $selection['unit_price_sen'];
                $expected['available'] = $selection['available'];
                $inventory->assertQuantity($item->quantity, $selection['available']);
            } catch (ValidationException $exception) {
                $expected['error'] = collect($exception->errors())->flatten()->first();
            }
            $this->assertSame($expected, $display[$item->id], 'Cart item '.$item->id);
        }
    }

    public function test_batched_display_matches_canonical_inventory_and_reads_fresh_changes(): void
    {
        $this->travelTo(now()->startOfSecond());
        $user = User::factory()->create();
        $cart = Cart::create(['user_id' => $user->id]);
        $plain = $this->product(1);
        $variantProduct = $this->product(2);
        $variant = $variantProduct->variants()->create(['name' => 'Blue', 'sku' => 'PERF-BLUE', 'price_sen' => 2300, 'stock' => 10, 'is_active' => true]);
        $membership = $this->product(3);
        $membership->update(['type' => 'membership']);
        $plan = MembershipPlan::create(['name' => 'VIP', 'product_id' => $membership->id, 'price_sen' => 19000, 'is_active' => true]);
        foreach ([[$plain, null], [$variantProduct, $variant->id], [$variantProduct, null], [$plain, $variant->id], [$membership, null], [$membership, $variant->id]] as [$product, $variantId]) {
            $cart->items()->create(['product_id' => $product->id, 'product_variant_id' => $variantId,
                'item_key' => $product->id.':'.($variantId ?? 0), 'quantity' => 1]);
        }
        $order = Order::create(['user_id' => $user->id, 'reference' => 'PERF-RESERVATIONS', 'checkout_key' => Str::uuid(),
            'customer_name' => 'Synthetic test', 'customer_email' => $user->email, 'customer_phone' => '+60123456789',
            'subtotal_sen' => 1000, 'shipping_fee_sen' => 0, 'total_sen' => 1000, 'expires_at' => now()->addHour()]);
        foreach ([[$plain, null, 'active', now()->addMinute(), 3], [$plain, null, 'active', now(), 7],
            [$plain, null, 'released', now()->addHour(), 9], [$variantProduct, $variant->id, 'active', now()->addHour(), 4],
            [$variantProduct, null, 'active', now()->addHour(), 8]] as [$product, $variantId, $status, $expires, $quantity]) {
            $orderItem = $order->items()->create(['product_id' => $product->id, 'product_variant_id' => $variantId,
                'product_name' => $product->name, 'sku' => $product->sku, 'quantity' => $quantity, 'unit_price_sen' => 1000, 'line_total_sen' => $quantity * 1000]);
            StockReservation::create(['order_id' => $order->id, 'order_item_id' => $orderItem->id, 'product_id' => $product->id,
                'product_variant_id' => $variantId, 'quantity' => $quantity, 'status' => $status, 'expires_at' => $expires]);
        }
        $this->assertDisplayParity($cart);
        $request = Request::create('/cart');
        $request->setUserResolver(fn () => $user);
        $before = app(CartService::class)->summary($request);
        $this->assertSame(97, $before['items'][0]['available']);
        $this->assertSame(6, $before['items'][1]['available']);
        $plain->update(['stock' => 5, 'sale_price_sen' => 750, 'sale_starts_at' => now(), 'sale_ends_at' => now()->addMinute()]);
        $after = app(CartService::class)->summary($request);
        $this->assertSame(2, $after['items'][0]['available']);
        $this->assertSame(750, $after['items'][0]['unit_price_sen']);
        foreach ([0, 3, 100] as $quantity) {
            $cart->items()->where('product_id', $plain->id)->whereNull('product_variant_id')->update(['quantity' => $quantity]);
            $this->assertDisplayParity($cart);
        }
        $variant->update(['is_active' => false]);
        $plan->update(['is_active' => false]);
        $this->assertDisplayParity($cart);
        $plan->update(['is_active' => true]);
        $cart->items()->where('product_id', $membership->id)->update(['quantity' => 2]);
        $this->assertDisplayParity($cart);
        $plan->delete();
        $this->assertDisplayParity($cart);
        $plain->update(['status' => 'archived']);
        $this->assertDisplayParity($cart);
        $plain->update(['status' => 'active']);
        $this->travel(1)->minutes();
        $this->assertDisplayParity($cart);
        $this->assertSame(1000, app(CartService::class)->summary($request)['items'][0]['unit_price_sen']);
        $this->assertSame(5, app(CartService::class)->summary($request)['items'][0]['available']);
    }

    public function test_cover_order_card_fields_and_full_detail_gallery_are_preserved(): void
    {
        $this->travelTo(now()->startOfSecond());
        $product = $this->product(1);
        $category = Category::create(['name' => 'Performance category', 'slug' => 'performance-category']);
        $product->update(['description' => '<p>Full detail content</p>', 'is_featured' => true,
            'sale_price_sen' => 700, 'sale_starts_at' => now(), 'sale_ends_at' => now()->addMinute()]);
        $product->images()->create(['path' => 'later.webp', 'position' => 5]);
        $cover = $product->images()->create(['path' => 'cover.webp', 'position' => 1]);
        $product->images()->create(['path' => 'tie.webp', 'position' => 1]);
        $product->variants()->create(['name' => 'Inactive', 'sku' => 'PERF-INACTIVE', 'stock' => 5, 'price_sen' => 1500, 'is_active' => false]);
        $cards = app(CatalogCardService::class);
        $card = $cards->data($cards->query()->findOrFail($product->id));
        $this->assertSame($cover->id, $card['images'][0]['id']);
        $this->assertSame(1, $card['variants_count']);
        $this->assertSame(700, $card['current_price_sen']);
        $this->assertNull($card['category']);
        $this->assertArrayNotHasKey('description', $card);
        $product->update(['category_id' => $category->id]);
        $card = $cards->data($cards->query()->findOrFail($product->id));
        $this->assertSame($category->only(['id', 'name', 'slug']), $card['category']);
        $detail = $this->get('/products/'.$product->slug)->assertOk()->viewData('page')['props']['product'];
        $this->assertCount(3, $detail['images']);
        $this->assertSame('<p>Full detail content</p>', $detail['description']);
        $this->assertSame($cover->id, $detail['images'][0]['id']);
        $home = $this->get('/')->assertOk()->viewData('page')['props'];
        $this->assertSame($card, $home['featured'][0]);
        $related = $this->product(2);
        $related->update(['category_id' => $category->id]);
        $detailPage = $this->get('/products/'.$product->slug)->assertOk()->viewData('page')['props'];
        $this->assertSame($related->id, $detailPage['related'][0]['id']);
        $this->assertArrayNotHasKey('description', $detailPage['related'][0]);
        $this->travel(1)->minutes();
        $this->assertSame(1000, $cards->data($cards->query()->findOrFail($product->id))['current_price_sen']);
    }

    public function test_catalog_query_count_does_not_grow_with_card_count(): void
    {
        $cards = app(CatalogCardService::class);
        $counts = [];
        foreach ([1, 12] as $size) {
            for ($index = Product::count(); $index < $size; $index++) {
                $product = $this->product($index);
                $product->images()->create(['path' => 'cover.webp', 'position' => 0]);
            }
            DB::enableQueryLog();
            DB::flushQueryLog();
            try {
                $rows = $cards->query()->get()->map($cards->data(...));
                $counts[] = count(DB::getQueryLog());
            } finally {
                DB::disableQueryLog();
            }
            $this->assertCount($size, $rows);
        }
        $this->assertSame($counts[0], $counts[1]);
        $this->assertLessThanOrEqual(3, $counts[1]);
    }
}
