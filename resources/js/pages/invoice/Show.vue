<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { documentCopy as copy } from '@/lib/documentCopy';
import { money } from '@/types/catalog';
import { dateTime } from '@/types/commerce';
import type { Invoice } from '@/types/invoice';
defineProps<{ invoice: Invoice; backUrl: string }>();
function print() {
    window.print();
}
</script>
<template>
    <Head :title="invoice.reference" />
    <article class="mx-auto max-w-4xl p-6 sm:p-10 print:max-w-none print:p-0">
        <div class="mb-10 flex flex-wrap justify-between gap-4 print:hidden">
            <Link :href="backUrl" class="text-sm underline">{{
                copy.back
            }}</Link
            ><button class="rounded-md border px-4 py-2 text-sm" @click="print">
                {{ copy.print }}
            </button>
        </div>
        <header class="flex flex-wrap justify-between gap-8 border-b pb-8">
            <div>
                <h1 class="text-3xl font-semibold">{{ copy.title }}</h1>
                <p class="mt-3 font-medium">{{ invoice.reference }}</p>
                <p class="mt-2 text-sm">
                    {{ copy.issued }}: {{ dateTime(invoice.issued_at) }}
                </p>
            </div>
            <div class="text-sm">
                <h2 class="font-semibold">
                    {{ invoice.snapshot.seller.seller_name }}
                </h2>
                <p class="mt-2 whitespace-pre-line">
                    {{ invoice.snapshot.seller.seller_address }}
                </p>
                <p class="mt-2">{{ invoice.snapshot.seller.seller_email }}</p>
                <p
                    v-if="invoice.snapshot.seller.registration_number"
                    class="mt-2"
                >
                    {{ invoice.snapshot.seller.registration_number }}
                </p>
            </div>
        </header>
        <section class="my-8 grid gap-8 sm:grid-cols-2">
            <div class="text-sm">
                <h2 class="font-semibold">{{ copy.buyer }}</h2>
                <p class="mt-3">{{ invoice.snapshot.buyer.customer_name }}</p>
                <p class="mt-2">{{ invoice.snapshot.buyer.customer_email }}</p>
                <p class="mt-2">{{ invoice.snapshot.buyer.customer_phone }}</p>
                <p v-if="invoice.snapshot.buyer.shipping_address" class="mt-2">
                    {{
                        Object.values(invoice.snapshot.buyer.shipping_address)
                            .filter(Boolean)
                            .join(', ')
                    }}
                </p>
            </div>
            <dl class="space-y-2 text-sm">
                <div>
                    <dt class="text-neutral-500">{{ copy.order }}</dt>
                    <dd>{{ invoice.snapshot.order_reference }}</dd>
                </div>
                <div>
                    <dt class="text-neutral-500">{{ copy.payment }}</dt>
                    <dd>{{ invoice.snapshot.payment_reference }}</dd>
                </div>
                <div>
                    <dt class="text-neutral-500">{{ copy.paid }}</dt>
                    <dd>{{ dateTime(invoice.snapshot.paid_at) }}</dd>
                </div>
            </dl>
        </section>
        <div class="overflow-x-auto">
            <table class="w-full text-left text-sm">
                <thead>
                    <tr class="border-b">
                        <th class="py-3">{{ copy.items }}</th>
                        <th class="p-3">{{ copy.quantity }}</th>
                        <th class="p-3 whitespace-nowrap">{{ copy.unit }}</th>
                        <th class="py-3 text-right">{{ copy.total }}</th>
                    </tr>
                </thead>
                <tbody>
                    <tr
                        v-for="(item, index) in invoice.snapshot.items"
                        :key="index"
                        class="border-b"
                    >
                        <td class="py-4">
                            <p class="font-medium">{{ item.product_name }}</p>
                            <p class="mt-1 text-xs text-neutral-500">
                                {{ item.variant_name }} · {{ item.sku }}
                            </p>
                        </td>
                        <td class="p-3">{{ item.quantity }}</td>
                        <td class="p-3 whitespace-nowrap">
                            {{ money(item.unit_price_sen) }}
                        </td>
                        <td class="py-4 text-right whitespace-nowrap">
                            {{ money(item.line_total_sen) }}
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
        <dl class="mt-8 ml-auto max-w-sm space-y-3 text-sm">
            <div class="flex justify-between gap-8">
                <dt>{{ copy.subtotal }}</dt>
                <dd>{{ money(invoice.snapshot.subtotal_sen) }}</dd>
            </div>
            <div class="flex justify-between gap-8">
                <dt>{{ copy.shipping }}</dt>
                <dd>{{ money(invoice.snapshot.shipping_fee_sen) }}</dd>
            </div>
            <div class="flex justify-between gap-8 border-t pt-4 font-semibold">
                <dt>{{ copy.total }} ({{ invoice.snapshot.currency }})</dt>
                <dd>{{ money(invoice.snapshot.total_sen) }}</dd>
            </div>
        </dl>
        <footer
            class="mt-12 border-t pt-5 text-xs leading-relaxed text-neutral-500"
        >
            <p
                v-if="invoice.snapshot.seller.footer_note"
                class="mb-3 whitespace-pre-line"
            >
                {{ invoice.snapshot.seller.footer_note }}
            </p>
            <p>{{ copy.disclaimer }}</p>
        </footer>
    </article>
</template>
<style>
@media print {
    @page {
        margin: 16mm;
    }
    tr {
        break-inside: avoid;
    }
}
</style>
