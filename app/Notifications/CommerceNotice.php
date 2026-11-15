<?php

namespace App\Notifications;

use App\Models\CommerceNoticeDelivery;
use App\Models\Order;
use App\Models\Payment;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\DB;

class CommerceNotice extends Notification implements ShouldQueue
{
    use Queueable;

    public bool $deleteWhenMissingModels = true;

    public function __construct(public int $deliveryId, string $notificationId)
    {
        $this->id = $notificationId;
        // Queue inserts commit or roll back with the outbox/business transaction.
        $this->onConnection('database')->onQueue('commerce-notices')->beforeCommit();
    }

    /** @return list<string> */
    public function via(object $notifiable): array
    {
        return ['database', 'mail'];
    }

    public function shouldSend(object $notifiable, string $channel): bool
    {
        if (! $notifiable instanceof User || ! in_array($channel, ['database', 'mail'], true)) {
            return false;
        }
        $delivery = CommerceNoticeDelivery::whereKey($this->deliveryId)->where('user_id', $notifiable->id)->first();
        if (! $delivery || $delivery->notification_id !== $this->id
            || $delivery->getAttribute($channel.'_sent_at') !== null || $delivery->getAttribute($channel.'_suppressed_at') !== null) {
            return false;
        }
        if ($channel === 'database') {
            $sentAt = DB::table('notifications')->where('id', $this->id)
                ->where('notifiable_type', $notifiable->getMorphClass())->where('notifiable_id', $notifiable->id)->value('created_at');
            if ($sentAt !== null) {
                $delivery->update(['database_sent_at' => $sentAt]);

                return false;
            }
        }
        $reason = $this->suppressionReason($delivery, $notifiable, $channel);
        if ($reason !== null) {
            $delivery->update([$channel.'_suppressed_at' => now(), $channel.'_suppression_reason' => $reason]);

            return false;
        }

        return true;
    }

    private function suppressionReason(CommerceNoticeDelivery $delivery, User $user, string $channel): ?string
    {
        if ($channel === 'mail' || $delivery->kind === 'welcome') {
            $current = User::find($user->id);
            if (! $current || ! $current->hasVerifiedEmail() || ! $user->hasVerifiedEmail()) {
                return 'email_unverified';
            }
            if ($user->email !== $delivery->snapshot['recipient_email'] || $current->email !== $delivery->snapshot['recipient_email']) {
                return 'email_changed';
            }
        }
        if ($delivery->order_id === null) {
            return null;
        }
        $order = Order::whereKey($delivery->order_id)->where('user_id', $user->id)->first();
        if (! $order) {
            return 'order_unavailable';
        }
        if ($delivery->kind === 'payment_success') {
            $valid = $order->payment_status === 'paid' && ! $order->payments()->where('requires_review', true)->exists()
                && $order->payments()->whereKey($delivery->payment_id)->where('status', 'paid')->where('requires_review', false)->exists();

            return $valid ? null : 'payment_state_changed';
        }
        if ($delivery->kind === 'payment_failed') {
            $payment = Payment::whereKey($delivery->payment_id)->where('order_id', $order->id)->first();
            if ($order->payment_status !== 'failed' || ! $payment || $payment->status !== 'failed' || $payment->requires_review) {
                return 'payment_state_changed';
            }
            if ($order->payments()->where('id', '>', $payment->id)->exists()) {
                return 'newer_payment_attempt';
            }
        }

        return null;
    }

    public function toMail(object $notifiable): MailMessage
    {
        $snapshot = CommerceNoticeDelivery::findOrFail($this->deliveryId)->snapshot;

        return (new MailMessage)->subject($snapshot['title'])->line($snapshot['message'])
            ->action(__('commerce_notices.action'), rtrim(config('app.url'), '/').$snapshot['url']);
    }

    /** @return array<string, string> */
    public function toArray(object $notifiable): array
    {
        $snapshot = CommerceNoticeDelivery::findOrFail($this->deliveryId)->snapshot;

        return ['title' => $snapshot['title'], 'message' => $snapshot['message'], 'url' => $snapshot['url']];
    }
}
