<?php

namespace App\Services;

use App\Models\AppointmentBlockedDate;
use App\Models\AppointmentRescheduleRequest;
use App\Models\AppointmentService;
use App\Models\AppointmentSlot;
use App\Models\AppointmentStatusHistory;
use Carbon\CarbonImmutable;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class AppointmentSchedulingService
{
    /** @param array<string, mixed> $data */
    public function saveSlot(AppointmentService $service, ?AppointmentSlot $slot, array $data, int $adminId): AppointmentSlot
    {
        return DB::transaction(function () use ($service, $slot, $data, $adminId) {
            $service = AppointmentService::whereKey($service->id)->lockForUpdate()->firstOrFail();
            $slot = $slot ? $service->slots()->whereKey($slot->id)->lockForUpdate()->firstOrFail() : new AppointmentSlot(['appointment_service_id' => $service->id, 'created_by' => $adminId]);
            foreach (['starts_at', 'ends_at'] as $field) {
                $data[$field] = CarbonImmutable::parse($data[$field], 'Asia/Kuala_Lumpur')->setTimezone('Asia/Kuala_Lumpur')->toDateTimeString();
            }
            $slot->fill($data);
            $minutes = $slot->starts_at->diffInMinutes($slot->ends_at, false);
            if ($slot->starts_at->lte(now()) || $minutes < 5 || $minutes > 480) {
                throw ValidationException::withMessages(['ends_at' => 'Slots must be in the future and last between 5 and 480 minutes.']);
            }
            if (! $service->is_active && (! $slot->exists || $slot->status === 'available')) {
                throw ValidationException::withMessages(['slot' => __('appointment.inactive')]);
            }
            if ($slot->exists && $slot->isDirty(['starts_at', 'ends_at', 'capacity', 'location', 'status'])
                && ($slot->appointments()->whereIn('status', ['pending', 'approved'])->lockForUpdate()->first()
                    || AppointmentRescheduleRequest::where('target_slot_id', $slot->id)->whereIn('status', ['pending', 'proposed'])->lockForUpdate()->first())) {
                throw ValidationException::withMessages(['slot' => __('appointment.booked')]);
            }
            if ($slot->status === 'available' && $service->blockedDates()->whereBetween('date', [
                $slot->starts_at->toDateString(), $slot->ends_at->subMicrosecond()->toDateString(),
            ])->lockForUpdate()->first()) {
                throw ValidationException::withMessages(['starts_at' => __('appointment.blocked')]);
            }
            $overlap = $service->slots()->when($slot->exists, fn ($query) => $query->where('id', '!=', $slot->id))
                ->where('starts_at', '<', $slot->ends_at)->where('ends_at', '>', $slot->starts_at)->lockForUpdate()->first();
            if ($overlap) {
                throw ValidationException::withMessages(['starts_at' => __('appointment.overlap')]);
            }
            $slot->save();

            return $slot;
        }, 3);
    }

    public function deleteSlot(AppointmentService $service, AppointmentSlot $slot): void
    {
        DB::transaction(function () use ($service, $slot) {
            AppointmentService::whereKey($service->id)->lockForUpdate()->firstOrFail();
            $slot = $service->slots()->whereKey($slot->id)->lockForUpdate()->firstOrFail();
            if ($slot->appointments()->lockForUpdate()->first()
                || AppointmentRescheduleRequest::where('original_slot_id', $slot->id)->orWhere('target_slot_id', $slot->id)->lockForUpdate()->first()
                || AppointmentStatusHistory::where('previous_slot_id', $slot->id)->orWhere('new_slot_id', $slot->id)->lockForUpdate()->first()) {
                throw ValidationException::withMessages(['slot' => __('appointment.used')]);
            }
            $slot->delete();
        }, 3);
    }

    public function blockDate(AppointmentService $service, string $date, string $reason, int $adminId): void
    {
        DB::transaction(function () use ($service, $date, $reason, $adminId) {
            AppointmentService::whereKey($service->id)->lockForUpdate()->firstOrFail();
            $from = CarbonImmutable::parse($date, 'Asia/Kuala_Lumpur')->startOfDay();
            $slots = $service->slots()->where('starts_at', '<', $from->addDay())->where('ends_at', '>', $from)->lockForUpdate()->get();
            foreach ($slots as $slot) {
                if ($slot->appointments()->where('status', 'approved')->lockForUpdate()->first()) {
                    throw ValidationException::withMessages(['date' => __('appointment.blocked_approved')]);
                }
            }
            $service->blockedDates()->firstOrCreate(['date' => $date], ['reason' => $reason, 'created_by' => $adminId]);
            $service->slots()->whereIn('id', $slots->pluck('id'))->update(['status' => 'disabled']);
        }, 3);
    }

    public function unblockDate(AppointmentService $service, AppointmentBlockedDate $blockedDate): void
    {
        DB::transaction(function () use ($service, $blockedDate) {
            AppointmentService::whereKey($service->id)->lockForUpdate()->firstOrFail();
            $service->blockedDates()->whereKey($blockedDate->id)->firstOrFail()->delete();
        }, 3);
    }

    /** @param array<string, mixed> $data
     * @return array<string, mixed>
     */
    public function generate(AppointmentService $service, array $data, int $adminId): array
    {
        $from = CarbonImmutable::parse($data['from'], 'Asia/Kuala_Lumpur')->startOfDay();
        $to = CarbonImmutable::parse($data['to'], 'Asia/Kuala_Lumpur')->startOfDay();
        if ($from->diffInDays($to, false) < 0 || $from->diffInDays($to) > 92) {
            throw ValidationException::withMessages(['to' => __('appointment.range')]);
        }

        return DB::transaction(function () use ($service, $data, $adminId, $from, $to) {
            $service = AppointmentService::whereKey($service->id)->lockForUpdate()->firstOrFail();
            if (! $service->is_active) {
                throw ValidationException::withMessages(['generator' => __('appointment.inactive')]);
            }
            $blocked = $service->blockedDates()->whereBetween('date', [$from->toDateString(), $to->toDateString()])->pluck('date')->all();
            $existing = $service->slots()->where('starts_at', '<', $to->addDay())->where('ends_at', '>', $from)->orderBy('starts_at')->lockForUpdate()->get();
            $cursor = 0;
            $slots = [];
            $summary = ['created' => 0, 'overlaps' => 0, 'breaks' => 0, 'blocked_days' => 0, 'past' => 0, 'preview' => (bool) $data['preview']];
            $weekdays = array_map(intval(...), $data['weekdays']);
            for ($day = $from; $day->lte($to); $day = $day->addDay()) {
                if (! in_array($day->isoWeekday(), $weekdays, true)) {
                    continue;
                }
                if (in_array($day->toDateString(), $blocked, true)) {
                    $summary['blocked_days']++;

                    continue;
                }
                $endOfWork = $day->setTimeFromTimeString($data['day_end']);
                $breakStart = filled($data['break_start'] ?? null) ? $day->setTimeFromTimeString($data['break_start']) : null;
                $breakEnd = $breakStart ? $day->setTimeFromTimeString($data['break_end']) : null;
                for ($start = $day->setTimeFromTimeString($data['day_start']); $start->addMinutes($data['duration_minutes'])->lte($endOfWork); $start = $start->addMinutes($data['duration_minutes'])) {
                    $end = $start->addMinutes($data['duration_minutes']);
                    if ($start->lte(now())) {
                        $summary['past']++;

                        continue;
                    }
                    if ($breakStart && $start->lt($breakEnd) && $end->gt($breakStart)) {
                        $summary['breaks']++;

                        continue;
                    }
                    while ($cursor < $existing->count() && $existing[$cursor]->ends_at->lte($start)) {
                        $cursor++;
                    }
                    if ($cursor < $existing->count() && $start->lt($existing[$cursor]->ends_at) && $end->gt($existing[$cursor]->starts_at)) {
                        $summary['overlaps']++;

                        continue;
                    }
                    $slots[] = ['starts_at' => $start->toDateTimeString(), 'ends_at' => $end->toDateTimeString()];
                    if (count($slots) > 5000) {
                        throw ValidationException::withMessages(['to' => __('appointment.limit')]);
                    }
                }
            }
            $summary['candidates'] = count($slots);
            $summary['slots'] = array_map(fn ($slot) => [
                'starts_at' => CarbonImmutable::parse($slot['starts_at'], 'Asia/Kuala_Lumpur')->toIso8601String(),
                'ends_at' => CarbonImmutable::parse($slot['ends_at'], 'Asia/Kuala_Lumpur')->toIso8601String(),
            ], array_slice($slots, 0, 100));
            if (! $data['preview']) {
                $schedule = $service->schedules()->create([
                    'created_by' => $adminId, 'configuration' => Arr::except($data, ['preview']),
                    'created_count' => count($slots), 'skipped_count' => $summary['overlaps'] + $summary['breaks'] + $summary['past'],
                ]);
                $timestamp = now()->toDateTimeString();
                foreach (array_chunk($slots, 200) as $batch) {
                    AppointmentSlot::insert(array_map(fn ($slot) => [
                        ...$slot, 'appointment_service_id' => $service->id, 'appointment_schedule_id' => $schedule->id,
                        'created_by' => $adminId, 'capacity' => $data['capacity'], 'location' => $data['location'],
                        'status' => 'available', 'created_at' => $timestamp, 'updated_at' => $timestamp,
                    ], $batch));
                }
                $summary['created'] = count($slots);
            }

            return $summary;
        }, 3);
    }
}
