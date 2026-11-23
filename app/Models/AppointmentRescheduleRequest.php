<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AppointmentRescheduleRequest extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['reviewed_at' => 'immutable_datetime', 'target_starts_at' => 'immutable_datetime', 'target_ends_at' => 'immutable_datetime'];
    }

    /** @return BelongsTo<AppointmentSlot, $this> */
    public function targetSlot(): BelongsTo
    {
        return $this->belongsTo(AppointmentSlot::class, 'target_slot_id');
    }

    /** @return BelongsTo<Appointment, $this> */
    public function appointment(): BelongsTo
    {
        return $this->belongsTo(Appointment::class)->withTrashed();
    }
}
