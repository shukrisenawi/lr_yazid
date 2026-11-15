<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import ReportFilters from '@/components/ReportFilters.vue';
import ReportOverview from '@/components/ReportOverview.vue';
import ReportMonthly from '@/components/ReportMonthly.vue';
import { reportCopy as copy } from '@/lib/reportCopy';
import { computed } from 'vue';
import type { AdminReport } from '@/types/reports';
const props = defineProps<{ report: AdminReport }>();
const reportUrl = computed(
    () =>
        `/admin/reports?${new URLSearchParams(props.report.filters).toString()}`,
);
</script>
<template>
    <Head :title="copy.dashboard" />
    <div class="mx-auto w-full max-w-7xl min-w-0 p-6 md:p-10">
        <h1 class="text-3xl font-semibold tracking-tight">
            {{ copy.dashboard }}
        </h1>
        <Link :href="reportUrl" class="mt-4 inline-block text-sm underline">{{
            copy.view
        }}</Link
        ><ReportFilters
            :filters="report.filters"
            action="/admin/dashboard"
        /><ReportOverview :report="report" /><ReportMonthly :report="report" />
    </div>
</template>
