<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import ReportFilters from '@/components/ReportFilters.vue';
import ReportOverview from '@/components/ReportOverview.vue';
import ReportMonthly from '@/components/ReportMonthly.vue';
import { reportCopy as copy } from '@/lib/reportCopy';
import { computed } from 'vue';
import type { AdminReport } from '@/types/reports';
import {
    ArrowRight,
    CalendarDays,
    CreditCard,
    ShoppingBag,
    Users,
} from '@lucide/vue';
import { money } from '@/types/catalog';
const props = defineProps<{ report: AdminReport }>();
const reportUrl = computed(
    () =>
        `/admin/reports?${new URLSearchParams(props.report.filters).toString()}`,
);
</script>
<template>
    <Head :title="copy.dashboard" />
    <div class="dashboard-page dashboard-report">
        <div class="dashboard-page-heading">
            <h1>{{ copy.dashboard }}</h1>
            <span class="text-xs text-muted-foreground"
                >Csiti.my / Administration</span
            >
        </div>
        <div class="grid gap-6 xl:grid-cols-[2fr_1fr_1fr]">
            <div class="dashboard-panel dashboard-welcome">
                <h2 class="text-xl font-semibold text-primary">
                    Your business, at a glance.
                </h2>
                <p class="mt-3 text-sm leading-6 text-muted-foreground">
                    Monitor sales, memberships and appointments from one central
                    workspace.
                </p>
                <Link :href="reportUrl" class="dashboard-action mt-5"
                    >{{ copy.view }} <ArrowRight class="size-4"
                /></Link>
            </div>
            <section class="dashboard-panel">
                <span class="dashboard-tile-icon"
                    ><CreditCard class="size-6"
                /></span>
                <h2
                    class="mt-4 text-xs font-medium text-muted-foreground uppercase"
                >
                    Verified collected sales
                </h2>
                <p class="mt-3 text-2xl font-semibold tabular-nums">
                    {{ money(report.summary.collected_sen) }}
                </p>
                <p class="mt-3 text-xs text-muted-foreground">
                    Selected period · including shipping
                </p>
            </section>
            <section class="dashboard-panel">
                <span class="dashboard-tile-icon"
                    ><ShoppingBag class="size-6"
                /></span>
                <h2
                    class="mt-4 text-xs font-medium text-muted-foreground uppercase"
                >
                    Verified paid orders
                </h2>
                <p class="mt-3 text-2xl font-semibold tabular-nums">
                    {{ report.summary.paid_orders.toLocaleString('en-MY') }}
                </p>
                <p class="mt-3 text-xs text-muted-foreground">
                    Selected period · {{ report.filters.environment }}
                </p>
            </section>
        </div>
        <nav
            class="dashboard-shortcuts mt-6 grid gap-x-6 rounded-md border bg-card px-6 md:grid-cols-3"
            aria-label="Administration shortcuts"
        >
            <Link href="/admin/orders" class="dashboard-quick-link"
                ><ShoppingBag class="size-4 text-primary" /><span
                    class="flex-1"
                    >{{ copy.orders }}</span
                ><ArrowRight class="size-4"
            /></Link>
            <Link href="/admin/customers" class="dashboard-quick-link"
                ><Users class="size-4 text-primary" /><span class="flex-1">{{
                    copy.customers
                }}</span
                ><ArrowRight class="size-4"
            /></Link>
            <Link href="/admin/appointments" class="dashboard-quick-link"
                ><CalendarDays class="size-4 text-primary" /><span
                    class="flex-1"
                    >{{ copy.bookings }}</span
                ><ArrowRight class="size-4"
            /></Link>
        </nav>
        <div class="dashboard-panel mt-6">
            <h2 class="font-semibold">Report period</h2>
            <ReportFilters
                :filters="report.filters"
                action="/admin/dashboard"
            />
        </div>
        <ReportOverview :report="report" /><ReportMonthly :report="report" />
    </div>
</template>
