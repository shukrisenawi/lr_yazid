<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { computed } from 'vue';
import StatusBadge from '@/components/StatusBadge.vue';
import { money } from '@/types/catalog';
import type { Order, Payment } from '@/types/commerce';
const props = defineProps<{ payment: Payment; order: Order }>();
const title = computed(() =>
    props.payment.requires_review
        ? 'Payment needs review'
        : props.order.payment_status === 'paid'
          ? 'Payment successful'
          : props.payment.status === 'failed'
            ? 'Payment unsuccessful'
            : 'Payment pending',
);
</script>
<template>
    <Head :title="title" />
    <div class="mx-auto w-full max-w-3xl p-6 md:p-12">
        <StatusBadge :status="payment.status" />
        <h1 class="mt-5 text-3xl font-semibold">{{ title }}</h1>
        <p class="mt-5 leading-relaxed text-muted-foreground">
            {{
                payment.requires_review
                    ? 'Your payment was verified, but the order needs administrator review. Do not make another payment. Please contact support with your order reference.'
                    : order.payment_status === 'paid'
                      ? 'Your payment has been verified by the server. Thank you for your order.'
                      : payment.status === 'failed'
                        ? 'The payment has not completed successfully. You can review your order and retry while its stock reservation is valid.'
                        : 'We are waiting for server-side confirmation. A browser return alone does not confirm payment. You can check the latest status on your order page.'
            }}
        </p>
        <dl class="mt-8 grid gap-3 rounded-xl border p-6 text-sm">
            <div class="flex justify-between gap-4">
                <dt>Order</dt>
                <dd>{{ order.reference }}</dd>
            </div>
            <div class="flex justify-between gap-4">
                <dt>Amount</dt>
                <dd>{{ money(order.total_sen) }}</dd>
            </div>
            <div class="flex justify-between gap-4">
                <dt>Bill code</dt>
                <dd>{{ payment.bill_code }}</dd>
            </div>
        </dl>
        <Link
            :href="`/orders/${order.id}`"
            class="mt-8 inline-block rounded-md bg-emerald-800 px-5 py-3 text-white"
            >View order</Link
        >
    </div>
</template>
