<?php

namespace App\Services;

use App\Models\Product;
use Illuminate\Database\Eloquent\Builder;

class CatalogCardService
{
    /** @return Builder<Product> */
    public function query(): Builder
    {
        return Product::query()->select(['id', 'category_id', 'name', 'slug', 'type', 'price_sen',
            'sale_price_sen', 'sale_starts_at', 'sale_ends_at', 'stock', 'created_at'])
            ->with(['category:id,name,slug', 'coverImage' => fn ($query) => $query->select([
                'product_images.id', 'product_images.product_id', 'product_images.path', 'product_images.alt', 'product_images.position',
            ])])->withCount('variants');
    }

    /** @return array<string, mixed> */
    public function data(Product $product): array
    {
        $image = $product->coverImage;

        return ['id' => $product->id, 'name' => $product->name, 'slug' => $product->slug, 'type' => $product->type,
            'price_sen' => $product->price_sen, 'current_price_sen' => $product->current_price_sen, 'stock' => $product->stock,
            'variants_count' => $product->variants_count, 'category' => $product->category?->only(['id', 'name', 'slug']),
            'images' => $image ? [$image->only(['id', 'url', 'alt', 'position'])] : []];
    }
}
