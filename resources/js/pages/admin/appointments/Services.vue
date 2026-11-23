<script setup lang="ts">
import { Head, Link, useForm } from '@inertiajs/vue3';
import { ref } from 'vue';
import AdminRecordAction from '@/components/AdminRecordAction.vue';
import AdminEditButton from '@/components/AdminEditButton.vue';
import Pagination from '@/components/Pagination.vue';
import { appointmentCopy as copy } from '@/lib/appointmentCopy';
import type { AppointmentService } from '@/types/appointment';
import type { Paginated } from '@/types/catalog';
defineProps<{ services: Paginated<AppointmentService> }>();
const selected = ref<number | null>(null);
const form = useForm({
    name: '',
    description: '',
    duration_minutes: 30,
    default_capacity: 1,
    location: '',
    is_active: true,
    minimum_notice_hours: 24,
    maximum_advance_days: 90,
    cancellation_notice_hours: 24,
    maximum_active_bookings: 3,
});
function edit(service: AppointmentService) {
    selected.value = service.id;
    for (const key of [
        'name',
        'description',
        'duration_minutes',
        'default_capacity',
        'location',
        'is_active',
        'minimum_notice_hours',
        'maximum_advance_days',
        'cancellation_notice_hours',
        'maximum_active_bookings',
    ] as const) {
        Object.assign(form, { [key]: service[key] ?? '' });
    }
    form.clearErrors();
}
function save() {
    if (selected.value)
        form.put(`/admin/appointment-services/${selected.value}`, {
            preserveScroll: true,
        });
    else
        form.post('/admin/appointment-services', {
            preserveScroll: true,
            onSuccess: () => form.reset(),
        });
}
</script>
<template>
    <Head :title="copy.services" />
    <div class="mx-auto w-full max-w-6xl p-6 md:p-10">
        <h1 class="text-3xl font-semibold">{{ copy.services }}</h1>
        <p class="mt-3 text-sm text-muted-foreground">
            {{ copy.schedulingOnly }}
        </p>
        <div class="mt-8 grid gap-5 md:grid-cols-2">
            <article
                v-for="service in services.data"
                :key="service.id"
                class="rounded-xl border p-6"
            >
                <div class="flex items-start justify-between gap-4">
                    <h2 class="text-xl font-semibold">{{ service.name }}</h2>
                    <span class="text-xs text-muted-foreground">{{
                        service.is_active ? copy.active : copy.disabled
                    }}</span>
                </div>
                <p class="mt-3 text-sm text-muted-foreground">
                    {{ service.description }}
                </p>
                <p class="mt-4 text-sm">
                    {{ service.location }} · {{ service.duration_minutes }} min
                    · {{ copy.capacity }}: {{ service.default_capacity }}
                </p>
                <div class="mt-5 flex flex-wrap items-center gap-2 text-sm">
                    <AdminEditButton
                        :label="`Edit ${service.name}`"
                        @click="edit(service)"
                    />
                    <AdminRecordAction
                        module="appointment-services"
                        :record-id="service.id"
                        :record-name="service.name"
                        @success="
                            selected = null;
                            form.reset();
                        "
                    />
                    <Link
                        :href="`/admin/appointment-services/${service.id}/slots`"
                        class="underline"
                        >{{ copy.manage }} ({{ service.slots_count }})</Link
                    >
                </div>
            </article>
            <p
                v-if="!services.data.length"
                class="rounded-xl border border-dashed p-8 text-muted-foreground"
            >
                {{ copy.noServices }}
            </p>
        </div>
        <Pagination :links="services.links" />
        <form
            class="mt-10 space-y-6 rounded-xl border p-6"
            @submit.prevent="save"
        >
            <h2 class="text-2xl font-semibold">
                {{ selected ? copy.editService : copy.newService }}
            </h2>
            <p
                v-if="Object.keys(form.errors).length"
                role="alert"
                class="text-sm text-red-700 dark:text-red-400"
            >
                {{ Object.values(form.errors).join(' ') }}
            </p>
            <label class="grid gap-2 text-sm"
                ><span
                    >{{ copy.name }}
                    <span class="text-red-600" aria-hidden="true">*</span></span
                ><input
                    v-model="form.name"
                    required
                    maxlength="200"
                    class="rounded-md border bg-transparent p-2.5"
            /></label>
            <label class="grid gap-2 text-sm"
                >{{ copy.description
                }}<textarea
                    v-model="form.description"
                    maxlength="5000"
                    rows="3"
                    class="rounded-md border bg-transparent p-3"
                />
            </label>
            <label class="grid gap-2 text-sm"
                ><span
                    >{{ copy.location }}
                    <span class="text-red-600" aria-hidden="true">*</span></span
                ><input
                    v-model="form.location"
                    required
                    maxlength="500"
                    class="rounded-md border bg-transparent p-2.5"
            /></label>
            <div class="grid gap-5 sm:grid-cols-2">
                <label class="grid gap-2 text-sm"
                    ><span
                        >{{ copy.duration }}
                        <span class="text-red-600" aria-hidden="true"
                            >*</span
                        ></span
                    ><input
                        v-model="form.duration_minutes"
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
                        v-model="form.default_capacity"
                        type="number"
                        min="1"
                        max="1000"
                        required
                        class="rounded-md border bg-transparent p-2.5" /></label
                ><label class="grid gap-2 text-sm"
                    ><span
                        >{{ copy.notice }}
                        <span class="text-red-600" aria-hidden="true"
                            >*</span
                        ></span
                    ><input
                        v-model="form.minimum_notice_hours"
                        type="number"
                        min="0"
                        max="2160"
                        required
                        class="rounded-md border bg-transparent p-2.5" /></label
                ><label class="grid gap-2 text-sm"
                    ><span
                        >{{ copy.advance }}
                        <span class="text-red-600" aria-hidden="true"
                            >*</span
                        ></span
                    ><input
                        v-model="form.maximum_advance_days"
                        type="number"
                        min="1"
                        max="366"
                        required
                        class="rounded-md border bg-transparent p-2.5" /></label
                ><label class="grid gap-2 text-sm"
                    ><span
                        >{{ copy.cancellation }}
                        <span class="text-red-600" aria-hidden="true"
                            >*</span
                        ></span
                    ><input
                        v-model="form.cancellation_notice_hours"
                        type="number"
                        min="0"
                        max="2160"
                        required
                        class="rounded-md border bg-transparent p-2.5" /></label
                ><label class="grid gap-2 text-sm"
                    ><span
                        >{{ copy.maximum }}
                        <span class="text-red-600" aria-hidden="true"
                            >*</span
                        ></span
                    ><input
                        v-model="form.maximum_active_bookings"
                        type="number"
                        min="1"
                        max="100"
                        required
                        class="rounded-md border bg-transparent p-2.5"
                /></label>
            </div>
            <label class="flex items-center gap-2 text-sm"
                ><input v-model="form.is_active" type="checkbox" />{{
                    copy.active
                }}</label
            >
            <div class="flex flex-wrap gap-5">
                <button
                    :disabled="form.processing"
                    class="rounded-md bg-emerald-800 px-5 py-2.5 text-white disabled:opacity-50"
                >
                    {{ form.processing ? copy.saving : copy.save }}</button
                ><button
                    v-if="selected"
                    type="button"
                    class="text-sm underline"
                    @click="
                        selected = null;
                        form.reset();
                        form.clearErrors();
                    "
                >
                    {{ copy.createAnother }}
                </button>
            </div>
        </form>
    </div>
</template>
