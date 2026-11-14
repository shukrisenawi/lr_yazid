<?php

namespace App\Services;

use App\Models\Cart;
use App\Models\CartItem;
use App\Models\CommerceSetting;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;

class CartService
{
    public function __construct(private InventoryService $inventory) {}

    public function cart(Request $request, bool $create = true): ?Cart
    {
        if ($request->user()) {
            return $create
                ? Cart::firstOrCreate(['user_id' => $request->user()->id])
                : Cart::where('user_id', $request->user()->id)->first();
        }
        $token = $request->session()->get('guest_cart_token');
        if (! is_string($token)) {
            if (! $create) {
                return null;
            }
            $token = Str::random(64);
            $request->session()->put('guest_cart_token', $token);
        }
        $hash = hash('sha256', $token);

        return $create ? Cart::firstOrCreate(['guest_token_hash' => $hash]) : Cart::where('guest_token_hash', $hash)->first();
    }

    public function count(Request $request): int
    {
        return (int) ($this->cart($request, false)?->items()->sum('quantity') ?? 0);
    }

    public function add(Request $request, int $productId, ?int $variantId, int $quantity): void
    {
        $cart = $this->cart($request);
        DB::transaction(function () use ($cart, $productId, $variantId, $quantity) {
            $cart = Cart::whereKey($cart->id)->lockForUpdate()->firstOrFail();
            $selection = $this->inventory->selection($productId, $variantId, true);
            $key = $productId.':'.($variantId ?? 0);
            $item = $cart->items()->where('item_key', $key)->first();
            if (! $item && $cart->items()->count() >= 50) {
                throw ValidationException::withMessages(['cart' => __('shop.limit')]);
            }
            $newQuantity = $selection['plan'] && $quantity === 1 ? 1 : ($item->quantity ?? 0) + $quantity;
            $this->inventory->assertQuantity($newQuantity, $selection['available']);
            $cart->items()->updateOrCreate(['item_key' => $key], [
                'product_id' => $productId, 'product_variant_id' => $variantId, 'quantity' => $newQuantity,
            ]);
        }, 3);
    }

    public function update(Request $request, CartItem $item, ?int $quantity): void
    {
        $cart = $this->cart($request, false);
        abort_unless($cart && $item->cart_id === $cart->id, 404);
        DB::transaction(function () use ($cart, $item, $quantity) {
            Cart::whereKey($cart->id)->lockForUpdate()->firstOrFail();
            $item = CartItem::whereKey($item->id)->firstOrFail();
            if ($quantity === null) {
                $item->delete();

                return;
            }
            $selection = $this->inventory->selection($item->product_id, $item->product_variant_id, true);
            $this->inventory->assertQuantity($quantity, $selection['available']);
            $item->update(['quantity' => $quantity]);
        }, 3);
    }

    public function clear(Request $request): void
    {
        $cart = $this->cart($request, false);
        if ($cart) {
            DB::transaction(function () use ($cart) {
                Cart::whereKey($cart->id)->lockForUpdate()->firstOrFail();
                $cart->items()->delete();
            });
        }
    }

    /** @return array<string, mixed> */
    public function summary(Request $request): array
    {
        $cart = $this->cart($request, false);
        $lines = [];
        $subtotal = 0;
        $valid = true;
        foreach ($cart?->items()->with(['product.images', 'variant.image'])->orderBy('id')->get() ?? [] as $item) {
            $error = null;
            $price = $item->variant->price_sen ?? $item->product->current_price_sen;
            $available = 0;
            try {
                $selection = $this->inventory->selection($item->product_id, $item->product_variant_id);
                $available = $selection['available'];
                $price = $selection['unit_price_sen'];
                $this->inventory->assertQuantity($item->quantity, $available);
            } catch (ValidationException $exception) {
                $error = collect($exception->errors())->flatten()->first();
                $valid = false;
            }
            $total = $price * $item->quantity;
            $subtotal += $total;
            $lines[] = [
                'id' => $item->id, 'product_id' => $item->product_id, 'product_variant_id' => $item->product_variant_id,
                'type' => $item->product->type,
                'name' => $item->product->name, 'slug' => $item->product->slug,
                'variant_name' => $item->variant?->name, 'sku' => $item->variant->sku ?? $item->product->sku,
                'image_url' => $item->variant->image->url ?? $item->product->images->first()?->url,
                'quantity' => $item->quantity, 'available' => $available, 'unit_price_sen' => $price,
                'line_total_sen' => $total, 'error' => $error,
            ];
        }
        $settings = CommerceSetting::current();
        $requiresShipping = collect($lines)->contains('type', 'physical');
        $shipping = $requiresShipping ? $settings->shipping_fee_sen : 0;

        return [
            'items' => $lines, 'subtotal_sen' => $subtotal, 'shipping_fee_sen' => $shipping,
            'total_sen' => $subtotal + $shipping, 'valid' => $valid && count($lines) > 0 && (! $requiresShipping || $settings->shipping_enabled),
            'requires_shipping' => $requiresShipping,
            'shipping_enabled' => $settings->shipping_enabled,
        ];
    }

    public function mergeGuest(Request $request, User $user): void
    {
        $token = $request->session()->get('guest_cart_token');
        if (! is_string($token)) {
            return;
        }
        $guest = Cart::where('guest_token_hash', hash('sha256', $token))->first();
        if (! $guest) {
            return;
        }
        $target = Cart::firstOrCreate(['user_id' => $user->id]);
        DB::transaction(function () use ($guest, $target) {
            Cart::whereIn('id', [$guest->id, $target->id])->orderBy('id')->lockForUpdate()->get();
            foreach ($guest->items()->orderBy('product_id')->orderBy('product_variant_id')->get() as $item) {
                try {
                    $selection = $this->inventory->selection($item->product_id, $item->product_variant_id, true);
                } catch (ValidationException) {
                    continue;
                }
                $existing = $target->items()->where('item_key', $item->item_key)->first();
                if (! $existing && $target->items()->count() >= 50) {
                    continue;
                }
                $quantity = $selection['plan'] ? 1 : min(99, ($existing->quantity ?? 0) + $item->quantity, $selection['available']);
                if ($quantity > 0) {
                    $target->items()->updateOrCreate(['item_key' => $item->item_key], [
                        'product_id' => $item->product_id, 'product_variant_id' => $item->product_variant_id, 'quantity' => $quantity,
                    ]);
                }
            }
            $guest->delete();
        }, 3);
        $request->session()->forget('guest_cart_token');
        Inertia::flash('toast', ['type' => 'success', 'message' => 'Guest cart merged. Quantities were limited to available stock; unavailable items were skipped.']);
    }
}
