<?php

namespace App\Services;

use App\Exceptions\GatewayException;
use App\Models\Order;
use App\Models\Payment;
use App\Models\PaymentCallback;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class PaymentVerificationService
{
    public function __construct(private ToyyibPayService $gateway) {}

    /** @param array<string, mixed> $data */
    public function callback(array $data): string
    {
        $payment = Payment::where('reference', $data['order_id'])->where('bill_code', $data['billcode'])->first();
        $safe = Arr::only($data, ['order_id', 'billcode', 'refno', 'status', 'amount']);
        if (! $payment || ! hash_equals(md5($payment->secret_key_snapshot.$data['status'].$data['order_id'].$data['refno'].'ok'), strtolower($data['hash']))) {
            PaymentCallback::create(['payment_id' => $payment?->id, 'event' => 'callback', 'outcome' => 'invalid_signature_or_reference', 'payload' => $safe]);
            throw new AuthorizationException('The payment callback could not be authenticated.');
        }
        try {
            if ($this->gateway->toSen($data['amount']) !== $payment->amount_sen) {
                throw new GatewayException('Payment amount mismatch.');
            }
            if ($payment->status === 'paid' && $payment->transaction_reference === $data['refno']) {
                $status = 'paid';
            } else {
                $status = $this->reconcile($payment, $data['refno']);
            }
            PaymentCallback::create(['payment_id' => $payment->id, 'event' => 'callback', 'outcome' => $status, 'payload' => $safe]);

            return $status;
        } catch (GatewayException $error) {
            PaymentCallback::create(['payment_id' => $payment->id, 'event' => 'callback', 'outcome' => 'verification_rejected', 'payload' => $safe]);
            throw ValidationException::withMessages(['payment' => $error->getMessage()]);
        }
    }

    public function reconcile(Payment $payment, ?string $expectedInvoice = null): string
    {
        if (! $payment->bill_code) {
            throw new GatewayException('A bill code is required for reconciliation.');
        }
        $transactions = $this->gateway->transactions($payment);
        $selected = null;
        foreach ($transactions as $transaction) {
            if (($transaction['billExternalReferenceNo'] ?? null) !== $payment->reference
                || ($expectedInvoice !== null && ($transaction['billpaymentInvoiceNo'] ?? null) !== $expectedInvoice)) {
                continue;
            }
            if ($this->gateway->toSen($transaction['billpaymentAmount'] ?? null) !== $payment->amount_sen) {
                throw new GatewayException('The verified transaction amount does not match this order.');
            }
            $invoice = $transaction['billpaymentInvoiceNo'] ?? null;
            if (! is_string($invoice) || $invoice === '' || strlen($invoice) > 100) {
                throw new GatewayException('The verified transaction reference is invalid.');
            }
            if ($selected === null || (string) ($transaction['billpaymentStatus'] ?? '') === '1') {
                $selected = $transaction;
            }
            if ((string) ($transaction['billpaymentStatus'] ?? '') === '1') {
                break;
            }
        }
        $payment->update(['checked_at' => now()]);
        if ($selected === null) {
            PaymentCallback::create(['payment_id' => $payment->id, 'event' => 'reconciliation', 'outcome' => 'no_matching_transaction']);

            return 'pending';
        }
        $status = (string) ($selected['billpaymentStatus'] ?? '');
        $result = match ($status) {
            '1' => $this->settle($payment, $selected['billpaymentInvoiceNo'], $this->gateway->paymentTime($selected['billPaymentDate'] ?? null)),
            '3' => $this->fail($payment),
            default => 'pending',
        };
        PaymentCallback::create(['payment_id' => $payment->id, 'event' => 'reconciliation', 'outcome' => $result, 'payload' => Arr::only($selected, ['billExternalReferenceNo', 'billpaymentInvoiceNo', 'billpaymentAmount', 'billpaymentStatus'])]);

        return $result;
    }

    private function settle(Payment $payment, string $invoice, CarbonImmutable $paidAt): string
    {
        return DB::transaction(function () use ($payment, $invoice, $paidAt) {
            $userId = Order::whereKey($payment->order_id)->value('user_id');
            User::whereKey($userId)->lockForUpdate()->firstOrFail();
            $order = Order::whereKey($payment->order_id)->lockForUpdate()->firstOrFail();
            $payment = Payment::whereKey($payment->id)->lockForUpdate()->firstOrFail();
            if ($payment->status === 'paid') {
                if ($payment->transaction_reference !== $invoice) {
                    $payment->update(['requires_review' => true, 'review_reason' => 'additional_bill_payment']);

                    return 'requires_review';
                }

                return 'paid';
            }
            if (Payment::where('environment', $payment->environment)->where('transaction_reference', $invoice)->where('id', '!=', $payment->id)->exists()) {
                throw new GatewayException('This transaction reference has already been processed.');
            }
            $reason = null;
            if ($order->payment_status === 'paid') {
                $reason = 'additional_order_payment';
            } elseif (! in_array($order->payment_status, ['pending', 'failed'], true) || $order->expires_at->lte(now())) {
                $reason = 'payment_after_reservation_ended';
            }
            $references = $order->reservations()->orderBy('product_id')->orderBy('product_variant_id')->get();
            $resources = [];
            // Follow the same product -> variant -> reservation lock order as checkout.
            foreach ($references as $reservation) {
                $product = Product::whereKey($reservation->product_id)->lockForUpdate()->firstOrFail();
                $resource = $reservation->product_variant_id
                    ? ProductVariant::whereKey($reservation->product_variant_id)->lockForUpdate()->firstOrFail()
                    : $product;
                $resources[$reservation->id] = $resource;
            }
            $reservations = $order->reservations()->orderBy('product_id')->orderBy('product_variant_id')->lockForUpdate()->get();
            $inventory = [];
            foreach ($reservations as $reservation) {
                $resource = $resources[$reservation->id];
                if ($reservation->status !== 'active' || $reservation->expires_at->lte(now()) || $resource->stock < $reservation->quantity) {
                    $reason ??= 'stock_reservation_unavailable';
                }
                $inventory[] = [$resource, $reservation];
            }
            $hasPhysical = $order->items()->where('type', 'physical')->exists();
            if ($hasPhysical && $reservations->isEmpty()) {
                $reason ??= 'stock_reservation_missing';
            }
            if (! app(MembershipService::class)->canActivate($order)) {
                $reason ??= 'membership_revoked';
            }
            if ($reason === null) {
                foreach ($inventory as [$resource, $reservation]) {
                    $resource->decrement('stock', $reservation->quantity);
                    $reservation->update(['status' => 'consumed']);
                }
            } elseif ($reason !== 'additional_order_payment') {
                $order->reservations()->where('status', 'active')->update(['status' => 'released']);
            }
            $payment->update(['status' => 'paid', 'transaction_reference' => $invoice, 'paid_at' => $paidAt, 'requires_review' => $reason !== null, 'review_reason' => $reason]);
            if ($reason === null) {
                app(MembershipService::class)->activateVerified($order, $payment);
            }
            if ($order->payment_status !== 'paid') {
                $order->update(['payment_status' => 'paid', 'fulfillment_status' => $reason === null ? ($hasPhysical ? 'new' : null) : 'cancelled']);
            }
            if ($reason === null) {
                app(CommerceNotificationService::class)->payment($order, $payment, 'payment_success');
            }

            return $reason === null ? 'paid' : 'requires_review';
        }, 3);
    }

    private function fail(Payment $payment): string
    {
        return DB::transaction(function () use ($payment) {
            User::whereKey(Order::whereKey($payment->order_id)->value('user_id'))->lockForUpdate()->firstOrFail();
            $order = Order::whereKey($payment->order_id)->lockForUpdate()->firstOrFail();
            $payment = Payment::whereKey($payment->id)->lockForUpdate()->firstOrFail();
            if ($payment->status === 'paid' || $order->payment_status === 'paid') {
                return 'paid';
            }
            $failedNow = $payment->status !== 'failed';
            $payment->update(['status' => 'failed']);
            if ($order->payment_status === 'pending') {
                $order->update(['payment_status' => 'failed']);
            }
            if ($failedNow && $order->payment_status === 'failed' && ! $payment->requires_review) {
                app(CommerceNotificationService::class)->payment($order, $payment, 'payment_failed');
            }

            return 'failed';
        });
    }
}
