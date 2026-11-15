<script setup lang="ts">
import { Head, Link, useForm } from '@inertiajs/vue3';
import OrderSummary from '@/components/OrderSummary.vue';
import ManualRefundLedger from '@/components/ManualRefundLedger.vue';
import { orderCopy as copy } from '@/lib/orderCopy';
import { documentCopy } from '@/lib/documentCopy';
import { orderCancellationCopy as cancellationCopy } from '@/lib/orderCancellationCopy';
import { money } from '@/types/catalog';
import { dateTime, type Order } from '@/types/commerce';
const props = defineProps<{
    order: Order;
    canCancel: boolean;
    refundKey?: string;
}>();
const form = useForm({
    fulfillment_status: props.order.fulfillment_status ?? 'new',
    courier_name: props.order.courier_name ?? '',
    tracking_number: props.order.tracking_number ?? '',
    reason: '',
});
function print() {
    window.print();
}
const invoiceForm = useForm({});
const cancellationForm = useForm({ reason: '' });
function cancelOrder() {
    if (window.confirm(cancellationCopy.confirm)) {
        cancellationForm.post(`/admin/orders/${props.order.id}/cancel`);
    }
}
</script>
<template>
    <Head :title="order.reference" />
    <div class="mx-auto w-full max-w-5xl p-6 md:p-10">
        <div class="flex flex-wrap justify-between gap-4 print:hidden">
            <Link href="/admin/orders" class="text-sm underline">{{
                copy.back
            }}</Link
            ><button class="text-sm underline" @click="print">
                {{ copy.invoice }}
            </button>
        </div>
        <h1 class="mt-6 text-3xl font-semibold">{{ order.reference }}</h1>
        <div class="mt-4 flex flex-wrap gap-5 text-sm print:hidden">
            <Link
                v-if="order.invoice"
                :href="`/invoices/${order.invoice.id}`"
                class="underline"
                >{{ documentCopy.view }} · {{ order.invoice.reference }}</Link
            >
            <form
                v-else
                @submit.prevent="
                    invoiceForm.post(`/admin/orders/${order.id}/invoice`)
                "
            >
                <button :disabled="invoiceForm.processing" class="underline">
                    {{ documentCopy.issue }}
                </button>
            </form>
            <Link href="/admin/invoice-settings" class="underline">{{
                documentCopy.settings
            }}</Link>
        </div>
        <p
            v-if="Object.keys(invoiceForm.errors).length"
            role="alert"
            class="mt-4 text-sm text-red-700"
        >
            {{ Object.values(invoiceForm.errors).join(' ') }}
        </p>
        <p class="mt-3 text-sm">
            {{ dateTime(order.created_at) }} · {{ order.payment_status }} ·
            {{ order.customer_name }} · {{ order.customer_email }}
        </p>
        <p
            v-if="order.shipping_address"
            class="mt-4 text-sm whitespace-pre-line"
        >
            {{
                Object.values(order.shipping_address).filter(Boolean).join(', ')
            }}
        </p>
        <div class="mt-8 grid items-start gap-8 md:grid-cols-2">
            <section>
                <h2 class="text-xl font-semibold">{{ copy.items }}</h2>
                <article
                    v-for="item in order.items"
                    :key="item.id"
                    class="border-b py-4 text-sm"
                >
                    <p class="font-medium">
                        {{ item.product_name }} {{ item.variant_name }}
                    </p>
                    <p class="mt-2">
                        {{ item.sku }} · {{ item.quantity }} ×
                        {{ money(item.unit_price_sen) }} =
                        {{ money(item.line_total_sen) }}
                    </p>
                </article>
            </section>
            <aside class="rounded-xl border p-6">
                <OrderSummary
                    :subtotal="order.subtotal_sen"
                    :shipping="order.shipping_fee_sen"
                    :total="order.total_sen"
                />
                <p class="mt-5 text-sm">
                    {{ copy.fulfillment }}:
                    {{ order.fulfillment_status ?? '—' }}
                </p>
                <p v-if="order.tracking_number" class="mt-3 text-sm">
                    {{ order.courier_name }} · {{ order.tracking_number }}
                </p>
            </aside>
        </div>
        <form
            v-if="
                order.shipping_address &&
                order.payment_status === 'paid' &&
                order.fulfillment_status !== 'delivered'
            "
            class="mt-8 space-y-5 rounded-xl border p-6 print:hidden"
            @submit.prevent="form.put(`/admin/orders/${order.id}`)"
        >
            <p class="text-sm text-muted-foreground">{{ copy.notice }}</p>
            <label class="grid gap-2 text-sm"
                >{{ copy.fulfillment
                }}<select
                    v-model="form.fulfillment_status"
                    class="rounded-md border bg-background p-2.5"
                >
                    <option v-for="status in copy.states" :key="status">
                        {{ status }}
                    </option>
                </select></label
            ><label class="grid gap-2 text-sm"
                >{{ copy.courier
                }}<input
                    v-model="form.courier_name"
                    maxlength="100"
                    class="rounded-md border bg-transparent p-2.5" /></label
            ><label class="grid gap-2 text-sm"
                >{{ copy.tracking
                }}<input
                    v-model="form.tracking_number"
                    maxlength="150"
                    class="rounded-md border bg-transparent p-2.5" /></label
            ><label class="grid gap-2 text-sm"
                >{{ copy.reason
                }}<textarea
                    v-model="form.reason"
                    required
                    minlength="5"
                    maxlength="1000"
                    rows="3"
                    class="rounded-md border bg-transparent p-3"
                />
            </label>
            <p
                v-if="Object.keys(form.errors).length"
                role="alert"
                class="text-sm text-red-700"
            >
                {{ Object.values(form.errors).join(' ') }}
            </p>
            <button
                :disabled="form.processing"
                class="rounded-md bg-emerald-800 px-5 py-3 text-white"
            >
                {{ copy.save }}
            </button>
        </form>
        <form
            v-if="canCancel"
            class="mt-8 space-y-4 rounded-xl border p-6 print:hidden"
            @submit.prevent="cancelOrder"
        >
            <h2 class="text-xl font-semibold">{{ cancellationCopy.title }}</h2>
            <p class="text-sm text-muted-foreground">
                {{ cancellationCopy.help }}
            </p>
            <label class="grid gap-2 text-sm"
                >{{ cancellationCopy.reason }}
                <textarea
                    v-model="cancellationForm.reason"
                    required
                    minlength="5"
                    maxlength="1000"
                    rows="3"
                    class="rounded-md border bg-transparent p-3"
                />
            </label>
            <p
                v-if="Object.keys(cancellationForm.errors).length"
                role="alert"
                class="text-sm text-red-700 dark:text-red-400"
            >
                {{ Object.values(cancellationForm.errors).join(' ') }}
            </p>
            <button
                :disabled="cancellationForm.processing"
                class="rounded-md border px-5 py-3 text-sm disabled:opacity-50"
            >
                {{ cancellationCopy.submit }}
            </button>
        </form>
        <section
            v-if="order.cancellation || order.cancellation_reason"
            class="mt-8 rounded-xl border p-6 print:hidden"
        >
            <h2 class="text-xl font-semibold">
                {{ cancellationCopy.history }}
            </h2>
            <template v-if="order.cancellation">
                <p class="mt-3 text-sm">
                    {{ order.cancellation.source }} ·
                    {{ dateTime(order.cancellation.created_at) }} ·
                    {{ cancellationCopy.actor }} #{{
                        order.cancellation.actor_id ?? '—'
                    }}
                </p>
                <p class="mt-3 text-sm">
                    {{ cancellationCopy.previous }}:
                    {{ order.cancellation.previous_payment_status }} /
                    {{ order.cancellation.previous_fulfillment_status ?? '—' }}
                </p>
            </template>
            <p
                class="mt-3 text-sm [overflow-wrap:anywhere] whitespace-pre-line"
            >
                {{ order.cancellation?.reason ?? order.cancellation_reason }}
            </p>
        </section>
        <ManualRefundLedger :order="order" :refund-key="refundKey" />
        <section class="mt-8 print:hidden">
            <h2 class="text-xl font-semibold">{{ copy.history }}</h2>
            <ol class="mt-4 space-y-3">
                <li
                    v-for="event in order.fulfillment_history"
                    :key="event.id"
                    class="rounded-md border p-4 text-sm"
                >
                    {{ event.previous_status }} → {{ event.new_status }} ·
                    {{ dateTime(event.created_at) }} · #{{ event.admin_id }}
                    <p class="mt-2">{{ event.reason }}</p>
                </li>
            </ol>
        </section>
    </div>
</template>
