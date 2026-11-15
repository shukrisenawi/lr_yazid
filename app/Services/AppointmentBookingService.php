<?php

namespace App\Services;

use App\Enums\UserRole;
use App\Models\Appointment;
use App\Models\AppointmentRescheduleRequest;
use App\Models\AppointmentService;
use App\Models\AppointmentSlot;
use App\Models\AppointmentStatusHistory;
use App\Models\Membership;
use App\Models\User;
use Closure;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class AppointmentBookingService
{
    /** @param array<string, mixed> $data */
    public function book(User $user, AppointmentSlot $slot, array $data): Appointment
    {
        return DB::transaction(function () use ($user, $slot, $data) {
            $user = User::whereKey($user->id)->lockForUpdate()->firstOrFail();
            $existing = Appointment::where('user_id', $user->id)->where('booking_key', $data['booking_key'])->first();
            if ($existing) {
                if ($existing->appointment_slot_id !== $slot->id) {
                    $this->invalid('state');
                }

                return $existing;
            }
            $service = AppointmentService::whereKey($slot->appointment_service_id)->lockForUpdate()->firstOrFail();
            $slot = $service->slots()->whereKey($slot->id)->lockForUpdate()->firstOrFail();
            $duplicate = Appointment::where('user_id', $user->id)->where('appointment_slot_id', $slot->id)
                ->whereIn('status', ['pending', 'approved'])->lockForUpdate()->first();
            if ($duplicate) {
                return $duplicate;
            }
            $this->validateSlot($user, $service, $slot, null, true);
            $active = Appointment::where('user_id', $user->id)->whereIn('status', ['pending', 'approved'])->lockForUpdate()->get()
                ->filter(fn ($appointment) => ($appointment->scheduled_ends_at ?? $appointment->slot->ends_at)->gt(now()));
            if ($active->count() >= $service->maximum_active_bookings) {
                $this->invalid('maximum');
            }
            $appointment = Appointment::create([
                'reference' => 'APT-'.Str::upper(Str::random(20)), 'booking_key' => $data['booking_key'],
                'user_id' => $user->id, 'appointment_service_id' => $service->id,
                ...$this->snapshot($slot, $service), 'status' => 'pending',
                'purpose' => $data['purpose'], 'notes' => $data['notes'] ?? null,
            ]);
            $this->history($appointment, $user, 'submitted', null, null);
            $this->notices($appointment, 'submitted', true);

            return $appointment;
        }, 3);
    }

    public function review(Appointment $appointment, User $admin, string $decision, ?string $remarks): void
    {
        $this->admin($admin);
        abort_unless(in_array($decision, ['approved', 'rejected', 'completed', 'no_show'], true), 422);
        $this->locked($appointment, null, function (Appointment $appointment, User $user, AppointmentService $service) use ($admin, $decision, $remarks) {
            if ($appointment->status === $decision) {
                return;
            }
            $old = $appointment->status;
            if (in_array($decision, ['approved', 'rejected'], true)) {
                if ($old !== 'pending') {
                    $this->invalid('state');
                }
                if ($decision === 'approved') {
                    $this->validateSlot($user, $service, $appointment->slot, $appointment->id, false);
                }
                $appointment->fill(['reviewed_by' => $admin->id, 'reviewed_at' => now(), 'admin_remarks' => $remarks]);
            } elseif ($old !== 'approved' || ($appointment->scheduled_ends_at ?? $appointment->slot->ends_at)->gt(now())) {
                $this->invalid('state');
            }
            $appointment->fill(['status' => $decision, 'active_slot_id' => $decision === 'approved' ? $appointment->appointment_slot_id : null,
                'version' => $appointment->version + 1])->save();
            if ($decision !== 'approved') {
                $this->closeRequests($appointment, $admin);
            }
            $this->history($appointment, $admin, $decision, $old, $appointment->appointment_slot_id, $remarks);
            $this->notices($appointment, $decision);
        });
    }

    public function cancel(Appointment $appointment, User $actor, string $reason, bool $administrator = false): void
    {
        $administrator ? $this->admin($actor) : abort_unless($actor->id === $appointment->user_id, 403);
        $this->locked($appointment, null, function (Appointment $appointment, User $user, AppointmentService $service) use ($actor, $reason, $administrator) {
            if ($appointment->status === 'cancelled') {
                return;
            }
            if (! in_array($appointment->status, ['pending', 'approved'], true)) {
                $this->invalid('state');
            }
            $start = $appointment->scheduled_starts_at ?? $appointment->slot->starts_at;
            if (! $administrator && ($start->lte(now()) || $start->lt(now()->addHours($service->cancellation_notice_hours)))) {
                $this->invalid('cancellation_notice');
            }
            $old = $appointment->status;
            $appointment->update(['status' => 'cancelled', 'active_slot_id' => null, 'version' => $appointment->version + 1,
                'cancelled_by' => $actor->id, 'cancelled_at' => now(), 'cancellation_reason' => $reason]);
            $this->closeRequests($appointment, $actor);
            $this->history($appointment, $actor, 'cancelled', $old, $appointment->appointment_slot_id, $reason);
            $this->notices($appointment, 'cancelled', ! $administrator);
        });
    }

    public function requestReschedule(Appointment $appointment, AppointmentSlot $target, User $actor, string $reason, bool $proposal = false): void
    {
        $proposal ? $this->admin($actor) : abort_unless($actor->id === $appointment->user_id, 403);
        $this->locked($appointment, $target->id, function (Appointment $appointment, User $user, AppointmentService $service) use ($target, $actor, $reason, $proposal) {
            if (! in_array($appointment->status, $proposal ? ['pending', 'approved'] : ['approved'], true)
                || ($appointment->scheduled_starts_at ?? $appointment->slot->starts_at)->lte(now())) {
                $this->invalid('state');
            }
            if ($appointment->rescheduleRequests()->whereIn('status', ['pending', 'proposed'])->lockForUpdate()->first()) {
                $this->invalid('reschedule_pending');
            }
            $target = $service->slots()->whereKey($target->id)->firstOrFail();
            if ($target->id === $appointment->appointment_slot_id) {
                $this->invalid('same_slot');
            }
            $this->validateSlot($user, $service, $target, $appointment->id, true);
            $request = $appointment->rescheduleRequests()->create([
                'original_slot_id' => $appointment->appointment_slot_id, 'target_slot_id' => $target->id,
                'requested_by' => $actor->id, 'origin' => $proposal ? 'admin' : 'customer',
                'status' => $proposal ? 'proposed' : 'pending', 'reason' => $reason,
                'target_starts_at' => $target->starts_at, 'target_ends_at' => $target->ends_at, 'target_location' => $target->location,
            ]);
            $event = $proposal ? 'alternative_proposed' : 'reschedule_requested';
            $this->history($appointment, $actor, $event, $appointment->status, $appointment->appointment_slot_id, $reason);
            $this->notices($appointment, $event, ! $proposal, "request:$request->id:$event");
        });
    }

    public function respondToProposal(AppointmentRescheduleRequest $request, User $user, bool $accept): void
    {
        abort_unless($request->appointment->user_id === $user->id, 403);
        $this->locked($request->appointment, $request->target_slot_id, function (Appointment $appointment, User $user, AppointmentService $service) use ($request, $accept) {
            $request = AppointmentRescheduleRequest::whereKey($request->id)->lockForUpdate()->firstOrFail();
            if ($request->status !== 'proposed' || ! in_array($appointment->status, ['pending', 'approved'], true)) {
                $this->invalid('state');
            }
            if ($accept) {
                $this->validateSlot($user, $service, $request->targetSlot, $appointment->id, true);
            }
            $request->update(['status' => $accept ? 'pending' : 'rejected', 'reviewed_by' => $accept ? null : $user->id, 'reviewed_at' => $accept ? null : now()]);
            $event = $accept ? 'proposal_accepted' : 'proposal_declined';
            $this->history($appointment, $user, $event, $appointment->status, $appointment->appointment_slot_id);
            $this->notices($appointment, $event, true, "request:$request->id:$event");
        });
    }

    public function reviewReschedule(AppointmentRescheduleRequest $request, User $admin, bool $approve, ?string $remarks): void
    {
        $this->admin($admin);
        $this->locked($request->appointment, $request->target_slot_id, function (Appointment $appointment, User $user, AppointmentService $service) use ($request, $admin, $approve, $remarks) {
            $request = AppointmentRescheduleRequest::whereKey($request->id)->lockForUpdate()->firstOrFail();
            $decision = $approve ? 'approved' : 'rejected';
            if ($request->status === $decision) {
                return;
            }
            if ($request->status !== 'pending' || ! in_array($appointment->status, ['pending', 'approved'], true)
                || $request->original_slot_id !== $appointment->appointment_slot_id) {
                $this->invalid('state');
            }
            $oldSlot = $appointment->appointment_slot_id;
            $oldStatus = $appointment->status;
            $oldTimes = ['start' => $appointment->scheduled_starts_at ?? $appointment->slot->starts_at,
                'end' => $appointment->scheduled_ends_at ?? $appointment->slot->ends_at];
            if ($approve) {
                if (($appointment->scheduled_starts_at ?? $appointment->slot->starts_at)->lte(now())) {
                    $this->invalid('state');
                }
                $this->validateSlot($user, $service, $request->targetSlot, $appointment->id, false);
                $appointment->fill([...$this->snapshot($request->targetSlot, $service), 'status' => 'approved',
                    'reviewed_by' => $admin->id, 'reviewed_at' => now(), 'admin_remarks' => $remarks, 'version' => $appointment->version + 1])->save();
            }
            $request->update(['status' => $decision, 'reviewed_by' => $admin->id, 'reviewed_at' => now(), 'review_remarks' => $remarks]);
            $event = $approve ? 'reschedule_approved' : 'reschedule_rejected';
            $this->history($appointment, $admin, $event, $oldStatus, $oldSlot, $remarks, $oldTimes);
            $this->notices($appointment, $event, false, "request:$request->id:$event");
        });
    }

    private function locked(Appointment $appointment, ?int $targetSlotId, Closure $callback): void
    {
        DB::transaction(function () use ($appointment, $targetSlotId, $callback) {
            $user = User::whereKey($appointment->user_id)->lockForUpdate()->firstOrFail();
            $service = AppointmentService::whereKey($appointment->appointment_service_id)->lockForUpdate()->firstOrFail();
            // First snapshot read is after the owner/service locks. All booking writes
            // hold the same owner lock, so an earlier reschedule is visible here.
            $current = Appointment::whereKey($appointment->id)->firstOrFail();
            $ids = array_unique(array_filter([$current->appointment_slot_id, $targetSlotId]));
            $slots = $service->slots()->whereIn('id', $ids)->orderBy('id')->lockForUpdate()->get();
            if ($slots->count() !== count($ids)) {
                $this->invalid('same_slot');
            }
            $current = Appointment::whereKey($appointment->id)->lockForUpdate()->firstOrFail();
            $current->setRelation('slot', $slots->firstWhere('id', $current->appointment_slot_id));
            $callback($current, $user, $service);
        }, 3);
    }

    private function validateSlot(User $user, AppointmentService $service, AppointmentSlot $slot, ?int $excludeId, bool $notice): void
    {
        if (! $service->is_active || $slot->status !== 'available' || $slot->starts_at->lte(now())
            || $slot->appointment_service_id !== $service->id
            || $service->blockedDates()->whereBetween('date', [$slot->starts_at->toDateString(), $slot->ends_at->subMicrosecond()->toDateString()])->lockForUpdate()->first()) {
            $this->invalid('unavailable');
        }
        if ($notice && ($slot->starts_at->lt(now()->addHours($service->minimum_notice_hours)) || $slot->starts_at->gt(now()->addDays($service->maximum_advance_days)))) {
            $this->invalid('notice');
        }
        if (! Membership::where('user_id', $user->id)->where('status', 'active')->where('starts_at', '<=', now())
            ->where('expires_at', '>', now())->where('expires_at', '>', $slot->ends_at)->lockForUpdate()->first()) {
            $this->invalid('eligibility');
        }
        $approved = Appointment::where('appointment_slot_id', $slot->id)->where('status', 'approved')
            ->when($excludeId, fn ($query) => $query->where('id', '!=', $excludeId))->lockForUpdate()->get();
        if ($approved->count() >= $slot->capacity) {
            $this->invalid('full');
        }
        $others = Appointment::where('user_id', $user->id)->whereIn('status', ['pending', 'approved'])
            ->when($excludeId, fn ($query) => $query->where('id', '!=', $excludeId))->lockForUpdate()->get();
        foreach ($others as $other) {
            $start = $other->scheduled_starts_at ?? $other->slot->starts_at;
            $end = $other->scheduled_ends_at ?? $other->slot->ends_at;
            if ($start->lt($slot->ends_at) && $end->gt($slot->starts_at)) {
                $this->invalid('conflict');
            }
        }
    }

    /** @return array<string, mixed> */
    private function snapshot(AppointmentSlot $slot, AppointmentService $service): array
    {
        return ['appointment_slot_id' => $slot->id, 'active_slot_id' => $slot->id,
            'scheduled_starts_at' => $slot->starts_at, 'scheduled_ends_at' => $slot->ends_at,
            'service_name' => $service->name, 'location' => $slot->location];
    }

    /** @param array<string, mixed>|null $previousTimes */
    private function history(Appointment $appointment, User $actor, string $event, ?string $previousStatus, ?int $previousSlot, ?string $remarks = null, ?array $previousTimes = null): void
    {
        AppointmentStatusHistory::create(['appointment_id' => $appointment->id, 'event' => $event,
            'previous_status' => $previousStatus, 'new_status' => $appointment->status, 'changed_by' => $actor->id,
            'previous_slot_id' => $previousSlot, 'new_slot_id' => $appointment->appointment_slot_id, 'remarks' => $remarks,
            'previous_starts_at' => $previousSlot ? ($previousTimes['start'] ?? $appointment->scheduled_starts_at ?? $appointment->slot->starts_at) : null,
            'previous_ends_at' => $previousSlot ? ($previousTimes['end'] ?? $appointment->scheduled_ends_at ?? $appointment->slot->ends_at) : null,
            'new_starts_at' => $appointment->scheduled_starts_at ?? $appointment->slot->starts_at,
            'new_ends_at' => $appointment->scheduled_ends_at ?? $appointment->slot->ends_at]);
    }

    private function closeRequests(Appointment $appointment, User $actor): void
    {
        $appointment->rescheduleRequests()->whereIn('status', ['pending', 'proposed'])->update(['status' => 'cancelled', 'reviewed_at' => now(), 'reviewed_by' => $actor->id, 'review_remarks' => 'Closed because the appointment is no longer active.']);
    }

    private function notices(Appointment $appointment, string $kind, bool $admins = false, ?string $eventKey = null): void
    {
        app(AppointmentNotificationService::class)->queue($appointment, $kind, $eventKey ?? "$kind:$appointment->version", $admins);
    }

    private function admin(User $user): void
    {
        abort_unless($user->role === UserRole::Admin, 403);
    }

    private function invalid(string $key): never
    {
        throw ValidationException::withMessages(['appointment' => __('appointment.'.$key)]);
    }
}
