<?php

namespace App\Services;

use App\Models\MembershipPlan;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\StockReservation;
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
}
