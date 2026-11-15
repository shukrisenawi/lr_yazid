<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AppointmentStatusHistory extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['previous_starts_at' => 'immutable_datetime', 'previous_ends_at' => 'immutable_datetime',
            'new_starts_at' => 'immutable_datetime', 'new_ends_at' => 'immutable_datetime'];
    }
}
