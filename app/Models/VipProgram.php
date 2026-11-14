<?php

namespace App\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property CarbonImmutable $starts_at
 * @property CarbonImmutable $ends_at
 * @property CarbonImmutable $registration_opens_at
 * @property CarbonImmutable $registration_closes_at
 */
class VipProgram extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'starts_at' => 'immutable_datetime', 'ends_at' => 'immutable_datetime',
            'registration_opens_at' => 'immutable_datetime', 'registration_closes_at' => 'immutable_datetime',
            'publication_queued_at' => 'immutable_datetime', 'capacity' => 'integer',
            'registration_enabled' => 'boolean', 'price_sen' => 'integer',
        ];
    }

    /** @return HasMany<VipProgramImage, $this> */
    public function images(): HasMany
    {
        return $this->hasMany(VipProgramImage::class)->orderBy('position')->orderBy('id');
    }

    /** @return HasMany<VipProgramRegistration, $this> */
    public function registrations(): HasMany
    {
        return $this->hasMany(VipProgramRegistration::class);
    }
}
