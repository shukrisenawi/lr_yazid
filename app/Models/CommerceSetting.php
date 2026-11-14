<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CommerceSetting extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return ['shipping_fee_sen' => 'integer', 'shipping_enabled' => 'boolean', 'reservation_minutes' => 'integer'];
    }

    public static function current(): self
    {
        return self::find(1) ?? new self([
            'id' => 1, 'shipping_fee_sen' => 1000, 'shipping_enabled' => true, 'reservation_minutes' => 30,
        ]);
    }
}
