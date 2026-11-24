<script setup lang="ts">
import { computed, ref, watch } from 'vue';
import { appointmentCopy as copy } from '@/lib/appointmentCopy';
import {
    calendarDays,
    malaysiaDate,
    shiftMonth,
} from '@/lib/appointmentCalendar';
import type { AvailableAppointmentSlot } from '@/types/appointment';
import { dateTime } from '@/types/commerce';
const props = defineProps<{
    month: string;
    slots: AvailableAppointmentSlot[];
    modelValue: number | null;
    loading?: boolean;
    from?: string | null;
    to?: string | null;
}>();
const emit = defineEmits<{
    'update:modelValue': [id: number];
    month: [value: string];
}>();
const selectedDate = ref(
    props.slots.find((slot) => slot.available)
        ? malaysiaDate(props.slots.find((slot) => slot.available)!.starts_at)
        : `${props.month}-01`,
);
const days = computed(() => calendarDays(props.month));
const times = computed(() =>
    props.slots.filter((slot) =>
        props.from && props.to
            ? malaysiaDate(slot.starts_at) >= props.from &&
              malaysiaDate(slot.starts_at) <= props.to
            : malaysiaDate(slot.starts_at) === selectedDate.value,
    ),
);
const counts = computed(() => {
    const map: Record<string, number> = {};
    for (const slot of props.slots)
        if (slot.available) {
            const date = malaysiaDate(slot.starts_at);
            map[date] = (map[date] ?? 0) + 1;
        }
    return map;
});
watch(
    () => props.month,
    () => {
        selectedDate.value = `${props.month}-01`;
    },
);
</script>
<template>
    <section :aria-busy="loading" class="rounded-xl border p-4 sm:p-6">
        <h2 v-if="from && to" class="font-semibold">{{ from }} — {{ to }}</h2>
        <template v-else>
            <div class="flex items-center justify-between gap-3">
                <button
                    type="button"
                    :disabled="loading"
                    :aria-label="copy.previous"
                    class="rounded-md border px-3 py-2"
                    @click="emit('month', shiftMonth(month, -1))"
                >
                    ←
                </button>
                <h2 class="font-semibold">{{ month }}</h2>
                <button
                    type="button"
                    :disabled="loading"
                    :aria-label="copy.next"
                    class="rounded-md border px-3 py-2"
                    @click="emit('month', shiftMonth(month, 1))"
                >
                    →
                </button>
            </div>
            <div
                class="mt-5 grid grid-cols-7 text-center text-xs text-muted-foreground"
            >
                <span v-for="day in copy.days" :key="day" :title="day">{{
                    day.slice(0, 3)
                }}</span>
            </div>
            <div class="mt-2 grid grid-cols-7 gap-1">
                <button
                    v-for="day in days"
                    :key="day"
                    type="button"
                    :disabled="loading || !day.startsWith(month)"
                    :aria-pressed="selectedDate === day"
                    :aria-label="`${day}: ${counts[day] ?? 0} ${copy.available}`"
                    class="min-h-12 rounded-md border p-1 text-sm disabled:opacity-30"
                    :class="
                        selectedDate === day
                            ? 'border-emerald-700 bg-emerald-800 text-white'
                            : ''
                    "
                    @click="selectedDate = day"
                >
                    <span>{{ Number(day.slice(-2)) }}</span
                    ><span
                        v-if="counts[day]"
                        class="mx-auto mt-1 block size-1 rounded-full bg-emerald-500"
                        aria-hidden="true"
                    />
                </button>
            </div>
        </template>
        <h3 class="mt-6 font-medium">
            {{ from && to ? 'Select a time slot' : selectedDate }}
            <span class="text-red-600" aria-hidden="true">*</span>
        </h3>
        <p v-if="!times.length" class="mt-4 text-sm text-muted-foreground">
            {{ from && to ? 'No slots in this date range.' : copy.noTimes }}
        </p>
        <div class="mt-4 grid gap-3 sm:grid-cols-2">
            <button
                v-for="slot in times"
                :key="slot.id"
                type="button"
                :disabled="loading || !slot.available"
                :aria-pressed="modelValue === slot.id"
                class="rounded-md border p-3 text-left text-sm disabled:opacity-60"
                :class="
                    modelValue === slot.id
                        ? 'border-emerald-800 ring-1 ring-emerald-800'
                        : ''
                "
                @click="emit('update:modelValue', slot.id)"
            >
                <span class="block font-medium"
                    >{{ dateTime(slot.starts_at) }} —
                    {{ dateTime(slot.ends_at) }}</span
                ><span class="mt-2 block text-xs text-muted-foreground">{{
                    slot.available
                        ? `${slot.remaining} ${copy.remaining}`
                        : slot.reason
                }}</span
                ><span class="mt-2 block text-xs">{{ slot.location }}</span>
            </button>
        </div>
    </section>
</template>
