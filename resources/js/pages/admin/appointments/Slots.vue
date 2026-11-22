<script setup lang="ts">
import { Head, Link, useForm } from '@inertiajs/vue3';
import { ref } from 'vue';
import Pagination from '@/components/Pagination.vue';
import StatusBadge from '@/components/StatusBadge.vue';
import { appointmentCopy as copy } from '@/lib/appointmentCopy';
import type {
    AppointmentService,
    AppointmentSlot,
    SlotGeneration,
} from '@/types/appointment';
import type { Paginated } from '@/types/catalog';
import { dateTime } from '@/types/commerce';
import { localDateTime } from '@/types/program';
const props = defineProps<{
    service: AppointmentService;
    slots: Paginated<AppointmentSlot>;
    blockedDates: { id: number; date: string; reason: string }[];
    schedules: {
        id: number;
        created_count: number;
        skipped_count: number;
        created_at: string;
    }[];
    generation: SlotGeneration | null;
    filters: { from?: string; to?: string; status?: string };
}>();
const base = `/admin/appointment-services/${props.service.id}`;
const tab = ref<'slots' | 'generate' | 'blocked'>('slots');
const selected = ref<number | null>(null);
const showManual = ref(false);
const filter = useForm({
    from: props.filters.from ?? '',
    to: props.filters.to ?? '',
    status: props.filters.status ?? '',
});
const slot = useForm({
    starts_at: '',
    ends_at: '',
    capacity: props.service.default_capacity,
    location: props.service.location,
    notes: '',
    status: 'available',
});
const generator = useForm({
    from: '',
    to: '',
    weekdays: [1, 2, 3, 4, 5],
    day_start: '09:00',
    day_end: '17:00',
    break_start: '13:00',
    break_end: '14:00',
    duration_minutes: props.service.duration_minutes,
    capacity: props.service.default_capacity,
    location: props.service.location,
    preview: true,
});
const block = useForm({ date: '', reason: '' });
const action = useForm({});
function edit(row: AppointmentSlot) {
    selected.value = row.id;
    showManual.value = true;
    slot.starts_at = localDateTime(row.starts_at);
    slot.ends_at = localDateTime(row.ends_at);
    slot.capacity = row.capacity;
    slot.location = row.location;
    slot.notes = row.notes ?? '';
    slot.status = row.status;
    slot.clearErrors();
}
function create() {
    selected.value = null;
    slot.reset();
    slot.clearErrors();
    showManual.value = true;
}
function saveSlot() {
    const options = {
        preserveScroll: true,
        onSuccess: () => {
            showManual.value = false;
        },
    };
    if (selected.value) slot.put(`${base}/slots/${selected.value}`, options);
    else slot.post(`${base}/slots`, options);
}
function generate(preview: boolean) {
    generator.preview = preview;
    generator.post(`${base}/generate-slots`, { preserveScroll: true });
}
function remove(id: number) {
    if (confirm(copy.deleteConfirm))
        action.delete(`${base}/slots/${id}`, { preserveScroll: true });
}
</script>
<template>
    <Head :title="`${copy.slots} · ${service.name}`" />
    <div class="mx-auto w-full max-w-6xl p-6 md:p-10">
        <Link href="/admin/appointment-services" class="text-sm underline">{{
            copy.services
        }}</Link>
        <h1 class="mt-5 text-3xl font-semibold">
            {{ service.name }} · {{ copy.slots }}
        </h1>
        <p class="mt-3 text-sm text-muted-foreground">{{ copy.bookedHelp }}</p>
        <nav class="mt-8 flex flex-wrap gap-3" aria-label="Scheduling sections">
            <button
                v-for="value in ['slots', 'generate', 'blocked'] as const"
                :key="value"
                :aria-pressed="tab === value"
                :class="tab === value ? 'bg-emerald-800 text-white' : ''"
                class="rounded-md border px-4 py-2 text-sm"
                @click="tab = value"
            >
                {{ copy[value] }}
            </button>
        </nav>
        <p
            v-if="Object.keys(action.errors).length"
            role="alert"
            class="mt-5 text-sm text-red-700 dark:text-red-400"
        >
            {{ Object.values(action.errors).join(' ') }}
        </p>
        <section v-if="tab === 'slots'" class="mt-6">
            <form
                class="flex flex-wrap items-end gap-4"
                @submit.prevent="
                    filter.get(`${base}/slots`, { preserveState: true })
                "
            >
                <label class="grid gap-2 text-sm"
                    >{{ copy.from
                    }}<input
                        v-model="filter.from"
                        type="date"
                        class="rounded-md border bg-transparent p-2.5" /></label
                ><label class="grid gap-2 text-sm"
                    >{{ copy.to
                    }}<input
                        v-model="filter.to"
                        type="date"
                        class="rounded-md border bg-transparent p-2.5" /></label
                ><label class="grid gap-2 text-sm"
                    >{{ copy.status
                    }}<select
                        v-model="filter.status"
                        class="rounded-md border bg-background p-2.5"
                    >
                        <option value="">{{ copy.all }}</option>
                        <option value="available">{{ copy.available }}</option>
                        <option value="disabled">{{ copy.disabled }}</option>
                    </select></label
                ><button class="rounded-md border px-4 py-2.5 text-sm">
                    {{ copy.apply }}</button
                ><button
                    type="button"
                    class="rounded-md bg-emerald-800 px-4 py-2.5 text-sm text-white"
                    @click="create"
                >
                    {{ copy.manual }}
                </button>
            </form>
            <p
                v-if="Object.keys(filter.errors).length"
                role="alert"
                class="mt-4 text-sm text-red-700"
            >
                {{ Object.values(filter.errors).join(' ') }}
            </p>
            <div class="mt-6 overflow-x-auto rounded-xl border">
                <table class="w-full text-left text-sm">
                    <thead class="bg-muted">
                        <tr>
                            <th class="p-4">{{ copy.start }}</th>
                            <th class="p-4">{{ copy.end }}</th>
                            <th class="p-4">{{ copy.capacity }}</th>
                            <th class="p-4">{{ copy.status }}</th>
                            <th class="p-4">{{ copy.history }}</th>
                            <th class="p-4">{{ copy.edit }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr
                            v-for="row in slots.data"
                            :key="row.id"
                            class="border-t"
                        >
                            <td class="p-4 whitespace-nowrap">
                                {{ dateTime(row.starts_at) }}
                            </td>
                            <td class="p-4 whitespace-nowrap">
                                {{ dateTime(row.ends_at) }}
                            </td>
                            <td class="p-4">{{ row.capacity }}</td>
                            <td class="p-4">
                                <StatusBadge :status="row.status" />
                            </td>
                            <td class="p-4">{{ row.appointments_count }}</td>
                            <td class="p-4">
                                <div class="flex gap-4">
                                    <button
                                        class="underline"
                                        @click="edit(row)"
                                    >
                                        {{ copy.edit }}</button
                                    ><button
                                        v-if="
                                            !row.appointments_count &&
                                            !row.target_reschedules_exists &&
                                            !row.original_reschedules_exists &&
                                            !row.previous_histories_exists &&
                                            !row.new_histories_exists
                                        "
                                        :disabled="action.processing"
                                        class="whitespace-nowrap underline"
                                        @click="remove(row.id)"
                                    >
                                        {{ copy.delete }}
                                    </button>
                                </div>
                            </td>
                        </tr>
                        <tr v-if="!slots.data.length">
                            <td
                                colspan="6"
                                class="p-10 text-center text-muted-foreground"
                            >
                                {{ copy.noSlots }}
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
            <Pagination :links="slots.links" />
            <form
                v-if="showManual"
                class="mt-8 space-y-5 rounded-xl border p-6"
                @submit.prevent="saveSlot"
            >
                <h2 class="text-xl font-semibold">
                    {{ selected ? copy.editSlot : copy.manual }}
                </h2>
                <p
                    v-if="Object.keys(slot.errors).length"
                    role="alert"
                    class="text-sm text-red-700 dark:text-red-400"
                >
                    {{ Object.values(slot.errors).join(' ') }}
                </p>
                <div class="grid gap-5 sm:grid-cols-2">
                    <label class="grid gap-2 text-sm"
                        ><span
                            >{{ copy.start }}
                            <span class="text-red-600" aria-hidden="true"
                                >*</span
                            ></span
                        ><input
                            v-model="slot.starts_at"
                            type="datetime-local"
                            required
                            step="1"
                            class="rounded-md border bg-transparent p-2.5" /></label
                    ><label class="grid gap-2 text-sm"
                        ><span
                            >{{ copy.end }}
                            <span class="text-red-600" aria-hidden="true"
                                >*</span
                            ></span
                        ><input
                            v-model="slot.ends_at"
                            type="datetime-local"
                            required
                            step="1"
                            class="rounded-md border bg-transparent p-2.5" /></label
                    ><label class="grid gap-2 text-sm"
                        ><span
                            >{{ copy.capacity }}
                            <span class="text-red-600" aria-hidden="true"
                                >*</span
                            ></span
                        ><input
                            v-model="slot.capacity"
                            type="number"
                            min="1"
                            max="1000"
                            required
                            class="rounded-md border bg-transparent p-2.5" /></label
                    ><label class="grid gap-2 text-sm"
                        ><span
                            >{{ copy.status }}
                            <span class="text-red-600" aria-hidden="true"
                                >*</span
                            ></span
                        ><select
                            v-model="slot.status"
                            class="rounded-md border bg-background p-2.5"
                        >
                            <option value="available">
                                {{ copy.available }}
                            </option>
                            <option value="disabled">
                                {{ copy.disabled }}
                            </option>
                        </select></label
                    >
                </div>
                <label class="grid gap-2 text-sm"
                    ><span
                        >{{ copy.location }}
                        <span class="text-red-600" aria-hidden="true"
                            >*</span
                        ></span
                    ><input
                        v-model="slot.location"
                        required
                        maxlength="500"
                        class="rounded-md border bg-transparent p-2.5" /></label
                ><label class="grid gap-2 text-sm"
                    >{{ copy.notes
                    }}<textarea
                        v-model="slot.notes"
                        rows="3"
                        maxlength="2000"
                        class="rounded-md border bg-transparent p-3"
                    /></label
                ><button
                    :disabled="slot.processing"
                    class="rounded-md bg-emerald-800 px-5 py-2.5 text-white disabled:opacity-50"
                >
                    {{ slot.processing ? copy.saving : copy.saveSlot }}</button
                ><button
                    type="button"
                    class="ml-5 text-sm underline"
                    @click="showManual = false"
                >
                    {{ copy.cancel }}
                </button>
            </form>
        </section>
        <section v-if="tab === 'generate'" class="mt-6">
            <p class="max-w-3xl text-sm leading-relaxed text-muted-foreground">
                {{ copy.generatorHelp }}
            </p>
            <form
                class="mt-6 space-y-5 rounded-xl border p-6"
                @submit.prevent="generate(true)"
            >
                <p
                    v-if="Object.keys(generator.errors).length"
                    role="alert"
                    class="text-sm text-red-700 dark:text-red-400"
                >
                    {{ Object.values(generator.errors).join(' ') }}
                </p>
                <div class="grid gap-5 sm:grid-cols-2">
                    <label class="grid gap-2 text-sm"
                        ><span
                            >{{ copy.from }}
                            <span class="text-red-600" aria-hidden="true"
                                >*</span
                            ></span
                        ><input
                            v-model="generator.from"
                            type="date"
                            required
                            class="rounded-md border bg-transparent p-2.5" /></label
                    ><label class="grid gap-2 text-sm"
                        ><span
                            >{{ copy.to }}
                            <span class="text-red-600" aria-hidden="true"
                                >*</span
                            ></span
                        ><input
                            v-model="generator.to"
                            type="date"
                            required
                            class="rounded-md border bg-transparent p-2.5"
                    /></label>
                </div>
                <fieldset>
                    <legend class="mb-3 text-sm">
                        {{ copy.weekdays }}
                        <span class="text-red-600" aria-hidden="true">*</span>
                    </legend>
                    <div class="flex flex-wrap gap-4">
                        <label
                            v-for="(day, index) in copy.days"
                            :key="day"
                            class="flex items-center gap-2 text-sm"
                            ><input
                                v-model="generator.weekdays"
                                type="checkbox"
                                :value="index + 1"
                            />{{ day }}</label
                        >
                    </div>
                </fieldset>
                <div class="grid gap-5 sm:grid-cols-2">
                    <label class="grid gap-2 text-sm"
                        ><span
                            >{{ copy.dayStart }}
                            <span class="text-red-600" aria-hidden="true"
                                >*</span
                            ></span
                        ><input
                            v-model="generator.day_start"
                            type="time"
                            required
                            class="rounded-md border bg-transparent p-2.5" /></label
                    ><label class="grid gap-2 text-sm"
                        ><span
                            >{{ copy.dayEnd }}
                            <span class="text-red-600" aria-hidden="true"
                                >*</span
                            ></span
                        ><input
                            v-model="generator.day_end"
                            type="time"
                            required
                            class="rounded-md border bg-transparent p-2.5" /></label
                    ><label class="grid gap-2 text-sm"
                        ><span
                            >{{ copy.breakStart }}
                            <span
                                v-if="generator.break_end"
                                class="text-red-600"
                                aria-hidden="true"
                                >*</span
                            ></span
                        ><input
                            v-model="generator.break_start"
                            :required="!!generator.break_end"
                            type="time"
                            class="rounded-md border bg-transparent p-2.5" /></label
                    ><label class="grid gap-2 text-sm"
                        ><span
                            >{{ copy.breakEnd }}
                            <span
                                v-if="generator.break_start"
                                class="text-red-600"
                                aria-hidden="true"
                                >*</span
                            ></span
                        ><input
                            v-model="generator.break_end"
                            :required="!!generator.break_start"
                            type="time"
                            class="rounded-md border bg-transparent p-2.5" /></label
                    ><label class="grid gap-2 text-sm"
                        ><span
                            >{{ copy.duration }}
                            <span class="text-red-600" aria-hidden="true"
                                >*</span
                            ></span
                        ><input
                            v-model="generator.duration_minutes"
                            type="number"
                            min="5"
                            max="480"
                            required
                            class="rounded-md border bg-transparent p-2.5" /></label
                    ><label class="grid gap-2 text-sm"
                        ><span
                            >{{ copy.capacity }}
                            <span class="text-red-600" aria-hidden="true"
                                >*</span
                            ></span
                        ><input
                            v-model="generator.capacity"
                            type="number"
                            min="1"
                            max="1000"
                            required
                            class="rounded-md border bg-transparent p-2.5"
                    /></label>
                </div>
                <label class="grid gap-2 text-sm"
                    ><span
                        >{{ copy.location }}
                        <span class="text-red-600" aria-hidden="true"
                            >*</span
                        ></span
                    ><input
                        v-model="generator.location"
                        required
                        maxlength="500"
                        class="rounded-md border bg-transparent p-2.5"
                /></label>
                <div class="flex flex-wrap gap-4">
                    <button
                        :disabled="generator.processing"
                        class="rounded-md border px-5 py-2.5 disabled:opacity-50"
                    >
                        {{ copy.preview }}</button
                    ><button
                        type="button"
                        :disabled="generator.processing"
                        class="rounded-md bg-emerald-800 px-5 py-2.5 text-white disabled:opacity-50"
                        @click="generate(false)"
                    >
                        {{ generator.processing ? copy.saving : copy.generate }}
                    </button>
                </div>
            </form>
            <section
                v-if="generation"
                class="mt-6 rounded-xl border p-6"
                aria-live="polite"
            >
                <h2 class="text-xl font-semibold">
                    {{
                        generation.preview
                            ? copy.previewTitle
                            : copy.generatedTitle
                    }}
                </h2>
                <dl class="mt-5 grid gap-4 sm:grid-cols-3">
                    <div
                        v-for="key in [
                            'candidates',
                            'created',
                            'overlaps',
                            'breaks',
                            'blocked_days',
                            'past',
                        ] as const"
                        :key="key"
                    >
                        <dt class="text-xs text-muted-foreground">
                            {{
                                key === 'blocked_days'
                                    ? copy.blockedDays
                                    : copy[key]
                            }}
                        </dt>
                        <dd class="mt-1 text-2xl font-semibold">
                            {{ generation[key] }}
                        </dd>
                    </div>
                </dl>
                <p class="mt-5 text-xs text-muted-foreground">
                    {{ copy.previewHelp }}
                </p>
                <ul class="mt-4 max-h-64 space-y-2 overflow-auto text-sm">
                    <li v-for="row in generation.slots" :key="row.starts_at">
                        {{ dateTime(row.starts_at) }}
                        —
                        {{ dateTime(row.ends_at) }}
                    </li>
                </ul>
            </section>
            <section v-if="schedules.length" class="mt-8">
                <h2 class="text-xl font-semibold">{{ copy.runs }}</h2>
                <ul class="mt-4 space-y-3 text-sm">
                    <li v-for="run in schedules" :key="run.id">
                        {{ dateTime(run.created_at) }} · {{ copy.created }}:
                        {{ run.created_count }}
                    </li>
                </ul>
            </section>
        </section>
        <section v-if="tab === 'blocked'" class="mt-6">
            <p class="max-w-3xl text-sm text-muted-foreground">
                {{ copy.blockHelp }}
            </p>
            <form
                class="mt-6 flex flex-wrap items-end gap-4"
                @submit.prevent="
                    block.post(`${base}/blocked-dates`, {
                        preserveScroll: true,
                        onSuccess: () => block.reset(),
                    })
                "
            >
                <label class="grid gap-2 text-sm"
                    ><span
                        >{{ copy.date }}
                        <span class="text-red-600" aria-hidden="true"
                            >*</span
                        ></span
                    ><input
                        v-model="block.date"
                        type="date"
                        required
                        class="rounded-md border bg-transparent p-2.5" /></label
                ><label class="grid min-w-0 flex-1 gap-2 text-sm"
                    ><span
                        >{{ copy.reason }}
                        <span class="text-red-600" aria-hidden="true"
                            >*</span
                        ></span
                    ><input
                        v-model="block.reason"
                        required
                        maxlength="500"
                        class="rounded-md border bg-transparent p-2.5" /></label
                ><button
                    :disabled="block.processing"
                    class="rounded-md bg-emerald-800 px-5 py-2.5 text-white disabled:opacity-50"
                >
                    {{ copy.block }}
                </button>
            </form>
            <p
                v-if="Object.keys(block.errors).length"
                role="alert"
                class="mt-4 text-sm text-red-700 dark:text-red-400"
            >
                {{ Object.values(block.errors).join(' ') }}
            </p>
            <ul class="mt-6 space-y-3">
                <li
                    v-for="date in blockedDates"
                    :key="date.id"
                    class="flex flex-wrap items-center justify-between gap-4 rounded-md border p-4 text-sm"
                >
                    <span>{{ date.date }} · {{ date.reason }}</span
                    ><button
                        :disabled="action.processing"
                        class="underline"
                        @click="
                            action.delete(`${base}/blocked-dates/${date.id}`, {
                                preserveScroll: true,
                            })
                        "
                    >
                        {{ copy.unblock }}
                    </button>
                </li>
            </ul>
            <p
                v-if="!blockedDates.length"
                class="mt-6 text-sm text-muted-foreground"
            >
                {{ copy.noBlocked }}
            </p>
        </section>
    </div>
</template>
