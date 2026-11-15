<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\Category;
use App\Models\Product;
use App\Models\User;
use App\Services\RichTextService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class CatalogTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        $user = User::factory()->create();
        $user->role = UserRole::Admin;
        $user->save();

        return $user;
    }

    private function data(array $overrides = []): array
    {
        return array_merge([
            'name' => 'Everyday Bottle', 'slug' => 'everyday-bottle', 'sku' => 'BOTTLE-01',
            'price_sen' => 8900, 'stock' => 20, 'weight_grams' => 500,
            'status' => 'active', 'is_featured' => false,
            'description' => '<h2>Made for everyday</h2><p>A reusable bottle.</p>',
        ], $overrides);
    }

    public function test_admin_can_create_and_update_sanitized_products(): void
    {
        $this->actingAs($this->admin())->post('/admin/products', $this->data([
            'description' => '<h2>Details</h2><script>alert(1)</script><p onclick="x()">Safe</p>',
        ]))->assertSessionHasNoErrors();
        $product = Product::firstOrFail();
        $this->assertStringNotContainsString('script', $product->description);
        $this->assertStringNotContainsString('onclick', $product->description);
        $this->put('/admin/products/'.$product->id, $this->data(['price_sen' => 7900]))->assertSessionHasNoErrors();
        $this->assertDatabaseHas('products', ['id' => $product->id, 'price_sen' => 7900]);
    }

    public function test_customer_cannot_manage_catalog_or_upload_editor_images(): void
    {
        $this->actingAs(User::factory()->create())->post('/admin/products', $this->data())->assertForbidden();
        $this->post('/admin/categories', ['name' => 'Test', 'slug' => 'test'])->assertForbidden();
        $this->post('/admin/editor-images')->assertForbidden();
    }

    public function test_admin_can_upload_multiple_optimized_images_and_reorder(): void
    {
        Storage::fake('public');
        $this->actingAs($this->admin())->post('/admin/products', $this->data([
            'images' => [UploadedFile::fake()->image('one.jpg'), UploadedFile::fake()->image('two.png')],
        ]))->assertSessionHasNoErrors();
        $product = Product::with('images')->firstOrFail();
        $this->assertCount(2, $product->images);
        foreach ($product->images as $image) {
            Storage::disk('public')->assertExists($image->path);
            $this->assertStringEndsWith('.webp', $image->path);
        }
        $ids = $product->images->pluck('id')->reverse()->values()->all();
        $this->put('/admin/products/'.$product->id, $this->data(['image_order' => $ids]))->assertSessionHasNoErrors();
        $this->assertSame($ids, $product->images()->pluck('id')->all());
    }

    public function test_invalid_uploads_and_negative_stock_are_rejected(): void
    {
        $this->actingAs($this->admin())->post('/admin/products', $this->data([
            'stock' => -1, 'images' => [UploadedFile::fake()->create('bad.svg', 1, 'image/svg+xml')],
        ]))->assertSessionHasErrors(['stock', 'images.0']);
    }

    public function test_archive_preserves_product_and_hides_public_page(): void
    {
        $product = Product::create($this->data());
        $this->actingAs($this->admin())->delete('/admin/products/'.$product->id)->assertRedirect('/admin/products');
        $this->assertDatabaseHas('products', ['id' => $product->id, 'status' => 'archived']);
        $this->get('/products/'.$product->slug)->assertNotFound();
    }

    public function test_duplicate_creates_a_unique_draft(): void
    {
        $product = Product::create($this->data());
        $this->actingAs($this->admin())->post('/admin/products/'.$product->id.'/duplicate')->assertRedirect();
        $copy = Product::where('id', '!=', $product->id)->firstOrFail();
        $this->assertSame('draft', $copy->status);
        $this->assertNotSame($product->sku, $copy->sku);
    }

    public function test_first_catalog_visit_always_provides_object_filter_defaults(): void
    {
        $this->get('/products')->assertInertia(fn (Assert $page) => $page->where('filters.sort', 'newest')
            ->where('filters.search', null)->where('filters.min', null)->where('filters.max', null)->where('filters.category', null));
        $this->get('/products?sort=')->assertInertia(fn (Assert $page) => $page->where('filters.sort', 'newest'));
    }

    public function test_search_filters_sort_and_pagination_are_server_driven(): void
    {
        $category = Category::create(['name' => 'Essentials', 'slug' => 'essentials']);
        Product::create($this->data(['category_id' => $category->id]));
        Product::create($this->data(['name' => 'Draft product', 'slug' => 'draft', 'sku' => 'DRAFT', 'status' => 'draft']));
        $this->get('/products?search=Bottle&category='.$category->id.'&sort=price_asc&min=5000')
            ->assertInertia(fn (Assert $page) => $page->component('public/Products')->has('products.data', 1)->where('products.data.0.name', 'Everyday Bottle'));
    }

    public function test_sale_dates_and_exclusive_end_are_respected(): void
    {
        $this->freezeTime();
        $product = Product::create($this->data(['sale_price_sen' => 7000, 'sale_starts_at' => now()->subDay(), 'sale_ends_at' => now()->addDay()]));
        $this->assertSame(7000, $product->current_price_sen);
        $product->update(['sale_ends_at' => now()]);
        $this->assertSame(8900, $product->current_price_sen);
    }

    public function test_variants_persist_and_foreign_variant_ids_are_rejected(): void
    {
        $this->actingAs($this->admin())->post('/admin/products', $this->data([
            'variants' => [['name' => 'Black', 'sku' => 'BLACK-01', 'price_sen' => 9000, 'stock' => 3, 'is_active' => true]],
        ]))->assertSessionHasNoErrors();
        $product = Product::firstOrFail();
        $variant = $product->variants()->firstOrFail();
        $other = Product::create($this->data(['slug' => 'other', 'sku' => 'OTHER']));
        $this->put('/admin/products/'.$other->id, $this->data([
            'slug' => 'other', 'sku' => 'OTHER',
            'variants' => [['id' => $variant->id, 'name' => 'Black', 'sku' => 'BLACK-01', 'price_sen' => 9000, 'stock' => 3, 'is_active' => true]],
        ]))->assertSessionHasErrors('variants.0.id');
    }

    public function test_editor_sanitizer_keeps_alignment_and_drops_unsafe_styles_and_urls(): void
    {
        $html = app(RichTextService::class)->sanitize('<p style="text-align:center;position:fixed">Hello</p><a href="javascript:alert(1)">Link</a><img src="data:text/html,evil" onerror="bad()">');
        $this->assertStringContainsString('text-align: center;', $html);
        foreach (['position', 'javascript', 'data:', 'onerror'] as $unsafe) {
            $this->assertStringNotContainsString($unsafe, $html);
        }
    }
}
