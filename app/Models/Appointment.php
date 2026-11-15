<?php

namespace App\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property CarbonImmutable|null $scheduled_starts_at
 * @property CarbonImmutable|null $scheduled_ends_at
 */
class Appointment extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['reviewed_at' => 'immutable_datetime', 'cancelled_at' => 'immutable_datetime',
            'scheduled_starts_at' => 'immutable_datetime', 'scheduled_ends_at' => 'immutable_datetime', 'version' => 'integer'];
    }

    /** @return BelongsTo<AppointmentSlot, $this> */
    public function slot(): BelongsTo
    {
        return $this->belongsTo(AppointmentSlot::class, 'appointment_slot_id');
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @return BelongsTo<AppointmentService, $this> */
    public function service(): BelongsTo
    {
        return $this->belongsTo(AppointmentService::class, 'appointment_service_id');
    }

    /** @return HasMany<AppointmentRescheduleRequest, $this> */
    public function rescheduleRequests(): HasMany
    {
        return $this->hasMany(AppointmentRescheduleRequest::class)->orderByDesc('id');
    }

    /** @return HasMany<AppointmentStatusHistory, $this> */
    public function histories(): HasMany
    {
        return $this->hasMany(AppointmentStatusHistory::class)->orderBy('id');
    }
}
