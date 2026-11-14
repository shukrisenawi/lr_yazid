<script setup lang="ts">
import { router, useForm } from '@inertiajs/vue3';
import { ref, watch } from 'vue';
const props = withDefaults(
    defineProps<{
        productId: number;
        variantId?: number | null;
        available: number;
        requiresVariant?: boolean;
        compact?: boolean;
        actionLabel?: string;
        checkoutAfterAdd?: boolean;
    }>(),
    {
        variantId: null,
        requiresVariant: false,
        compact: false,
        actionLabel: 'Add to cart',
        checkoutAfterAdd: false,
    },
);
const form = useForm({
    product_id: props.productId,
    product_variant_id: props.variantId,
    quantity: 1,
});
const added = ref(false);
watch(
    () => props.variantId,
    (value) => {
        form.product_variant_id = value;
        added.value = false;
        form.clearErrors();
    },
);
function submit(buyNow = false) {
    added.value = false;
    form.post('/cart/items', {
        preserveScroll: true,
        onSuccess: () => {
            added.value = true;
            if (buyNow || props.checkoutAfterAdd) router.visit('/checkout');
        },
    });
}
</script>
<template>
    <form class="mt-5 space-y-3" @submit.prevent="submit()">
        <label v-if="!compact" class="grid max-w-28 gap-2 text-sm"
            >Quantity<input
                v-model="form.quantity"
                type="number"
                min="1"
                :max="Math.min(99, Math.max(1, available))"
                required
                class="rounded-md border bg-transparent p-2.5"
        /></label>
        <p
            v-if="Object.keys(form.errors).length"
            role="alert"
            class="text-sm text-red-700 dark:text-red-400"
        >
            {{ Object.values(form.errors).join(' ') }}
        </p>
        <p
            v-if="added"
            role="status"
            class="text-sm text-emerald-800 dark:text-emerald-400"
        >
            Added to your cart.
        </p>
        <div class="flex flex-wrap gap-3">
            <button
                type="submit"
                :disabled="
                    form.processing ||
                    available < 1 ||
                    (requiresVariant && !variantId)
                "
                class="rounded-md bg-emerald-800 px-5 py-2.5 text-sm font-medium text-white transition-colors hover:bg-emerald-900 disabled:cursor-not-allowed disabled:opacity-50"
            >
                {{
                    form.processing
                        ? 'Adding…'
                        : available < 1
                          ? 'Out of stock'
                          : actionLabel
                }}
            </button>
            <button
                v-if="!compact"
                type="button"
                :disabled="
                    form.processing ||
                    available < 1 ||
                    (requiresVariant && !variantId)
                "
                class="rounded-md border px-5 py-2.5 text-sm font-medium disabled:opacity-50"
                @click="submit(true)"
            >
                Buy now
            </button>
        </div>
        <p
            v-if="requiresVariant && !variantId"
            class="text-xs text-muted-foreground"
        >
            Choose an option before adding this product.
        </p>
    </form>
</template>
