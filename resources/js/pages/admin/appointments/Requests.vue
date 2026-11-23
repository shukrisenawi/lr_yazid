<script setup lang="ts">
import { Head, Link, useForm } from '@inertiajs/vue3';
import Pagination from '@/components/Pagination.vue';
import AdminRecordAction from '@/components/AdminRecordAction.vue';
import AdminEditButton from '@/components/AdminEditButton.vue';
import { appointmentCopy as copy } from '@/lib/appointmentCopy';
import type { Appointment } from '@/types/appointment';
import type { Paginated } from '@/types/catalog';
import { dateTime } from '@/types/commerce';
const props = defineProps<{
    appointments: Paginated<Appointment>;
    services: { id: number; name: string }[];
    filters: {
        search?: string;
        status?: string;
        service?: number;
        from?: string;
        to?: string;
    };
}>();
const filter = useForm({
    search: props.filters.search ?? '',
    status: props.filters.status ?? '',
    service: props.filters.service ?? '',
    from: props.filters.from ?? '',
    to: props.filters.to ?? '',
});
</script>
<template>
    <Head :title="copy.requests" />
    <div class="p-6 md:p-10">
        <div class="flex flex-wrap justify-between gap-4">
            <h1 class="text-3xl font-semibold">{{ copy.requests }}</h1>
            <Link
                href="/admin/appointments/calendar"
                class="text-sm underline"
                >{{ copy.calendar }}</Link
            >
        </div>
        <form
            class="mt-8 flex flex-wrap items-end gap-4"
            @submit.prevent="filter.get('/admin/appointments')"
        >
            <label class="grid gap-2 text-sm"
                >{{ copy.searchBookings
                }}<input
                    v-model="filter.search"
                    type="search"
                    class="rounded-md border bg-transparent p-2.5" /></label
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
            ><label class="grid gap-2 text-sm"
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
            ><button class="rounded-md border px-4 py-2.5 text-sm">
                {{ copy.apply }}
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
                        <th class="p-4">{{ copy.reference }}</th>
                        <th class="p-4">{{ copy.customer }}</th>
                        <th class="p-4">{{ copy.membershipExpires }}</th>
                        <th class="p-4">{{ copy.start }}</th>
                        <th class="p-4">{{ copy.bookingStatus }}</th>
                        <th class="p-4">{{ copy.rescheduleRequests }}</th>
                        <th class="p-4">{{ copy.recordedAt }}</th>
                        <th class="p-4">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <tr
                        v-for="appointment in appointments.data"
                        :key="appointment.id"
                        class="border-t"
                    >
                        <td class="p-4">
                            <Link
                                :href="`/admin/appointments/${appointment.id}`"
                                class="font-medium underline"
                                >{{ appointment.reference }}</Link
                            >
                            <p class="mt-2 text-xs text-muted-foreground">
                                {{ appointment.service_name }}
                            </p>
                        </td>
                        <td class="p-4">
                            <p>{{ appointment.user?.name }}</p>
                            <p class="mt-1 text-xs text-muted-foreground">
                                {{ appointment.user?.email }}
                            </p>
                            <p class="mt-1 text-xs text-muted-foreground">
                                {{ appointment.user?.phone }}
                            </p>
                        </td>
                        <td class="p-4 whitespace-nowrap">
                            {{
                                appointment.user?.vip_expires_at
                                    ? dateTime(appointment.user.vip_expires_at)
                                    : copy.noActiveMembership
                            }}
                        </td>
                        <td class="p-4 whitespace-nowrap">
                            {{ dateTime(appointment.scheduled_starts_at) }}
                        </td>
                        <td class="p-4">
                            {{
                                copy.states[
                                    appointment.status as keyof typeof copy.states
                                ]
                            }}
                        </td>
                        <td class="p-4">
                            {{ appointment.pending_reschedules_count }}
                        </td>
                        <td class="p-4 whitespace-nowrap">
                            {{ dateTime(appointment.created_at) }}
                        </td>
                        <td class="p-4">
                            <div class="flex items-center gap-2">
                                <AdminEditButton
                                    :href="`/admin/appointments/${appointment.id}`"
                                    :label="`Edit ${appointment.reference}`"
                                />
                                <AdminRecordAction
                                    module="appointments"
                                    :record-id="appointment.id"
                                    :record-name="appointment.reference"
                                />
                            </div>
                        </td>
                    </tr>
                    <tr v-if="!appointments.data.length">
                        <td
                            colspan="8"
                            class="p-10 text-center text-muted-foreground"
                        >
                            {{ copy.emptyBookings }}
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
        <Pagination :links="appointments.links" />
    </div>
</template>
