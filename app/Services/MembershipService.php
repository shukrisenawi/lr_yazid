<?php

namespace App\Services;

use App\Models\Membership;
use App\Models\MembershipTransaction;
use App\Models\Order;
use App\Models\Payment;
use App\Models\User;
use App\Notifications\MembershipNotice;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use LogicException;

class MembershipService
{
    public function canActivate(Order $order): bool
    {
        $plans = $order->items()->where('type', 'membership')->pluck('membership_plan_id');

        return Membership::withTrashed()->where('user_id', $order->user_id)->whereIn('membership_plan_id', $plans)->where('status', 'revoked')->lockForUpdate()->first() === null;
    }

    public function activateVerified(Order $order, Payment $payment): void
    {
        if ($payment->status !== 'paid' || $payment->requires_review || $payment->order_id !== $order->id) {
            throw new LogicException('Membership activation requires a verified, settled payment.');
        }
        DB::transaction(function () use ($order, $payment) {
            $user = User::whereKey($order->user_id)->lockForUpdate()->firstOrFail();
            $paidAt = CarbonImmutable::parse($payment->paid_at);
            foreach ($order->items()->where('type', 'membership')->orderBy('id')->get() as $item) {
                if (MembershipTransaction::where('order_item_id', $item->id)->exists()) {
                    continue;
                }
                $membership = Membership::withTrashed()->where('user_id', $order->user_id)->where('membership_plan_id', $item->membership_plan_id)->lockForUpdate()->first();
                $previous = $membership?->expires_at;
                $stillValid = $membership && $membership->status === 'active' && $membership->expires_at->gt($paidAt);
                $start = $stillValid ? $membership->starts_at : $paidAt;
                $expiry = ($stillValid ? $membership->expires_at : $paidAt)->addMonthsNoOverflow($item->membership_months);
                $type = $membership ? 'renewal' : 'activation';
                $membership ??= new Membership(['user_id' => $order->user_id, 'membership_plan_id' => $item->membership_plan_id]);
                $membership->fill(['deleted_at' => null, 'status' => 'active', 'starts_at' => $start, 'expires_at' => $expiry])->save();
                MembershipTransaction::create([
                    'user_id' => $order->user_id, 'membership_id' => $membership->id,
                    'payment_id' => $payment->id, 'order_item_id' => $item->id, 'type' => $type,
                    'amount_sen' => $item->line_total_sen, 'previous_expires_at' => $previous,
                    'starts_at' => $start, 'expires_at' => $expiry, 'paid_at' => $paidAt,
                ]);
                $user->notify(new MembershipNotice($type, $membership->plan->name, $expiry->format('d M Y H:i')));
            }
        });
    }
}
