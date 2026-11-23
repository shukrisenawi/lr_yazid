<?php

namespace App\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** @property CarbonImmutable $registered_at */
class VipProgramRegistration extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['registered_at' => 'immutable_datetime', 'cancelled_at' => 'immutable_datetime', 'attendance_at' => 'immutable_datetime'];
    }

    /** @return BelongsTo<VipProgram, $this> */
    public function program(): BelongsTo
    {
        return $this->belongsTo(VipProgram::class, 'vip_program_id')->withTrashed();
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
