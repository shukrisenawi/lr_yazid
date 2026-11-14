<script setup lang="ts">
import { Head, Link, useForm } from '@inertiajs/vue3';
import OrderSummary from '@/components/OrderSummary.vue';
import StatusBadge from '@/components/StatusBadge.vue';
import { money } from '@/types/catalog';
import { dateTime, type Order } from '@/types/commerce';
defineProps<{ order: Order; paymentEnabled: boolean }>();
const form = useForm({});
function cancel(id: number) {
    if (
        confirm('Cancel this pending order and release any stock reservations?')
    )
        form.post(`/orders/${id}/cancel`, { preserveScroll: true });
}
</script>
<template>
    <Head :title="order.reference" />
    <div class="mx-auto w-full max-w-5xl p-6 md:p-10">
        <Link href="/orders" class="text-sm text-muted-foreground"
            >← My orders</Link
        >
        <div class="mt-5 flex flex-wrap items-center justify-between gap-4">
            <h1 class="text-2xl font-semibold tracking-tight md:text-3xl">
                {{ order.reference }}
            </h1>
            <StatusBadge :status="order.payment_status" />
        </div>
        <p class="mt-3 text-sm text-muted-foreground">
            {{ dateTime(order.created_at) }} · MYR
        </p>
        <section
            v-if="['pending', 'failed'].includes(order.payment_status)"
            class="mt-6 rounded-lg border bg-muted/50 p-5 text-sm leading-relaxed"
        >
            <h2 class="font-semibold">Awaiting payment</h2>
            <p class="mt-2 text-muted-foreground">
                {{
                    order.shipping_address
                        ? 'Stock is reserved until'
                        : 'Complete payment before'
                }}
                {{ dateTime(order.expires_at) }} (Malaysia time).
                {{
                    paymentEnabled
                        ? 'Continue to ToyyibPay to complete your payment.'
                        : 'Payment collection is unavailable or the order has expired.'
                }}
            </p>
            <button
                v-if="paymentEnabled"
                :disabled="form.processing"
                class="mt-4 mr-5 rounded-md bg-emerald-800 px-5 py-2.5 text-white disabled:opacity-50"
                @click="form.post(`/orders/${order.id}/pay`)"
            >
                {{
                    form.processing
                        ? 'Preparing payment…'
                        : order.payment_status === 'failed'
                          ? 'Retry payment'
                          : 'Pay with ToyyibPay'
                }}
            </button>
            <button
                :disabled="form.processing"
                class="mt-4 underline disabled:opacity-50"
                @click="cancel(order.id)"
            >
                Cancel pending order
            </button>
        </section>
        <p
            v-if="order.payments?.some((payment) => payment.requires_review)"
            role="alert"
            class="mt-5 rounded-md border p-4 text-sm"
        >
            A payment requires administrator review. Do not make another
            payment. Please contact support with your order reference.
        </p>
        <section
            v-if="order.payments?.length"
            class="mt-8 rounded-lg border p-5"
        >
            <h2 class="font-semibold">Payment attempts</h2>
            <div
                v-for="payment in order.payments"
                :key="payment.id"
                class="mt-4 flex flex-wrap justify-between gap-3 text-sm"
            >
                <div>
                    <p>{{ payment.reference }}</p>
                    <p class="mt-1 text-xs text-muted-foreground">
                        Bill: {{ payment.bill_code || 'Not confirmed' }}
                    </p>
                </div>
                <StatusBadge :status="payment.status" />
            </div>
        </section>
        <p
            v-if="order.cancellation_reason"
            class="mt-5 text-sm text-muted-foreground"
        >
            Cancellation reason:
            {{ order.cancellation_reason.replaceAll('_', ' ') }}
        </p>
        <p
            v-if="Object.keys(form.errors).length"
            role="alert"
            class="mt-4 text-sm text-red-700"
        >
            {{ Object.values(form.errors).join(' ') }}
        </p>
        <div class="mt-8 grid gap-8 lg:grid-cols-[1.5fr_1fr]">
            <section>
                <h2 class="text-xl font-semibold">Order items</h2>
                <article
                    v-for="item in order.items"
                    :key="item.id"
                    class="flex justify-between gap-4 border-b py-5 text-sm"
                >
                    <div>
                        <h3 class="font-medium">{{ item.product_name }}</h3>
                        <p
                            v-if="item.variant_name"
                            class="mt-1 text-muted-foreground"
                        >
                            {{ item.variant_name }}
                        </p>
                        <p class="mt-2 text-muted-foreground">
                            {{ item.sku }} · {{ item.quantity }} ×
                            {{ money(item.unit_price_sen) }}
                        </p>
                    </div>
                    <p class="shrink-0 font-medium">
                        {{ money(item.line_total_sen) }}
                    </p>
                </article>
                <div v-if="order.shipping_address" class="mt-8">
                    <h2 class="text-xl font-semibold">Ship to</h2>
                    <p class="mt-4 text-sm leading-relaxed">
                        {{ order.shipping_address.full_name }}<br />{{
                            order.shipping_address.line1
                        }}<br v-if="order.shipping_address.line2" />{{
                            order.shipping_address.line2
                        }}<br />{{ order.shipping_address.postcode }}
                        {{ order.shipping_address.city }},
                        {{ order.shipping_address.state }}<br />{{
                            order.shipping_address.phone
                        }}
                    </p>
                </div>
                <p v-if="order.notes" class="mt-6 text-sm">
                    <strong>Order notes:</strong> {{ order.notes }}
                </p>
            </section>
            <aside class="h-fit rounded-xl border p-6">
                <h2 class="mb-6 text-xl font-semibold">Order summary</h2>
                <OrderSummary
                    :subtotal="order.subtotal_sen"
                    :shipping="order.shipping_fee_sen"
                    :total="order.total_sen"
                />
                <div
                    v-if="order.fulfillment_status"
                    class="mt-6 border-t pt-4 text-sm"
                >
                    <p class="mb-2 text-muted-foreground">Fulfillment status</p>
                    <StatusBadge
                        v-if="order.fulfillment_status"
                        :status="order.fulfillment_status"
                    />
                </div>
            </aside>
        </div>
    </div>
</template>
