<?php

namespace App\Notifications;

use App\Models\Appointment;
use Carbon\CarbonImmutable;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class AppointmentNotice extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public string $kind, public string $reference, public string $serviceName, public string $date,
        public string $status, public int $deliveryId, public int $appointmentId, public string $url, public string $startsAt,
    ) {
        // The database queue shares this transaction; workers see jobs only after commit.
        $this->onConnection('database')->beforeCommit();
    }

    /** @return list<string> */
    public function via(object $notifiable): array
    {
        return ['database', 'mail'];
    }

    public function shouldSend(object $notifiable, string $channel): bool
    {
        return ! str_starts_with($this->kind, 'reminder_') || Appointment::whereKey($this->appointmentId)->where('status', 'approved')
            ->where('scheduled_starts_at', CarbonImmutable::parse($this->startsAt)->setTimezone('Asia/Kuala_Lumpur'))
            ->where('scheduled_starts_at', '>', now())->exists();
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)->subject(__('appointment.'.$this->kind.'_title'))
            ->line($this->message())->action(__('appointment.notice_action'), rtrim(config('app.url'), '/').$this->url);
    }

    /** @return array<string, string> */
    public function toArray(object $notifiable): array
    {
        return ['title' => __('appointment.'.$this->kind.'_title'), 'message' => $this->message(), 'url' => $this->url];
    }

    private function message(): string
    {
        return __('appointment.notice_body', ['reference' => $this->reference, 'service' => $this->serviceName,
            'date' => $this->date, 'status' => ucfirst($this->status)]);
    }
}
