<?php

namespace App\Services;

use App\Models\Membership;
use App\Models\User;
use App\Models\VipProgram;
use App\Models\VipProgramRegistration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class ProgramRegistrationService
{
    public function register(User $user, VipProgram $program): VipProgramRegistration
    {
        return DB::transaction(function () use ($user, $program) {
            $user = User::whereKey($user->id)->lockForUpdate()->firstOrFail();
            $program = VipProgram::whereKey($program->id)->lockForUpdate()->firstOrFail();
            $eligible = Membership::where('user_id', $user->id)->where('status', 'active')
                ->where('starts_at', '<=', now())->where('expires_at', '>', now())
                ->where('expires_at', '>', $program->ends_at)->lockForUpdate()->first();
            if (! $eligible) {
                throw ValidationException::withMessages(['program' => __('program.eligibility')]);
            }
            if ($program->status !== 'published' || ! $program->registration_enabled || $program->price_sen !== 0
                || $program->registration_opens_at->gt(now()) || $program->registration_closes_at->lte(now()) || $program->starts_at->lte(now())) {
                throw ValidationException::withMessages(['program' => __('program.unavailable')]);
            }
            // Locking reads must see newly committed registrations under MySQL REPEATABLE READ.
            $participants = $program->registrations()->where('status', 'registered')->lockForUpdate()->get();
            $existing = $program->registrations()->where('user_id', $user->id)->lockForUpdate()->first();
            if ($existing?->status === 'registered') {
                return $existing;
            }
            if ($participants->count() >= $program->capacity) {
                throw ValidationException::withMessages(['program' => __('program.full')]);
            }
            $version = $existing ? $existing->version + 1 : 1;
            $existing ??= new VipProgramRegistration([
                'vip_program_id' => $program->id, 'user_id' => $user->id,
                'reference' => 'REG-'.Str::upper(Str::random(20)),
            ]);
            $existing->fill(['status' => 'registered', 'version' => $version, 'attendance' => 'unmarked', 'registered_at' => now(),
                'cancelled_at' => null, 'cancellation_reason' => null, 'attendance_at' => null, 'attendance_by' => null])->save();
            app(ProgramNotificationService::class)->queue($user, $program, 'registration', "registration:$existing->id:$version");

            return $existing;
        }, 3);
    }

    public function cancel(User $user, VipProgramRegistration $registration): void
    {
        abort_unless($registration->user_id === $user->id, 403);
        DB::transaction(function () use ($user, $registration) {
            User::whereKey($user->id)->lockForUpdate()->firstOrFail();
            $program = VipProgram::whereKey($registration->vip_program_id)->lockForUpdate()->firstOrFail();
            $registration = VipProgramRegistration::whereKey($registration->id)->lockForUpdate()->firstOrFail();
            if ($registration->status !== 'registered') {
                return;
            }
            if ($program->starts_at->lte(now()) || $program->status !== 'published') {
                throw ValidationException::withMessages(['program' => __('program.unavailable')]);
            }
            $registration->update(['status' => 'cancelled', 'cancelled_at' => now(), 'cancellation_reason' => 'customer_withdrawal']);
            app(ProgramNotificationService::class)->queue($user, $program, 'withdrawal', "withdrawal:$registration->id:$registration->version");
        }, 3);
    }
}
