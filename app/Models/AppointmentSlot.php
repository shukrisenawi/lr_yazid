<?php

namespace App\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property CarbonImmutable $starts_at
 * @property CarbonImmutable $ends_at
 * @property int $approved_count
 */
class AppointmentSlot extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['starts_at' => 'immutable_datetime', 'ends_at' => 'immutable_datetime', 'capacity' => 'integer'];
    }

    /** @return BelongsTo<AppointmentService, $this> */
    public function service(): BelongsTo
    {
        return $this->belongsTo(AppointmentService::class, 'appointment_service_id')->withTrashed();
    }

    /** @return HasMany<Appointment, $this> */
    public function appointments(): HasMany
    {
        return $this->hasMany(Appointment::class);
    }

    /** @return HasMany<AppointmentRescheduleRequest, $this> */
    public function targetReschedules(): HasMany
    {
        return $this->hasMany(AppointmentRescheduleRequest::class, 'target_slot_id');
    }

    /** @return HasMany<AppointmentRescheduleRequest, $this> */
    public function originalReschedules(): HasMany
    {
        return $this->hasMany(AppointmentRescheduleRequest::class, 'original_slot_id');
    }

    /** @return HasMany<AppointmentStatusHistory, $this> */
    public function previousHistories(): HasMany
    {
        return $this->hasMany(AppointmentStatusHistory::class, 'previous_slot_id');
    }

    /** @return HasMany<AppointmentStatusHistory, $this> */
    public function newHistories(): HasMany
    {
        return $this->hasMany(AppointmentStatusHistory::class, 'new_slot_id');
    }
}
