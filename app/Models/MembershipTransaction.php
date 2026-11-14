<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MembershipTransaction extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['amount_sen' => 'integer', 'starts_at' => 'immutable_datetime', 'expires_at' => 'immutable_datetime', 'paid_at' => 'immutable_datetime', 'previous_expires_at' => 'immutable_datetime'];
    }
}
