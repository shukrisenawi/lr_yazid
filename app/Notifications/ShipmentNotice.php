<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class ShipmentNotice extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public int $historyId, public int $orderId, public string $kind, public string $reference, public string $courier, public string $tracking)
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
        return (new MailMessage)->subject(__('documents.'.$this->kind))->line($this->message())
            ->action(__('documents.order_action'), rtrim(config('app.url'), '/').'/orders/'.$this->orderId);
    }

    /** @return array<string, string> */
    public function toArray(object $notifiable): array
    {
        return ['title' => __('documents.'.$this->kind), 'message' => $this->message(), 'url' => '/orders/'.$this->orderId];
    }

    private function message(): string
    {
        return __('documents.shipment_message', ['reference' => $this->reference, 'courier' => $this->courier, 'tracking' => $this->tracking]);
    }
}
