<?php

namespace App\Services;

use App\Models\MembershipPlan;
use App\Models\Product;
use App\Models\ProductVariant;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Throwable;

class ProductService
{
    /** @param array<string, mixed> $data */
    public function save(Product $product, array $data): Product
    {
        $storedPaths = [];
        try {
            return DB::transaction(function () use ($product, $data, &$storedPaths) {
                if ($product->exists) {
                    $product = Product::whereKey($product->id)->lockForUpdate()->firstOrFail();
                    if ($data['stock'] < app(InventoryService::class)->held($product->id, null, true)) {
                        throw ValidationException::withMessages(['stock' => 'Stock cannot be reduced below active order reservations.']);
                    }
                }
                if ($product->type === 'membership') {
                    if (! empty($data['variants'])) {
                        throw ValidationException::withMessages(['variants' => 'Membership plans cannot have physical product variants.']);
                    }
                    $data = array_merge($data, ['stock' => 0, 'weight_grams' => 0, 'sale_price_sen' => null, 'sale_starts_at' => null, 'sale_ends_at' => null]);
                }
                $product->fill(Arr::except($data, ['images', 'image_order', 'variants']));
                $product->description = app(RichTextService::class)->sanitize($data['description'] ?? '');
                $product->save();
                if ($product->type === 'membership') {
                    MembershipPlan::where('product_id', $product->id)->update(['name' => $product->name, 'price_sen' => $product->price_sen, 'is_active' => $product->status === 'active']);
                }
                foreach ($data['image_order'] ?? [] as $position => $id) {
                    $product->images()->whereKey($id)->update(['position' => $position]);
                }
                $position = (int) $product->images()->max('position') + 1;
                foreach ($data['images'] ?? [] as $file) {
                    $path = app(ProductImageService::class)->store($file);
                    $storedPaths[] = $path;
                    $product->images()->create([
                        'path' => $path,
                        'alt' => $product->name, 'position' => $position++,
                    ]);
                }
                foreach ($data['variants'] ?? [] as $index => $variant) {
                    $id = $variant['id'] ?? null;
                    if ($id && $variant['stock'] < app(InventoryService::class)->held($product->id, $id, true)) {
                        throw ValidationException::withMessages(["variants.$index.stock" => 'Stock cannot be reduced below active order reservations.']);
                    }
                    if (ProductVariant::where('sku', $variant['sku'])->when($id, fn ($q) => $q->where('id', '!=', $id))->exists()) {
                        throw ValidationException::withMessages(["variants.$index.sku" => 'This variant SKU is already in use.']);
                    }
                    $product->variants()->updateOrCreate(['id' => $id], Arr::except($variant, 'id'));
                }

                return $product;
            });
        } catch (Throwable $error) {
            Storage::disk('public')->delete($storedPaths);
            throw $error;
        }
    }
}
