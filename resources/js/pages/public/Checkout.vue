<script setup lang="ts">
import { Head, Link, useForm } from '@inertiajs/vue3';
import OrderSummary from '@/components/OrderSummary.vue';
import { money } from '@/types/catalog';
import type { Address, CartSummary } from '@/types/commerce';
const props = defineProps<{
    cart: CartSummary;
    addresses: Address[];
    checkoutKey: string;
    reservationMinutes: number;
}>();
const form = useForm({
    checkout_key: props.checkoutKey,
    address_id:
        props.addresses.find((address) => address.is_default)?.id ??
        props.addresses[0]?.id ??
        '',
    notes: '',
});
</script>
<template>
    <Head title="Checkout" />
    <div class="mx-auto max-w-7xl px-6 py-12">
        <Link href="/cart" class="text-sm text-muted-foreground hover:underline"
            >← Back to cart</Link
        >
        <h1 class="mt-5 text-4xl font-semibold tracking-tight">Checkout</h1>
        <form
            class="mt-8 grid items-start gap-10 lg:grid-cols-[1.5fr_1fr]"
            @submit.prevent="form.post('/checkout')"
        >
            <div class="space-y-8">
                <div
                    v-if="Object.keys(form.errors).length"
                    role="alert"
                    class="rounded-md border border-red-300 p-4 text-sm text-red-700 dark:text-red-400"
                >
                    {{ Object.values(form.errors).join(' ') }}
                </div>
                <section v-if="cart.requires_shipping">
                    <div
                        class="flex flex-wrap items-center justify-between gap-4"
                    >
                        <h2 class="text-xl font-semibold">Shipping address</h2>
                        <Link href="/addresses" class="text-sm underline"
                            >Manage addresses</Link
                        >
                    </div>
                    <div v-if="addresses.length" class="mt-5 grid gap-3">
                        <label
                            v-for="address in addresses"
                            :key="address.id"
                            class="flex cursor-pointer items-start gap-3 rounded-lg border p-4"
                            ><input
                                v-model="form.address_id"
                                type="radio"
                                name="address"
                                :value="address.id"
                                required
                                class="mt-1"
                            /><span class="text-sm leading-relaxed"
                                ><strong>{{ address.full_name }}</strong
                                ><br />{{ address.line1
                                }}<span v-if="address.line2"
                                    >, {{ address.line2 }}</span
                                ><br />{{ address.postcode }}
                                {{ address.city }}, {{ address.state }}<br />{{
                                    address.phone
                                }}<span
                                    v-if="address.is_default"
                                    class="ml-2 text-xs text-muted-foreground"
                                    >Default</span
                                ></span
                            ></label
                        >
                    </div>
                    <p
                        v-else
                        class="mt-5 rounded-lg border border-dashed p-6 text-sm text-muted-foreground"
                    >
                        Add a shipping address before placing your order.
                    </p>
                </section>
                <label class="grid gap-2 text-sm"
                    >Order notes (optional)<textarea
                        v-model="form.notes"
                        maxlength="2000"
                        rows="4"
                        class="rounded-md border bg-transparent p-3"
                    />
                </label>
                <section
                    class="rounded-lg bg-muted p-5 text-sm leading-relaxed"
                >
                    <h2 class="font-semibold">Order confirmation</h2>
                    <p class="mt-2 text-muted-foreground">
                        This creates an unpaid order valid for
                        {{ reservationMinutes }} minutes.
                        <template v-if="cart.requires_shipping"
                            >Physical stock is reserved during this
                            period.</template
                        >
                        No payment is taken on this page. If payments are
                        enabled, continue to ToyyibPay from your order details.
                    </p>
                </section>
            </div>
            <aside class="rounded-xl border p-6">
                <h2 class="text-xl font-semibold">Your order</h2>
                <div class="my-6 space-y-4">
                    <div
                        v-for="line in cart.items"
                        :key="line.id"
                        class="flex justify-between gap-4 text-sm"
                    >
                        <div>
                            <p>{{ line.name }} × {{ line.quantity }}</p>
                            <p
                                v-if="line.variant_name"
                                class="mt-1 text-xs text-muted-foreground"
                            >
                                {{ line.variant_name }}
                            </p>
                            <p
                                v-if="line.error"
                                class="mt-1 text-xs text-red-700"
                            >
                                {{ line.error }}
                            </p>
                        </div>
                        <p class="shrink-0">{{ money(line.line_total_sen) }}</p>
                    </div>
                </div>
                <OrderSummary
                    :subtotal="cart.subtotal_sen"
                    :shipping="cart.shipping_fee_sen"
                    :total="cart.total_sen"
                /><button
                    :disabled="
                        form.processing ||
                        !cart.valid ||
                        (cart.requires_shipping && !addresses.length)
                    "
                    class="mt-6 w-full rounded-md bg-emerald-800 px-5 py-3 font-medium text-white disabled:cursor-not-allowed disabled:opacity-50"
                >
                    {{
                        form.processing
                            ? 'Creating order…'
                            : 'Place pending order'
                    }}
                </button>
                <p
                    v-if="!cart.valid"
                    class="mt-4 text-sm text-red-700 dark:text-red-400"
                >
                    Review your cart before placing an order.
                </p>
            </aside>
        </form>
    </div>
</template>
