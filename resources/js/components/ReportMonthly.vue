<script setup lang="ts">
import { computed, ref } from 'vue';
import { reportCopy as copy } from '@/lib/reportCopy';
import { barPercent } from '@/lib/reporting';
import { money } from '@/types/catalog';
import type { AdminReport } from '@/types/reports';
const props = defineProps<{ report: AdminReport }>();
const maximum = computed(() =>
    Math.max(0, ...props.report.monthly.map((row) => row.collected_sen)),
);
const activities = [
    'orders_created',
    'vip_activations',
    'vip_renewals',
    'appointment_requests',
] as const;
const activity = ref<(typeof activities)[number]>('orders_created');
const activityMaximum = computed(() =>
    Math.max(0, ...props.report.monthly.map((row) => row[activity.value])),
);
</script>
<template>
    <figure class="mt-12 border-t pt-8">
        <figcaption class="text-xl font-semibold">{{ copy.chart }}</figcaption>
        <div class="mt-4 flex flex-wrap gap-6 text-xs">
            <span class="flex items-center gap-2"
                ><span class="size-3 bg-emerald-800 dark:bg-emerald-500" />{{
                    copy.sales
                }}</span
            ><span class="flex items-center gap-2"
                ><span class="size-3 bg-stone-500 dark:bg-stone-400" />{{
                    copy.membership
                }}</span
            >
        </div>
        <ol class="mt-6 space-y-5">
            <li
                v-for="row in report.monthly"
                :key="row.month"
                class="grid items-center gap-3 sm:grid-cols-[5rem_1fr_9rem]"
            >
                <span class="text-sm tabular-nums">{{ row.month }}</span>
                <div
                    class="space-y-1.5"
                    role="img"
                    :aria-label="`${row.month}: ${copy.sales} ${money(row.collected_sen)}, ${copy.membership} ${money(row.membership_sen)}`"
                >
                    <div
                        class="h-3 bg-emerald-800 dark:bg-emerald-500"
                        :style="{
                            width: `${barPercent(row.collected_sen, maximum)}%`,
                        }"
                    />
                    <div
                        class="h-3 bg-stone-500 dark:bg-stone-400"
                        :style="{
                            width: `${barPercent(row.membership_sen, maximum)}%`,
                        }"
                    />
                </div>
                <span class="text-sm tabular-nums sm:text-right">{{
                    money(row.collected_sen)
                }}</span>
            </li>
        </ol>
    </figure>
    <section class="mt-10" :aria-label="copy.activity">
        <h2 class="text-xl font-semibold">{{ copy.activity }}</h2>
        <div
            role="group"
            :aria-label="copy.chooseActivity"
            class="mt-5 flex flex-wrap gap-3"
        >
            <button
                v-for="metric in activities"
                :key="metric"
                type="button"
                :aria-pressed="activity === metric"
                class="min-h-11 rounded-md border px-4 py-2 text-sm"
                :class="
                    activity === metric
                        ? 'border-emerald-800 bg-emerald-800 text-white'
                        : ''
                "
                @click="activity = metric"
            >
                {{ report.labels[metric] }}
            </button>
        </div>
        <ol class="mt-6 space-y-4">
            <li
                v-for="row in report.monthly"
                :key="row.month"
                class="grid items-center gap-3 sm:grid-cols-[5rem_1fr_5rem]"
            >
                <span class="text-sm tabular-nums">{{ row.month }}</span>
                <div
                    role="img"
                    :aria-label="`${row.month}: ${report.labels[activity]} ${row[activity]}`"
                >
                    <div
                        class="h-3 bg-emerald-800 dark:bg-emerald-500"
                        :style="{
                            width: `${barPercent(row[activity], activityMaximum)}%`,
                        }"
                    />
                </div>
                <span class="text-sm tabular-nums sm:text-right">{{
                    row[activity]
                }}</span>
            </li>
        </ol>
    </section>
    <section class="mt-10">
        <h2 class="text-xl font-semibold">{{ copy.monthly }}</h2>
        <p class="mt-3 text-sm leading-relaxed text-muted-foreground">
            {{ copy.cohortHelp }}
        </p>
        <div class="mt-5 overflow-x-auto rounded-md border">
            <table class="w-full text-left text-sm">
                <caption class="sr-only">
                    {{
                        copy.monthly
                    }}
                </caption>
                <thead>
                    <tr class="border-b bg-muted/40">
                        <th scope="col" class="p-3 whitespace-nowrap">
                            {{ copy.month }}
                        </th>
                        <th
                            v-for="key in copy.columns"
                            :key="key"
                            scope="col"
                            class="min-w-36 p-3"
                        >
                            {{ report.labels[key] }}
                        </th>
                    </tr>
                </thead>
                <tbody>
                    <tr
                        v-for="row in report.monthly"
                        :key="row.month"
                        class="border-b last:border-0"
                    >
                        <th
                            scope="row"
                            class="p-3 font-medium whitespace-nowrap"
                        >
                            {{ row.month }}
                        </th>
                        <td
                            v-for="key in copy.columns"
                            :key="key"
                            class="p-3 whitespace-nowrap tabular-nums"
                        >
                            {{
                                key.endsWith('_sen')
                                    ? money(row[key])
                                    : row[key].toLocaleString('en-MY')
                            }}
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
    </section>
    <section class="mt-10">
        <h2 class="text-xl font-semibold">{{ copy.appointments }}</h2>
        <dl class="mt-5 grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
            <div
                v-for="(count, status) in report.appointments"
                :key="status"
                class="border-t py-4"
            >
                <dt class="text-sm text-muted-foreground">
                    {{ report.statusLabels[status] }}
                </dt>
                <dd class="mt-2 text-2xl font-semibold tabular-nums">
                    {{ count }}
                </dd>
            </div>
        </dl>
    </section>
</template>
