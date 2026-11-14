<?php

namespace App\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property CarbonImmutable $starts_at
 * @property CarbonImmutable $ends_at
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
        return $this->belongsTo(AppointmentService::class, 'appointment_service_id');
    }

    /** @return HasMany<Appointment, $this> */
    public function appointments(): HasMany
    {
        return $this->hasMany(Appointment::class);
    }
}
