<script setup lang="ts">
import { Head, Link, useForm } from '@inertiajs/vue3';
import { computed } from 'vue';
import { calendarDays, malaysiaDate } from '@/lib/appointmentCalendar';
import { appointmentCopy as copy } from '@/lib/appointmentCopy';
import type { Appointment } from '@/types/appointment';
import { dateTime } from '@/types/commerce';
const props = defineProps<{
    appointments: Appointment[];
    truncated: boolean;
    services: { id: number; name: string }[];
    filters: { date: string; view: string; service?: number; status?: string };
    blockedDates: { id: number; date: string; reason: string }[];
    slots: {
        id: number;
        starts_at: string;
        capacity: number;
        approved_count: number;
        status: string;
    }[];
}>();
const filter = useForm({
    date: props.filters.date,
    view: props.filters.view,
    service: props.filters.service ?? '',
    status: props.filters.status ?? '',
});
const days = computed(() => {
    if (props.filters.view === 'month' || props.filters.view === 'list')
        return calendarDays(props.filters.date.slice(0, 7));
    const date = new Date(`${props.filters.date}T00:00:00Z`);
    if (props.filters.view === 'week')
        date.setUTCDate(date.getUTCDate() - ((date.getUTCDay() + 6) % 7));
    return Array.from(
        { length: props.filters.view === 'day' ? 1 : 7 },
        (_, index) =>
            new Date(date.getTime() + index * 86400000)
                .toISOString()
                .slice(0, 10),
    );
});
const entries = computed(() => {
    const map: Record<string, Appointment[]> = {};
    for (const appointment of props.appointments) {
        const key = malaysiaDate(appointment.scheduled_starts_at);
        (map[key] ??= []).push(appointment);
    }
    return map;
});
const full = computed(() => {
    const map: Record<string, number> = {};
    for (const slot of props.slots)
        if (
            slot.status === 'available' &&
            slot.approved_count >= slot.capacity
        ) {
            const key = malaysiaDate(slot.starts_at);
            map[key] = (map[key] ?? 0) + 1;
        }
    return map;
});
</script>
<template>
    <Head :title="copy.calendar" />
    <div class="p-6 md:p-10">
        <div class="flex flex-wrap justify-between gap-4">
            <h1 class="text-3xl font-semibold">{{ copy.calendar }}</h1>
            <Link href="/admin/appointments" class="text-sm underline">{{
                copy.requests
            }}</Link>
        </div>
        <form
            class="mt-8 flex flex-wrap items-end gap-4"
            @submit.prevent="filter.get('/admin/appointments/calendar')"
        >
            <label class="grid gap-2 text-sm"
                >{{ copy.date
                }}<input
                    v-model="filter.date"
                    type="date"
                    required
                    class="rounded-md border bg-transparent p-2.5" /></label
            ><label class="grid gap-2 text-sm"
                >{{ copy.calendarView
                }}<select
                    v-model="filter.view"
                    class="rounded-md border bg-background p-2.5"
                >
                    <option
                        v-for="value in [
                            'month',
                            'week',
                            'day',
                            'list',
                        ] as const"
                        :key="value"
                        :value="value"
                    >
                        {{ copy[value] }}
                    </option>
                </select></label
            ><label class="grid gap-2 text-sm"
                >{{ copy.chooseService
                }}<select
                    v-model="filter.service"
                    class="rounded-md border bg-background p-2.5"
                >
                    <option value="">{{ copy.allServices }}</option>
                    <option
                        v-for="service in services"
                        :key="service.id"
                        :value="service.id"
                    >
                        {{ service.name }}
                    </option>
                </select></label
            ><label class="grid gap-2 text-sm"
                >{{ copy.bookingStatus
                }}<select
                    v-model="filter.status"
                    class="rounded-md border bg-background p-2.5"
                >
                    <option value="">{{ copy.allStatuses }}</option>
                    <option
                        v-for="(label, value) in copy.states"
                        :key="value"
                        :value="value"
                    >
                        {{ label }}
                    </option>
                </select></label
            ><button class="rounded-md border px-4 py-2.5 text-sm">
                {{ copy.apply }}
            </button>
        </form>
        <p
            v-if="truncated"
            role="status"
            class="mt-5 text-sm text-muted-foreground"
        >
            {{ copy.calendarLimit }}
        </p>
        <div
            v-if="filters.view === 'month'"
            class="mt-6 grid grid-cols-7 text-center text-xs text-muted-foreground"
        >
            <span v-for="day in copy.days" :key="day" :title="day">{{
                day.slice(0, 3)
            }}</span>
        </div>
        <div
            class="mt-6 grid gap-1"
            :class="
                filters.view === 'month'
                    ? 'grid-cols-7'
                    : filters.view === 'week'
                      ? 'grid-cols-1 lg:grid-cols-7'
                      : 'grid-cols-1'
            "
        >
            <section
                v-for="day in days"
                :key="day"
                class="min-w-0 rounded-md border p-1.5 sm:p-3"
                :class="filters.view === 'month' ? 'min-h-28' : ''"
            >
                <h2 class="truncate text-xs font-semibold" :title="day">
                    {{ filters.view === 'month' ? day.slice(5) : day }}
                </h2>
                <p v-if="full[day]" class="mt-2 text-xs text-muted-foreground">
                    {{ copy.fullSlots }}: {{ full[day] }}
                </p>
                <p
                    v-for="block in blockedDates.filter(
                        (block) => block.date === day,
                    )"
                    :key="block.id"
                    class="mt-2 truncate text-xs text-red-700 dark:text-red-400"
                    :title="block.reason"
                >
                    {{ copy.blockedLabel }}: {{ block.reason }}
                </p>
                <Link
                    v-for="appointment in entries[day] ?? []"
                    :key="appointment.id"
                    :href="`/admin/appointments/${appointment.id}`"
                    class="mt-2 block min-w-0 rounded-sm bg-muted p-1.5 text-xs hover:underline"
                    :title="`${appointment.reference}: ${appointment.user?.name} · ${dateTime(appointment.scheduled_starts_at)}`"
                    ><span class="block truncate font-medium">{{
                        appointment.user?.name
                    }}</span
                    ><span class="block truncate">{{
                        copy.states[
                            appointment.status as keyof typeof copy.states
                        ]
                    }}</span
                    ><span v-if="filters.view !== 'month'" class="mt-1 block"
                        >{{ dateTime(appointment.scheduled_starts_at) }} ·
                        {{ appointment.service_name }}</span
                    ></Link
                >
            </section>
        </div>
    </div>
</template>
