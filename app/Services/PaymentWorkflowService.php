<?php

namespace App\Services;

use App\Exceptions\GatewayException;
use App\Models\Order;
use App\Models\Payment;
use App\Models\PaymentCallback;
use App\Models\PaymentGatewaySetting;
use App\Support\PublicHttpsUrl;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class PaymentWorkflowService
{
    public function __construct(private ToyyibPayService $gateway) {}

    public function start(Order $order): Payment
    {
        $settings = PaymentGatewaySetting::current();
        if (! $settings->enabled || ! $settings->configured()) {
            throw ValidationException::withMessages(['payment' => 'Payments are not configured yet. No payment has been taken.']);
        }
        if (! app()->runningUnitTests() && ! PublicHttpsUrl::configured(config('app.url'))) {
            throw ValidationException::withMessages(['payment' => 'A publicly reachable HTTPS application URL is required for ToyyibPay callbacks.']);
        }
        $payment = DB::transaction(function () use ($order, $settings) {
            $order = Order::whereKey($order->id)->lockForUpdate()->firstOrFail();
            if (! in_array($order->payment_status, ['pending', 'failed'], true) || $order->expires_at->lte(now())) {
                throw ValidationException::withMessages(['payment' => 'This order can no longer accept a new payment.']);
            }
            $existing = $order->payments()->whereIn('status', ['creating', 'creation_unknown', 'pending'])->latest('id')->first();
            if ($existing) {
                if ($existing->status === 'pending' && $existing->bill_code) {
                    return $existing;
                }
                throw ValidationException::withMessages(['payment' => 'A payment bill is being prepared or needs review. Please do not submit another payment.']);
            }
            if ($order->total_sen < 100) {
                throw ValidationException::withMessages(['payment' => 'ToyyibPay requires an order amount of at least RM 1.00.']);
            }

            return $order->payments()->create([
                'reference' => 'PAY'.Str::upper(Str::random(24)), 'environment' => $settings->environment,
                'secret_key_snapshot' => $settings->getAttribute($settings->environment.'_secret_key'),
                'amount_sen' => $order->total_sen, 'status' => 'creating',
            ]);
        }, 3);
        if ($payment->bill_code) {
            return $payment;
        }
        try {
            $code = $this->gateway->createBill($order, $payment, $settings->getAttribute($payment->environment.'_category_code'));
        } catch (GatewayException $error) {
            $payment->update(['status' => $error->ambiguous ? 'creation_unknown' : 'failed', 'requires_review' => $error->ambiguous, 'review_reason' => $error->ambiguous ? 'bill_creation_unconfirmed' : null]);
            PaymentCallback::create(['payment_id' => $payment->id, 'event' => 'bill_creation', 'outcome' => $payment->status]);
            throw ValidationException::withMessages(['payment' => $error->getMessage()]);
        }
        DB::transaction(function () use ($payment, $order, $code) {
            $lockedOrder = Order::whereKey($order->id)->lockForUpdate()->firstOrFail();
            $payment->update(['bill_code' => $code, 'status' => $lockedOrder->expires_at->gt(now()) && in_array($lockedOrder->payment_status, ['pending', 'failed'], true) ? 'pending' : 'expired']);
        });
        PaymentCallback::create(['payment_id' => $payment->id, 'event' => 'bill_creation', 'outcome' => $payment->status]);
        if ($payment->status !== 'pending') {
            throw ValidationException::withMessages(['payment' => 'The order expired before its payment bill was ready.']);
        }

        return $payment;
    }
}
