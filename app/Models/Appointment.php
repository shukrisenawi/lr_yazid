<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Appointment extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['reviewed_at' => 'immutable_datetime', 'cancelled_at' => 'immutable_datetime'];
    }

    /** @return BelongsTo<AppointmentSlot, $this> */
    public function slot(): BelongsTo
    {
        return $this->belongsTo(AppointmentSlot::class, 'appointment_slot_id');
    }
}
