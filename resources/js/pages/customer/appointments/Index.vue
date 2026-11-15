<script setup lang="ts">
import { Head, Link, useForm, usePage } from '@inertiajs/vue3';
import Pagination from '@/components/Pagination.vue';
import { appointmentCopy as copy } from '@/lib/appointmentCopy';
import type { Appointment } from '@/types/appointment';
import type { Paginated } from '@/types/catalog';
import { dateTime } from '@/types/commerce';
const props = defineProps<{
    appointments: Paginated<Appointment>;
    filters: { status?: string };
}>();
const filter = useForm({ status: props.filters.status ?? '' });
const page = usePage();
</script>
<template>
    <Head :title="copy.myAppointments" />
    <div class="mx-auto w-full max-w-5xl p-6 md:p-10">
        <div class="flex flex-wrap justify-between gap-4">
            <h1 class="text-3xl font-semibold">{{ copy.myAppointments }}</h1>
            <Link
                v-if="page.props.auth.isVip"
                href="/vip/appointments/book"
                class="rounded-md bg-emerald-800 px-5 py-2.5 text-white"
                >{{ copy.book }}</Link
            ><Link v-else href="/membership" class="text-sm underline">{{
                copy.renew
            }}</Link>
        </div>
        <form
            class="mt-6 flex items-end gap-3"
            @submit.prevent="filter.get('/appointments')"
        >
            <label class="grid gap-2 text-sm"
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
        <div class="mt-6 space-y-4">
            <article
                v-for="appointment in appointments.data"
                :key="appointment.id"
                class="rounded-xl border p-6"
            >
                <div class="flex flex-wrap justify-between gap-4">
                    <h2 class="font-semibold">
                        {{ appointment.service_name }}
                    </h2>
                    <span class="text-sm">{{
                        copy.states[
                            appointment.status as keyof typeof copy.states
                        ]
                    }}</span>
                </div>
                <p class="mt-3 text-sm">
                    {{ dateTime(appointment.scheduled_starts_at) }}
                </p>
                <p class="mt-2 text-xs text-muted-foreground">
                    {{ appointment.reference }}
                </p>
                <Link
                    :href="`/appointments/${appointment.id}`"
                    class="mt-4 inline-block text-sm underline"
                    >{{ copy.view }}</Link
                >
            </article>
            <p
                v-if="!appointments.data.length"
                class="rounded-xl border border-dashed p-10 text-center text-muted-foreground"
            >
                {{ copy.emptyBookings }}
            </p>
        </div>
        <Pagination :links="appointments.links" />
    </div>
</template>
