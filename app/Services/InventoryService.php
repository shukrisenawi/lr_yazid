<?php

namespace App\Services;

use App\Models\CartItem;
use App\Models\MembershipPlan;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\StockReservation;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class InventoryService
{
    public function held(int $productId, ?int $variantId = null, bool $lock = false): int
    {
        $query = StockReservation::where('product_id', $productId)
            ->where('product_variant_id', $variantId)->where('status', 'active')
            ->where('expires_at', '>', now());

        // A locking read is essential under MySQL REPEATABLE READ: a plain SUM
        // may retain a snapshot from before another checkout acquired the product lock.
        if (! $lock) {
            return (int) $query->sum('quantity');
        }
        $total = 0;
        foreach ($query->lockForUpdate()->get(['quantity']) as $reservation) {
            $total += $reservation->quantity;
        }

        return $total;
    }

    /** @return array{product: Product, variant: ProductVariant|null, unit_price_sen: int, available: int, plan: MembershipPlan|null} */
    public function selection(int $productId, ?int $variantId, bool $lock = false): array
    {
        $query = Product::withCount('variants')->whereKey($productId);
        if ($lock) {
            $query->lockForUpdate();
        }
        $product = $query->first();
        if (! $product || $product->status !== 'active') {
            throw ValidationException::withMessages(['cart' => __('shop.unavailable')]);
        }
        if ($product->type === 'membership') {
            $planQuery = MembershipPlan::where('product_id', $product->id)->where('is_active', true);
            if ($lock) {
                $planQuery->lockForUpdate();
            }
            $plan = $planQuery->first();
            if (! $plan || $variantId !== null) {
                throw ValidationException::withMessages(['cart' => __('shop.unavailable')]);
            }

            return ['product' => $product, 'variant' => null, 'plan' => $plan, 'unit_price_sen' => $plan->price_sen, 'available' => 1];
        }
        $variant = null;
        if ($variantId !== null) {
            $query = $product->variants()->whereKey($variantId)->where('is_active', true);
            if ($lock) {
                $query->lockForUpdate();
            }
            $variant = $query->first();
            if (! $variant) {
                throw ValidationException::withMessages(['cart' => __('shop.unavailable')]);
            }
        } elseif ($product->variants_count > 0) {
            throw ValidationException::withMessages(['cart' => __('shop.variant_required')]);
        }

        return [
            'product' => $product, 'variant' => $variant, 'plan' => null,
            'unit_price_sen' => $variant->price_sen ?? $product->current_price_sen,
            'available' => max(0, ($variant->stock ?? $product->stock) - $this->held($product->id, $variant?->id, $lock)),
        ];
    }

    public function assertQuantity(int $quantity, int $available): void
    {
        if ($quantity < 1 || $quantity > 99 || $quantity > $available) {
            throw ValidationException::withMessages(['quantity' => __('shop.stock')]);
        }
    }

    /**
     * Uncached display hints only. Mutating selection()/held(..., true) above
     * retain their existing locking reads and remain authoritative at checkout.
     *
     * @param  Collection<int, CartItem>  $items  Eager-load product (with variants_count) and variant.
     * @return array<int, array{unit_price_sen: int, available: int, error: string|null}>
     */
    public function forCartDisplay(Collection $items): array
    {
        $membershipIds = $items->filter(fn (CartItem $item) => $item->product->type === 'membership')->pluck('product_id')->unique()->all();
        $plans = $membershipIds === [] ? collect() : MembershipPlan::whereIn('product_id', $membershipIds)->where('is_active', true)->get()->keyBy('product_id');
        $physical = $items->filter(fn (CartItem $item) => $item->product->type === 'physical')
            ->unique(fn (CartItem $item) => $item->product_id.':'.($item->product_variant_id ?? 0));
        $held = [];
        if ($physical->isNotEmpty()) {
            $rows = DB::table('stock_reservations')->where('status', 'active')->where('expires_at', '>', now())
                ->where(function ($query) use ($physical) {
                    foreach ($physical as $item) {
                        $query->orWhere(fn ($pair) => $pair->where('product_id', $item->product_id)->where('product_variant_id', $item->product_variant_id));
                    }
                })->selectRaw('product_id, product_variant_id, sum(quantity) as held_quantity')->groupBy('product_id', 'product_variant_id')->get();
            foreach ($rows as $row) {
                $held[$row->product_id.':'.($row->product_variant_id ?? 0)] = (int) $row->held_quantity;
            }
        }
        $result = [];
        foreach ($items as $item) {
            $product = $item->product;
            $variant = $item->variant;
            $price = $variant->price_sen ?? $product->current_price_sen;
            $available = 0;
            $error = null;
            try {
                if ($product->status !== 'active') {
                    throw ValidationException::withMessages(['cart' => __('shop.unavailable')]);
                }
                if ($product->type === 'membership') {
                    $plan = $plans->get($product->id);
                    if (! $plan || $item->product_variant_id !== null) {
                        throw ValidationException::withMessages(['cart' => __('shop.unavailable')]);
                    }
                    $price = $plan->price_sen;
                    $available = 1;
                } else {
                    if ($item->product_variant_id !== null && (! $variant || $variant->product_id !== $product->id || ! $variant->is_active)) {
                        throw ValidationException::withMessages(['cart' => __('shop.unavailable')]);
                    }
                    if ($item->product_variant_id === null && $product->variants_count > 0) {
                        throw ValidationException::withMessages(['cart' => __('shop.variant_required')]);
                    }
                    $price = $variant->price_sen ?? $product->current_price_sen;
                    $available = max(0, ($variant->stock ?? $product->stock) - ($held[$product->id.':'.($item->product_variant_id ?? 0)] ?? 0));
                }
                $this->assertQuantity($item->quantity, $available);
            } catch (ValidationException $exception) {
                $error = collect($exception->errors())->flatten()->first();
            }
            $result[$item->id] = ['unit_price_sen' => $price, 'available' => $available, 'error' => $error];
        }

        return $result;
    }
}
