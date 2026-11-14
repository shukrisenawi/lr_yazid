<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProductVariant extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['is_active' => 'boolean', 'price_sen' => 'integer', 'stock' => 'integer', 'attributes' => 'array'];
    }

    /** @return BelongsTo<Product, $this> */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    /** @return BelongsTo<ProductImage, $this> */
    public function image(): BelongsTo
    {
        return $this->belongsTo(ProductImage::class, 'product_image_id');
    }
}
