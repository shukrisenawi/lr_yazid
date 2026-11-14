<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AppointmentSchedule extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['configuration' => 'array'];
    }
}
