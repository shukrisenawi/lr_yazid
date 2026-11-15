<?php

namespace App\Services;

use App\Models\CommerceNoticeDelivery;
use App\Models\Order;
use App\Models\Payment;
use App\Models\User;
use App\Notifications\CommerceNotice;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use LogicException;

class CommerceNotificationService
{
    public function welcome(User $user): void
    {
        DB::transaction(function () use ($user) {
            $user = User::whereKey($user->id)->lockForUpdate()->firstOrFail();
            if ($user->hasVerifiedEmail()) {
                $this->queue($user, 'welcome', 'welcome', null, null);
            }
        }, 3);
    }

    public function orderPlaced(Order $order): void
    {
        $this->queue($order->user, 'order_placed', 'order:'.$order->id.':placed', $order, null);
    }

    public function payment(Order $order, Payment $payment, string $kind): void
    {
        if ($payment->order_id !== $order->id || ! in_array($kind, ['payment_success', 'payment_failed'], true)
            || $payment->amount_sen !== $order->total_sen || $order->currency !== 'MYR'
            || $payment->requires_review || $payment->checked_at === null
            || ($kind === 'payment_success' && ($order->payment_status !== 'paid' || $payment->status !== 'paid' || $payment->paid_at === null))
            || ($kind === 'payment_failed' && ($order->payment_status !== 'failed' || $payment->status !== 'failed'))) {
            throw new LogicException('Commerce payment notices require a matching verified outcome.');
        }
        $key = $kind === 'payment_success' ? 'order:'.$order->id.':paid' : 'payment:'.$payment->id.':failed';
        $this->queue($order->user, $kind, $key, $order, $payment);
    }

    private function queue(User $user, string $kind, string $key, ?Order $order, ?Payment $payment): void
    {
        if (DB::transactionLevel() === 0 || config('queue.connections.database.driver') !== 'database'
            || (config('queue.connections.database.connection') !== null
                && config('queue.connections.database.connection') !== DB::getDefaultConnection())) {
            throw new LogicException('Commerce notices require a database queue on the business database transaction.');
        }
        $amount = $order ? intdiv($order->total_sen, 100).'.'.sprintf('%02d', $order->total_sen % 100) : '';
        $message = __('commerce_notices.'.$kind.'_body', ['reference' => $order->reference ?? '', 'amount' => $amount]);
        if ($payment?->environment === 'sandbox') {
            $message .= ' '.__('commerce_notices.sandbox');
        }
        $delivery = CommerceNoticeDelivery::firstOrCreate(['user_id' => $user->id, 'event_key' => $key], [
            'order_id' => $order?->id, 'payment_id' => $payment?->id, 'kind' => $kind,
            'notification_id' => (string) Str::uuid(), 'queued_at' => now(),
            'snapshot' => ['title' => __('commerce_notices.'.$kind.'_title'), 'message' => $message,
                'url' => $order ? '/orders/'.$order->id : '/dashboard', 'recipient_email' => $user->email],
        ]);
        if ($delivery->wasRecentlyCreated) {
            $user->notify(new CommerceNotice($delivery->id, $delivery->notification_id));
        }
    }
}
