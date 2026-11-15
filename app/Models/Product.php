<?php

namespace App\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 * @property CarbonImmutable|null $sale_starts_at
 * @property CarbonImmutable|null $sale_ends_at
 */
class Product extends Model
{
    protected $guarded = ['id'];

    protected $appends = ['current_price_sen'];

    protected function casts(): array
    {
        return [
            'price_sen' => 'integer', 'sale_price_sen' => 'integer', 'stock' => 'integer',
            'is_featured' => 'boolean', 'sale_starts_at' => 'immutable_datetime',
            'sale_ends_at' => 'immutable_datetime',
        ];
    }

    public function getCurrentPriceSenAttribute(): int
    {
        if ($this->sale_price_sen !== null
            && ($this->sale_starts_at === null || $this->sale_starts_at->lte(now()))
            && ($this->sale_ends_at === null || $this->sale_ends_at->gt(now()))) {
            return $this->sale_price_sen;
        }

        return $this->price_sen;
    }

    /** @return BelongsTo<Category, $this> */
    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    /** @return HasMany<ProductImage, $this> */
    public function images(): HasMany
    {
        return $this->hasMany(ProductImage::class)->orderBy('position')->orderBy('id');
    }

    /** @return HasOne<ProductImage, $this> */
    public function coverImage(): HasOne
    {
        return $this->hasOne(ProductImage::class)->ofMany(['position' => 'min', 'id' => 'min']);
    }

    /** @return HasMany<ProductVariant, $this> */
    public function variants(): HasMany
    {
        return $this->hasMany(ProductVariant::class);
    }
}
