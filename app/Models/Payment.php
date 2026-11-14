<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Payment extends Model
{
    protected $guarded = ['id'];

    protected $hidden = ['secret_key_snapshot'];

    protected function casts(): array
    {
        return ['amount_sen' => 'integer', 'secret_key_snapshot' => 'encrypted', 'requires_review' => 'boolean', 'paid_at' => 'immutable_datetime', 'checked_at' => 'immutable_datetime'];
    }

    /** @return BelongsTo<Order, $this> */
    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    /** @return HasMany<PaymentCallback, $this> */
    public function callbacks(): HasMany
    {
        return $this->hasMany(PaymentCallback::class);
    }
}
