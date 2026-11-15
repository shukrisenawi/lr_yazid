<?php

namespace App\Console\Commands;

use App\Models\Appointment;
use App\Models\AppointmentService;
use App\Models\User;
use App\Services\AppointmentNotificationService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class SendAppointmentReminders extends Command
{
    protected $signature = 'appointments:send-reminders';

    protected $description = 'Queue deduplicated 24-hour and 1-hour reminders for approved appointments';

    public function handle(AppointmentNotificationService $notices): int
    {
        Appointment::where('status', 'approved')->where('scheduled_starts_at', '>', now())
            ->where('scheduled_starts_at', '<=', now()->addDay())->select(['id', 'user_id', 'appointment_service_id'])
            ->chunkById(100, function ($appointments) use ($notices) {
                foreach ($appointments as $record) {
                    DB::transaction(function () use ($record, $notices) {
                        User::whereKey($record->user_id)->lockForUpdate()->firstOrFail();
                        AppointmentService::whereKey($record->appointment_service_id)->lockForUpdate()->firstOrFail();
                        $appointment = Appointment::whereKey($record->id)->lockForUpdate()->firstOrFail();
                        $start = $appointment->scheduled_starts_at;
                        if ($appointment->status !== 'approved' || ! $start || $start->lte(now()) || $start->gt(now()->addDay())) {
                            return;
                        }
                        $kind = $start->lte(now()->addHour()) ? 'reminder_1' : 'reminder_24';
                        $notices->queue($appointment, $kind, $kind.':'.$start->getTimestamp());
                    }, 3);
                }
            });
        $this->info('Appointment reminder queue checked.');

        return self::SUCCESS;
    }
}
