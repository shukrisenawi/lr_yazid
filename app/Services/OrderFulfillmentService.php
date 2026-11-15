<?php

namespace App\Services;

use App\Enums\UserRole;
use App\Models\Order;
use App\Models\User;
use App\Notifications\ShipmentNotice;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class OrderFulfillmentService
{
    /** @param array<string, mixed> $data */
    public function update(Order $order, User $admin, array $data): void
    {
        abort_unless($admin->role === UserRole::Admin, 403);
        DB::transaction(function () use ($order, $admin, $data) {
            User::whereKey($order->user_id)->lockForUpdate()->firstOrFail();
            $order = Order::whereKey($order->id)->lockForUpdate()->firstOrFail();
            $payments = $order->payments()->lockForUpdate()->get();
            if ($order->payment_status !== 'paid' || ! $payments->contains(fn ($payment) => $payment->status === 'paid' && ! $payment->requires_review)
                || $payments->contains('requires_review', true) || ! $order->items()->where('type', 'physical')->exists()) {
                throw ValidationException::withMessages(['fulfillment' => __('fulfillment.ineligible')]);
            }
            $states = ['new', 'processing', 'packed', 'shipped', 'delivered'];
            $old = array_search($order->fulfillment_status, $states, true);
            $next = array_search($data['fulfillment_status'], $states, true);
            if ($old === false || $next === false || ($next !== $old && $next !== $old + 1)) {
                throw ValidationException::withMessages(['fulfillment_status' => __('fulfillment.transition')]);
            }
            $previousStatus = $order->fulfillment_status;
            $previousTracking = $order->only(['courier_name', 'tracking_number']);
            $order->fill(['fulfillment_status' => $data['fulfillment_status'], 'courier_name' => $data['courier_name'] ?? null, 'tracking_number' => $data['tracking_number'] ?? null]);
            if (! $order->isDirty()) {
                return;
            }
            if ($previousStatus === 'delivered') {
                throw ValidationException::withMessages(['fulfillment_status' => __('fulfillment.terminal')]);
            }
            if ($next >= 3 && (! filled($order->courier_name) || ! filled($order->tracking_number))) {
                throw ValidationException::withMessages(['tracking_number' => __('fulfillment.tracking_required')]);
            }
            $order->save();
            $history = $order->fulfillmentHistory()->create(['admin_id' => $admin->id, 'previous_status' => $previousStatus,
                'new_status' => $order->fulfillment_status, 'previous_tracking' => $previousTracking,
                'new_tracking' => $order->only(['courier_name', 'tracking_number']), 'reason' => $data['reason']]);
            $kind = match ($order->fulfillment_status) {
                'shipped' => $previousStatus === 'shipped' ? 'tracking_updated' : 'shipped',
                'delivered' => 'delivered',
                default => null,
            };
            if ($kind !== null) {
                $order->user->notify(new ShipmentNotice($history->id, $order->id, $kind, $order->reference, $order->courier_name, $order->tracking_number));
            }
        }, 3);
    }
}
