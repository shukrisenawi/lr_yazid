<?php

namespace App\Services;

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
                $product->fill(Arr::except($data, ['images', 'image_order', 'variants']));
                $product->description = app(RichTextService::class)->sanitize($data['description'] ?? '');
                $product->save();
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
