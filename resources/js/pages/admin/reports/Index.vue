<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import ReportFilters from '@/components/ReportFilters.vue';
import ReportOverview from '@/components/ReportOverview.vue';
import ReportMonthly from '@/components/ReportMonthly.vue';
import { reportCopy as copy } from '@/lib/reportCopy';
import type { AdminReport } from '@/types/reports';
defineProps<{ report: AdminReport }>();
</script>
<template>
    <Head :title="copy.title" />
    <div class="dashboard-report mx-auto w-full max-w-7xl min-w-0 p-6 md:p-10">
        <Link href="/admin/dashboard" class="text-sm underline">{{
            copy.back
        }}</Link>
        <h1 class="mt-5 text-3xl font-semibold">{{ copy.title }}</h1>
        <ReportFilters
            :filters="report.filters"
            action="/admin/reports"
        /><ReportOverview :report="report" /><ReportMonthly :report="report" />
    </div>
</template>
