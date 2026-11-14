<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class MembershipNotice extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public string $kind, public string $planName, public string $expiry, public ?int $reminderId = null)
    {
        // Database queue inserts share the business transaction; workers cannot
        // see these jobs until that transaction commits.
        $this->onConnection('database')->beforeCommit();
    }

    /** @return list<string> */
    public function via(object $notifiable): array
    {
        return ['database', 'mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)->subject(__('membership.'.$this->kind))
            ->line(__('membership.expiry', ['plan' => $this->planName, 'date' => $this->expiry]))
            ->action(__('membership.action'), rtrim(config('app.url'), '/').'/membership');
    }

    /** @return array<string, string> */
    public function toArray(object $notifiable): array
    {
        return ['title' => __('membership.'.$this->kind), 'message' => __('membership.expiry', ['plan' => $this->planName, 'date' => $this->expiry]), 'url' => '/membership'];
    }
}
