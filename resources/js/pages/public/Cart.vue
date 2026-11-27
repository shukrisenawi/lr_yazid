<script setup lang="ts">
import { Head, Link, useForm } from '@inertiajs/vue3';
import { computed } from 'vue';
import CartLine from '@/components/CartLine.vue';
import OrderSummary from '@/components/OrderSummary.vue';
import type { CartSummary } from '@/types/commerce';
const props = defineProps<{ cart: CartSummary }>();
const groups = computed(() =>
    [
        {
            title: 'Product',
            description: 'Physical products · Shipping required',
            items: props.cart.items.filter((line) => line.type === 'physical'),
        },
        {
            title: 'Non-product',
            description: 'No shipping required',
            items: props.cart.items.filter((line) => line.type !== 'physical'),
        },
    ].filter((group) => group.items.length > 0),
);
const form = useForm({});
function clear() {
    if (confirm('Remove all items from your cart?'))
        form.delete('/cart', { preserveScroll: true });
}
</script>
<template>
    <Head title="Shopping Cart" />
    <div class="mx-auto max-w-7xl px-6 py-12">
        <h1 class="text-4xl font-semibold tracking-tight">Your cart</h1>
        <div
            v-if="cart.items.length"
            class="mt-8 grid items-start gap-10 lg:grid-cols-[1.5fr_1fr]"
        >
            <section aria-label="Cart items">
                <section
                    v-for="group in groups"
                    :key="group.title"
                    :aria-label="group.title"
                    class="mb-8"
                >
                    <h2 class="text-xl font-semibold">{{ group.title }}</h2>
                    <p class="mt-1 mb-4 text-sm text-muted-foreground">
                        {{ group.description }}
                    </p>
                    <CartLine
                        v-for="line in group.items"
                        :key="line.id"
                        :line="line"
                    />
                </section>
                <div class="mt-6 flex flex-wrap justify-between gap-4 text-sm">
                    <Link href="/products" class="underline"
                        >Continue shopping</Link
                    ><button
                        :disabled="form.processing"
                        class="underline"
                        @click="clear"
                    >
                        Clear cart
                    </button>
                </div>
            </section>
            <aside class="rounded-xl border p-6">
                <h2 class="mb-6 text-xl font-semibold">Order summary</h2>
                <OrderSummary
                    :subtotal="cart.subtotal_sen"
                    :shipping="cart.shipping_fee_sen"
                    :total="cart.total_sen"
                />
                <p
                    v-if="cart.requires_shipping && !cart.shipping_enabled"
                    role="alert"
                    class="mt-4 text-sm text-red-700 dark:text-red-400"
                >
                    Shipping is temporarily unavailable.
                </p>
                <Link
                    v-if="cart.valid"
                    href="/checkout"
                    class="mt-6 block rounded-md bg-emerald-800 px-5 py-3 text-center font-medium text-white"
                    >Proceed to checkout</Link
                >
                <p v-else class="mt-5 text-sm text-muted-foreground">
                    Please resolve unavailable items before checking out.
                </p>
                <p class="mt-4 text-xs leading-relaxed text-muted-foreground">
                    Prices and stock are checked again at checkout. Adding items
                    to your cart does not reserve stock.
                </p>
            </aside>
        </div>
        <section
            v-else
            class="mt-8 rounded-xl border border-dashed px-6 py-16 text-center"
        >
            <h2 class="text-xl font-medium">Your cart is empty</h2>
            <p class="mt-3 text-muted-foreground">
                Explore the collection and find something for your everyday.
            </p>
            <Link
                href="/products"
                class="mt-6 inline-block rounded-md bg-emerald-800 px-5 py-3 text-white"
                >Continue shopping</Link
            >
        </section>
    </div>
</template>
