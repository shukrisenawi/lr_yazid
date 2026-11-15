<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { reportCopy as copy } from '@/lib/reportCopy';
import { money } from '@/types/catalog';
import { dateTime } from '@/types/commerce';
import type { AdminReport } from '@/types/reports';
defineProps<{ report: AdminReport }>();
</script>
<template>
    <p class="mt-6 text-xs text-muted-foreground">
        {{ copy.generated }} {{ dateTime(report.as_of) }}
    </p>
    <p
        v-if="report.filters.environment === 'sandbox'"
        role="note"
        class="mt-4 rounded-md border border-amber-600 p-4 text-sm"
    >
        {{ copy.sandboxHelp }}
    </p>
    <section class="mt-8">
        <h2 class="text-xl font-semibold">{{ copy.summary }}</h2>
        <p class="mt-3 max-w-5xl text-sm leading-relaxed text-muted-foreground">
            {{ copy.financialHelp }}
        </p>
        <p
            v-if="!report.summary.paid_orders"
            class="mt-4 text-sm text-muted-foreground"
        >
            {{ copy.empty }}
        </p>
        <dl class="mt-6 grid gap-4 sm:grid-cols-2 xl:grid-cols-3">
            <div
                v-for="(value, key) in report.summary"
                :key="key"
                class="rounded-md border p-5"
            >
                <dt class="text-sm text-muted-foreground">
                    {{ report.labels[key] }}
                </dt>
                <dd class="mt-3 text-2xl font-semibold tabular-nums">
                    {{
                        key.endsWith('_sen')
                            ? money(value)
                            : value.toLocaleString('en-MY')
                    }}
                </dd>
            </div>
        </dl>
    </section>
    <section class="mt-10">
        <h2 class="text-xl font-semibold">{{ copy.current }}</h2>
        <p class="mt-3 max-w-5xl text-sm leading-relaxed text-muted-foreground">
            {{ copy.currentHelp }}
        </p>
        <dl class="mt-6 grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
            <div
                v-for="(value, key) in report.current"
                :key="key"
                class="border-t py-4"
            >
                <dt class="text-sm text-muted-foreground">
                    {{ report.labels[key] }}
                </dt>
                <dd class="mt-3 text-2xl font-semibold tabular-nums">
                    {{ value.toLocaleString('en-MY') }}
                </dd>
            </div>
        </dl>
    </section>
    <nav class="mt-6 flex flex-wrap gap-5 text-sm">
        <Link href="/admin/orders" class="underline">{{ copy.orders }}</Link
        ><Link href="/admin/payments" class="underline">{{
            copy.payments
        }}</Link
        ><Link href="/admin/customers" class="underline">{{
            copy.customers
        }}</Link
        ><Link href="/admin/appointments" class="underline">{{
            copy.bookings
        }}</Link>
    </nav>
</template>
