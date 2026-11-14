<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class CatalogRefinementTest extends TestCase
{
    use RefreshDatabase;

    private function product(string $slug): Product
    {
        return Product::create(['name' => $slug, 'slug' => $slug, 'sku' => $slug, 'price_sen' => 1200, 'stock' => 5, 'status' => 'active']);
    }

    private function admin(): void
    {
        $user = User::factory()->create();
        $user->role = UserRole::Admin;
        $user->save();
        $this->actingAs($user);
    }

    public function test_replacement_preserves_image_identity_and_order(): void
    {
        Storage::fake('public');
        $this->admin();
        $product = $this->product('one');
        Storage::disk('public')->put('products/old.webp', 'old');
        $image = $product->images()->create(['path' => 'products/old.webp', 'position' => 3]);
        $this->post("/admin/products/$product->id/images/$image->id", ['image' => UploadedFile::fake()->image('new.png')])->assertSessionHasNoErrors();
        $image->refresh();
        $this->assertSame(3, $image->position);
        Storage::disk('public')->assertMissing('products/old.webp');
        Storage::disk('public')->assertExists($image->path);
    }

    public function test_invalid_replacement_keeps_original_file(): void
    {
        Storage::fake('public');
        $this->admin();
        $product = $this->product('one');
        Storage::disk('public')->put('products/old.webp', 'old');
        $image = $product->images()->create(['path' => 'products/old.webp']);
        $this->post("/admin/products/$product->id/images/$image->id", ['image' => UploadedFile::fake()->create('bad.txt')])->assertSessionHasErrors('image');
        Storage::disk('public')->assertExists('products/old.webp');
    }

    public function test_cross_product_image_replacement_is_denied(): void
    {
        $this->admin();
        $product = $this->product('one');
        $other = $this->product('two');
        $image = $other->images()->create(['path' => 'products/other.webp']);
        $this->post("/admin/products/$product->id/images/$image->id", ['image' => UploadedFile::fake()->image('new.png')])->assertNotFound();
    }

    public function test_variant_attributes_and_image_persist_and_invalid_foreign_image_is_rejected(): void
    {
        $this->admin();
        $product = $this->product('one');
        $other = $this->product('two');
        $image = $product->images()->create(['path' => 'products/one.webp']);
        $foreign = $other->images()->create(['path' => 'products/two.webp']);
        $data = [
            'name' => 'one', 'slug' => 'one', 'sku' => 'one', 'price_sen' => 1200, 'stock' => 5,
            'weight_grams' => 100, 'status' => 'active', 'is_featured' => false,
            'variants' => [['name' => 'Black', 'sku' => 'BLACK', 'price_sen' => 1300, 'stock' => 2, 'is_active' => true, 'product_image_id' => $image->id, 'attributes' => [['name' => 'Color', 'value' => 'Black']]]],
        ];
        $this->put("/admin/products/$product->id", $data)->assertSessionHasNoErrors();
        $variant = $product->variants()->firstOrFail();
        $this->assertSame($image->id, $variant->product_image_id);
        $this->assertSame([['name' => 'Color', 'value' => 'Black']], $variant->attributes);
        $data['variants'][0]['id'] = $variant->id;
        $data['variants'][0]['product_image_id'] = $foreign->id;
        $this->put("/admin/products/$product->id", $data)->assertSessionHasErrors('variants.0.product_image_id');
    }
}
