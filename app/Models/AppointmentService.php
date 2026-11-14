<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AppointmentService extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['is_active' => 'boolean', 'duration_minutes' => 'integer', 'default_capacity' => 'integer',
            'minimum_notice_hours' => 'integer', 'maximum_advance_days' => 'integer',
            'cancellation_notice_hours' => 'integer', 'maximum_active_bookings' => 'integer'];
    }

    /** @return HasMany<AppointmentSlot, $this> */
    public function slots(): HasMany
    {
        return $this->hasMany(AppointmentSlot::class);
    }

    /** @return HasMany<AppointmentBlockedDate, $this> */
    public function blockedDates(): HasMany
    {
        return $this->hasMany(AppointmentBlockedDate::class)->orderBy('date');
    }

    /** @return HasMany<AppointmentSchedule, $this> */
    public function schedules(): HasMany
    {
        return $this->hasMany(AppointmentSchedule::class);
    }
}
