<?php

namespace App\Services;

use App\Enums\UserRole;
use App\Models\Invoice;
use App\Models\InvoiceSetting;
use App\Models\Order;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class InvoiceService
{
    public function issue(Order $order, User $admin): Invoice
    {
        abort_unless($admin->role === UserRole::Admin, 403);

        return DB::transaction(function () use ($order, $admin) {
            User::whereKey($order->user_id)->lockForUpdate()->firstOrFail();
            $order = Order::whereKey($order->id)->lockForUpdate()->firstOrFail();
            $existing = Invoice::where('order_id', $order->id)->lockForUpdate()->first();
            if ($existing) {
                return $existing;
            }
            $payments = $order->payments()->orderBy('id')->lockForUpdate()->get();
            $payment = $payments->first(fn ($payment) => $payment->status === 'paid' && ! $payment->requires_review
                && $payment->amount_sen === $order->total_sen && $payment->paid_at !== null);
            if ($order->payment_status !== 'paid' || ! $payment || $payments->contains('requires_review', true)) {
                throw ValidationException::withMessages(['invoice' => __('documents.unpaid')]);
            }
            $items = $order->items()->orderBy('id')->get(['product_name', 'variant_name', 'sku', 'type', 'quantity', 'unit_price_sen', 'line_total_sen']);
            if ($order->currency !== 'MYR' || $items->isEmpty() || $items->sum('line_total_sen') !== $order->subtotal_sen
                || $order->subtotal_sen + $order->shipping_fee_sen !== $order->total_sen
                || $items->contains(fn ($item) => $item->quantity * $item->unit_price_sen !== $item->line_total_sen)) {
                throw ValidationException::withMessages(['invoice' => __('documents.totals')]);
            }
            $seller = InvoiceSetting::whereKey(1)->lockForUpdate()->first();
            if (! $seller || ! $seller->enabled || ! filled($seller->seller_name) || ! filled($seller->seller_address) || ! filled($seller->seller_email)) {
                throw ValidationException::withMessages(['invoice' => __('documents.seller_required')]);
            }

            return Invoice::create(['order_id' => $order->id, 'issued_by' => $admin->id,
                'reference' => 'INV-'.str_pad((string) $order->id, 10, '0', STR_PAD_LEFT), 'issued_at' => now(),
                'snapshot' => [
                    'seller' => $seller->only(['seller_name', 'seller_address', 'seller_email', 'registration_number', 'footer_note']),
                    'buyer' => $order->only(['customer_name', 'customer_email', 'customer_phone', 'shipping_address']),
                    'order_reference' => $order->reference, 'currency' => $order->currency,
                    'subtotal_sen' => $order->subtotal_sen, 'shipping_fee_sen' => $order->shipping_fee_sen, 'total_sen' => $order->total_sen,
                    'payment_reference' => $payment->reference, 'paid_at' => $payment->paid_at->toIso8601String(),
                    'items' => $items->toArray(),
                ]]);
        }, 3);
    }
}
