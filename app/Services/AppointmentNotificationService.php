<?php

namespace App\Services;

use App\Enums\UserRole;
use App\Models\Appointment;
use App\Models\AppointmentNoticeDelivery;
use App\Models\User;
use App\Notifications\AppointmentNotice;

class AppointmentNotificationService
{
    public function queue(Appointment $appointment, string $kind, string $eventKey, bool $admins = false): void
    {
        $this->recipient($appointment, $appointment->user, $kind, $eventKey, false);
        if ($admins) {
            User::where('role', UserRole::Admin)->where('id', '!=', $appointment->user_id)->chunkById(100, function ($users) use ($appointment, $kind, $eventKey) {
                foreach ($users as $user) {
                    $this->recipient($appointment, $user, $kind, $eventKey, true);
                }
            });
        }
    }

    private function recipient(Appointment $appointment, User $user, string $kind, string $eventKey, bool $admin): void
    {
        $delivery = AppointmentNoticeDelivery::firstOrCreate(['appointment_id' => $appointment->id, 'user_id' => $user->id, 'event_key' => $eventKey], ['queued_at' => now()]);
        if ($delivery->wasRecentlyCreated) {
            $start = $appointment->scheduled_starts_at ?? $appointment->slot->starts_at;
            $user->notify(new AppointmentNotice($kind, $appointment->reference, $appointment->service_name ?? $appointment->service->name,
                $start->format('d M Y H:i'), $appointment->status, $delivery->id, $appointment->id,
                ($admin ? '/admin/appointments/' : '/appointments/').$appointment->id, $start->toIso8601String()));
        }
    }
}
