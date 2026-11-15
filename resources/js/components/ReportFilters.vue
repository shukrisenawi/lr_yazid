<script setup lang="ts">
import { useForm } from '@inertiajs/vue3';
import { computed } from 'vue';
import { reportCopy as copy } from '@/lib/reportCopy';
import { reportExportUrl } from '@/lib/reporting';
import type { ReportFilters } from '@/types/reports';
const props = defineProps<{ filters: ReportFilters; action: string }>();
const form = useForm({ ...props.filters });
const exportUrl = computed(() => reportExportUrl(props.filters));
</script>
<template>
    <p class="mt-4 max-w-4xl text-sm leading-relaxed text-muted-foreground">
        {{ copy.help }}
    </p>
    <form
        class="mt-6 flex flex-wrap items-end gap-4"
        @submit.prevent="form.get(action)"
    >
        <label class="grid gap-2 text-sm"
            >{{ copy.from
            }}<input
                v-model="form.from"
                type="date"
                required
                class="rounded-md border bg-transparent p-2.5"
        /></label>
        <label class="grid gap-2 text-sm"
            >{{ copy.to
            }}<input
                v-model="form.to"
                type="date"
                required
                class="rounded-md border bg-transparent p-2.5"
        /></label>
        <label class="grid gap-2 text-sm"
            >{{ copy.environment
            }}<select
                v-model="form.environment"
                class="rounded-md border bg-background p-2.5"
            >
                <option value="production">{{ copy.production }}</option>
                <option value="sandbox">{{ copy.sandbox }}</option>
            </select></label
        >
        <button
            :disabled="form.processing"
            class="rounded-md border px-4 py-2.5 text-sm disabled:opacity-50"
        >
            {{ copy.apply }}
        </button>
        <a :href="exportUrl" class="py-2.5 text-sm underline">{{
            copy.export
        }}</a>
    </form>
    <p
        v-if="Object.keys($page.props.errors).length"
        role="alert"
        class="mt-4 text-sm text-red-700 dark:text-red-400"
    >
        {{ Object.values($page.props.errors).join(' ') }}
    </p>
</template>
