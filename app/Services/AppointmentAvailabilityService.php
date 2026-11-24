<?php

namespace App\Services;

use App\Models\Appointment;
use App\Models\AppointmentService;
use App\Models\User;
use Carbon\CarbonImmutable;

class AppointmentAvailabilityService
{
    /** @return array<int, array<string, mixed>> */
    public function month(User $user, AppointmentService $service, CarbonImmutable $month, ?Appointment $exclude = null): array
    {
        return $this->range($user, $service, $month->startOfMonth(), $month->endOfMonth(), $exclude);
    }

    /** @return array<int, array<string, mixed>> */
    public function range(User $user, AppointmentService $service, CarbonImmutable $from, CarbonImmutable $to, ?Appointment $exclude = null): array
    {
        $expiry = $user->memberships()->where('status', 'active')->where('starts_at', '<=', now())->where('expires_at', '>', now())->max('expires_at');
        $expiry = $expiry ? CarbonImmutable::parse($expiry) : null;
        $blocked = $service->blockedDates()->whereBetween('date', [$from->toDateString(), $to->addDay()->toDateString()])->pluck('date')->all();
        $active = Appointment::where('user_id', $user->id)->whereIn('status', ['pending', 'approved'])
            ->when($exclude, fn ($query) => $query->where('id', '!=', $exclude->id))->with('slot')->get();

        return $service->slots()->where('starts_at', '>=', $from->startOfDay())->where('starts_at', '<', $to->addDay()->startOfDay())
            ->withCount(['appointments as approved_count' => fn ($query) => $query->where('status', 'approved')->when($exclude, fn ($query) => $query->where('id', '!=', $exclude->id))])
            ->orderBy('starts_at')->get()->map(function ($slot) use ($expiry, $blocked, $active, $service, $exclude) {
                $reason = null;
                if (! $service->is_active || $slot->status !== 'available' || $slot->starts_at->lte(now())
                    || in_array($slot->starts_at->toDateString(), $blocked, true) || in_array($slot->ends_at->subMicrosecond()->toDateString(), $blocked, true)) {
                    $reason = 'unavailable';
                } elseif ($slot->starts_at->lt(now()->addHours($service->minimum_notice_hours)) || $slot->starts_at->gt(now()->addDays($service->maximum_advance_days))) {
                    $reason = 'notice';
                } elseif (! $expiry || $expiry->lte($slot->ends_at)) {
                    $reason = 'eligibility';
                } elseif ($slot->approved_count >= $slot->capacity) {
                    $reason = 'full';
                } elseif ($exclude?->appointment_slot_id === $slot->id || $active->contains(fn ($appointment) => ($appointment->scheduled_starts_at ?? $appointment->slot->starts_at)->lt($slot->ends_at) && ($appointment->scheduled_ends_at ?? $appointment->slot->ends_at)->gt($slot->starts_at))) {
                    $reason = 'conflict';
                } elseif (! $exclude && $active->filter(fn ($appointment) => ($appointment->scheduled_ends_at ?? $appointment->slot->ends_at)->gt(now()))->count() >= $service->maximum_active_bookings) {
                    $reason = 'maximum';
                }

                return ['id' => $slot->id, 'starts_at' => $slot->starts_at->toIso8601String(), 'ends_at' => $slot->ends_at->toIso8601String(),
                    'capacity' => $slot->capacity, 'remaining' => max(0, $slot->capacity - $slot->approved_count),
                    'location' => $slot->location, 'available' => $reason === null, 'reason' => $reason ? __('appointment.'.$reason) : null];
            })->all();
    }
}
