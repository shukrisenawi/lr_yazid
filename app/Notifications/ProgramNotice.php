<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class ProgramNotice extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public string $kind, public string $title, public string $date, public int $deliveryId, public string $url = '/my-programs')
    {
        $this->onConnection('database')->beforeCommit();
    }

    /** @return list<string> */
    public function via(object $notifiable): array
    {
        return ['database', 'mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)->subject(__('program.'.$this->kind.'_title'))
            ->line(__('program.'.$this->kind.'_message', ['title' => $this->title, 'date' => $this->date]))
            ->action(__('program.action'), rtrim(config('app.url'), '/').$this->url);
    }

    /** @return array<string, string> */
    public function toArray(object $notifiable): array
    {
        return [
            'title' => __('program.'.$this->kind.'_title'),
            'message' => __('program.'.$this->kind.'_message', ['title' => $this->title, 'date' => $this->date]),
            'url' => $this->url,
        ];
    }
}
