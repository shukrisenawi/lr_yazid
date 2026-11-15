<?php

namespace App\Services;

use App\Enums\UserRole;
use App\Models\ManualRefund;
use App\Models\Order;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class ManualRefundService
{
    /** @param array<string, mixed> $input */
    public function record(Order $order, User $admin, array $input): ManualRefund
    {
        abort_unless($admin->role === UserRole::Admin, 403);
        $data = Validator::make($input, [
            'idempotency_key' => ['required', 'uuid'],
            'payment_id' => ['required', 'integer'],
            'amount' => ['required', 'string', 'regex:/\A(?:0|[1-9][0-9]{0,6})(?:\.[0-9]{1,2})?\z/'],
            'external_reference' => ['required', 'string', 'max:100', 'regex:/\A[A-Za-z0-9][A-Za-z0-9._:\/-]{2,99}\z/'],
            'method' => ['required', 'in:bank_transfer,cash,other'],
            'refunded_at' => ['required', 'date_format:Y-m-d\TH:i'],
            'reason' => ['required', 'string', 'min:5', 'max:1000'],
            'confirmed' => ['accepted'],
        ])->validate();
        [$whole, $fraction] = array_pad(explode('.', $data['amount'], 2), 2, '');
        $amount = (int) $whole * 100 + (int) str_pad($fraction, 2, '0');
        if ($amount < 1) {
            throw ValidationException::withMessages(['amount' => __('refunds.positive')]);
        }
        $refundedAt = CarbonImmutable::createFromFormat('!Y-m-d\TH:i', $data['refunded_at'], config('app.timezone'));
        if (! $refundedAt instanceof CarbonImmutable) {
            throw ValidationException::withMessages(['refunded_at' => __('refunds.date')]);
        }
        $attributes = ['order_id' => $order->id, 'payment_id' => (int) $data['payment_id'], 'recorded_by' => $admin->id,
            'idempotency_key' => strtolower($data['idempotency_key']), 'amount_sen' => $amount, 'currency' => 'MYR',
            'external_reference' => strtoupper($data['external_reference']), 'method' => $data['method'],
            'refunded_at' => $refundedAt, 'reason' => trim($data['reason'])];
        if (mb_strlen($attributes['reason']) < 5) {
            throw ValidationException::withMessages(['reason' => __('refunds.reason')]);
        }
        try {
            return DB::transaction(function () use ($order, $attributes, $refundedAt) {
                User::whereKey(Order::whereKey($order->id)->value('user_id'))->lockForUpdate()->firstOrFail();
                $order = Order::whereKey($order->id)->lockForUpdate()->firstOrFail();
                $payment = $order->payments()->whereKey($attributes['payment_id'])->lockForUpdate()->first();
                $existing = ManualRefund::where('idempotency_key', $attributes['idempotency_key'])->lockForUpdate()->first();
                if ($existing) {
                    foreach ($attributes as $key => $value) {
                        $same = $key === 'refunded_at' ? $existing->refunded_at->eq($refundedAt) : $existing->getAttribute($key) === $value;
                        if (! $same) {
                            throw ValidationException::withMessages(['refund' => __('refunds.key_conflict')]);
                        }
                    }

                    return $existing;
                }
                if (! $payment || $order->currency !== 'MYR' || $order->payment_status !== 'paid'
                    || $payment->status !== 'paid' || $payment->paid_at === null || $payment->checked_at === null
                    || ! filled($payment->transaction_reference) || $payment->amount_sen !== $order->total_sen
                    || ! in_array($payment->environment, ['sandbox', 'production'], true)) {
                    throw ValidationException::withMessages(['payment_id' => __('refunds.unverified')]);
                }
                if ($refundedAt->gt(now()) || $refundedAt->lt($payment->paid_at->startOfMinute())) {
                    throw ValidationException::withMessages(['refunded_at' => __('refunds.date')]);
                }
                // Locking reads avoid stale snapshots under MySQL REPEATABLE READ.
                $recorded = 0;
                foreach (ManualRefund::where('payment_id', $payment->id)->lockForUpdate()->get(['amount_sen']) as $refund) {
                    $recorded += $refund->amount_sen;
                }
                if ($attributes['amount_sen'] > $payment->amount_sen - $recorded) {
                    throw ValidationException::withMessages(['amount' => __('refunds.excess')]);
                }

                return ManualRefund::create([...$attributes, 'environment' => $payment->environment, 'reference' => 'RFD-'.Str::ulid()]);
            }, 3);
        } catch (UniqueConstraintViolationException) {
            throw ValidationException::withMessages(['refund' => __('refunds.duplicate')]);
        }
    }
}
