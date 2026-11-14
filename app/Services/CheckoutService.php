<?php

namespace App\Services;

use App\Models\Address;
use App\Models\Cart;
use App\Models\CommerceSetting;
use App\Models\Order;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class CheckoutService
{
    public function __construct(private InventoryService $inventory) {}

    public function create(User $user, string $key, ?int $addressId, ?string $notes): Order
    {
        return DB::transaction(function () use ($user, $key, $addressId, $notes) {
            $user = User::whereKey($user->id)->lockForUpdate()->firstOrFail();
            $existing = $user->orders()->where('checkout_key', $key)->lockForUpdate()->first();
            if ($existing) {
                return $existing;
            }
            $cart = Cart::where('user_id', $user->id)->lockForUpdate()->first();
            $items = $cart?->items()->orderBy('product_id')->orderBy('product_variant_id')->lockForUpdate()->get();
            if (! $cart || ! $items || $items->isEmpty()) {
                throw ValidationException::withMessages(['cart' => __('shop.empty')]);
            }
            $settings = CommerceSetting::current();
            $lines = [];
            $subtotal = 0;
            $requiresShipping = false;
            foreach ($items as $item) {
                $selection = $this->inventory->selection($item->product_id, $item->product_variant_id, true);
                $this->inventory->assertQuantity($item->quantity, $selection['available']);
                $product = $selection['product'];
                $variant = $selection['variant'];
                $plan = $selection['plan'];
                $requiresShipping = $requiresShipping || $plan === null;
                if ($plan) {
                    if ($user->orders()->whereIn('payment_status', ['pending', 'failed'])->where('expires_at', '>', now())
                        ->whereHas('items', fn ($query) => $query->where('type', 'membership'))->exists()) {
                        throw ValidationException::withMessages(['cart' => 'Complete or cancel your existing pending membership order before purchasing another.']);
                    }
                    if (! filled($user->phone)) {
                        throw ValidationException::withMessages(['cart' => 'Add a Malaysian phone number to your profile before purchasing membership.']);
                    }
                    if ($user->memberships()->where('membership_plan_id', $plan->id)->where('status', 'revoked')->exists()) {
                        throw ValidationException::withMessages(['cart' => 'Please contact support about your revoked membership.']);
                    }
                }
                $lineTotal = $selection['unit_price_sen'] * $item->quantity;
                $subtotal += $lineTotal;
                $lines[] = [
                    'product_id' => $product->id, 'product_variant_id' => $variant?->id,
                    'product_name' => $product->name, 'variant_name' => $variant?->name,
                    'sku' => $variant->sku ?? $product->sku, 'type' => $plan ? 'membership' : 'physical',
                    'membership_plan_id' => $plan?->id, 'membership_months' => $plan?->duration_months,
                    'quantity' => $item->quantity, 'unit_price_sen' => $selection['unit_price_sen'], 'line_total_sen' => $lineTotal,
                ];
            }
            $address = $requiresShipping ? Address::where('user_id', $user->id)->whereKey($addressId)->first() : null;
            if ($requiresShipping && ! $address) {
                throw ValidationException::withMessages(['address_id' => __('shop.address_required')]);
            }
            if ($requiresShipping && ! $settings->shipping_enabled) {
                throw ValidationException::withMessages(['cart' => __('shop.shipping_disabled')]);
            }
            $shipping = $requiresShipping ? $settings->shipping_fee_sen : 0;
            $order = $user->orders()->create([
                'reference' => 'YC-'.now()->format('Ymd').'-'.Str::upper(Str::random(12)),
                'checkout_key' => $key, 'customer_name' => $user->name, 'customer_email' => $user->email,
                'customer_phone' => $address->phone ?? $user->phone,
                'shipping_address' => $address?->only(['full_name', 'phone', 'line1', 'line2', 'city', 'state', 'postcode', 'country_code']),
                'subtotal_sen' => $subtotal, 'shipping_fee_sen' => $shipping,
                'total_sen' => $subtotal + $shipping, 'notes' => $notes,
                'payment_status' => 'pending', 'fulfillment_status' => $requiresShipping ? 'new' : null,
                'expires_at' => now()->addMinutes($settings->reservation_minutes),
            ]);
            foreach ($lines as $line) {
                $orderItem = $order->items()->create($line);
                if ($line['type'] !== 'physical') {
                    continue;
                }
                $order->reservations()->create([
                    'order_item_id' => $orderItem->id, 'product_id' => $line['product_id'],
                    'product_variant_id' => $line['product_variant_id'],
                    'quantity' => $line['quantity'], 'expires_at' => $order->expires_at,
                ]);
            }
            $cart->items()->delete();

            return $order;
        }, 3);
    }

    public function cancel(Order $order, string $reason = 'customer_cancelled'): void
    {
        DB::transaction(function () use ($order, $reason) {
            $order = Order::whereKey($order->id)->lockForUpdate()->firstOrFail();
            if (! in_array($order->payment_status, ['pending', 'failed'], true)) {
                throw ValidationException::withMessages(['order' => __('shop.cannot_cancel')]);
            }
            $order->reservations()->where('status', 'active')->update(['status' => 'released']);
            $order->update(['payment_status' => 'cancelled', 'fulfillment_status' => 'cancelled', 'cancellation_reason' => $reason]);
        }, 3);
    }

    public function expire(): int
    {
        $count = 0;
        Order::whereIn('payment_status', ['pending', 'failed'])->where('expires_at', '<=', now())->select('id')
            ->chunkById(100, function ($orders) use (&$count) {
                foreach ($orders as $order) {
                    try {
                        $this->cancel($order, 'reservation_expired');
                        $count++;
                    } catch (ValidationException) {
                        // A simultaneous payment or cancellation may have changed the order.
                    }
                }
            });

        return $count;
    }
}
