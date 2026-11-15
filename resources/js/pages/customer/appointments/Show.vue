<script setup lang="ts">
import { Head, Link, useForm } from '@inertiajs/vue3';
import AppointmentDetails from '@/components/AppointmentDetails.vue';
import { appointmentCopy as copy } from '@/lib/appointmentCopy';
import type { Appointment } from '@/types/appointment';
import { dateTime } from '@/types/commerce';
defineProps<{
    appointment: Appointment;
    canReschedule: boolean;
    cancellationNoticeHours: number;
}>();
const cancel = useForm({ reason: '' });
const response = useForm({ accept: true });
</script>
<template>
    <Head :title="appointment.reference" />
    <div class="mx-auto w-full max-w-4xl p-6 md:p-10">
        <Link href="/appointments" class="text-sm underline">{{
            copy.myAppointments
        }}</Link>
        <div class="mt-6">
            <AppointmentDetails :appointment="appointment" />
        </div>
        <Link
            v-if="
                canReschedule &&
                !appointment.reschedule_requests?.some((request) =>
                    ['pending', 'proposed'].includes(request.status),
                )
            "
            :href="`/vip/appointments/book?reschedule=${appointment.id}`"
            class="mt-6 inline-block rounded-md bg-emerald-800 px-5 py-3 text-white"
            >{{ copy.reschedule }}</Link
        >
        <section v-if="appointment.reschedule_requests?.length" class="mt-8">
            <h2 class="text-xl font-semibold">{{ copy.rescheduleRequests }}</h2>
            <article
                v-for="request in appointment.reschedule_requests"
                :key="request.id"
                class="mt-4 rounded-xl border p-5"
            >
                <p class="text-sm font-medium">
                    {{
                        copy.requestStates[
                            request.status as keyof typeof copy.requestStates
                        ]
                    }}
                    · {{ dateTime(request.target_starts_at) }} —
                    {{ dateTime(request.target_ends_at) }}
                </p>
                <p class="mt-3 text-sm whitespace-pre-wrap">
                    {{ request.reason }}
                </p>
                <p v-if="request.review_remarks" class="mt-3 text-sm">
                    {{ request.review_remarks }}
                </p>
                <form
                    v-if="request.status === 'proposed'"
                    class="mt-4"
                    @submit.prevent="
                        response.accept = true;
                        response.post(
                            `/appointment-reschedules/${request.id}/respond`,
                        );
                    "
                >
                    <p class="mb-4 text-xs text-muted-foreground">
                        {{ copy.proposalHelp }}
                    </p>
                    <button
                        :disabled="response.processing"
                        class="rounded-md bg-emerald-800 px-4 py-2 text-sm text-white"
                    >
                        {{ copy.accept }}</button
                    ><button
                        type="button"
                        :disabled="response.processing"
                        class="ml-4 text-sm underline"
                        @click="
                            response.accept = false;
                            response.post(
                                `/appointment-reschedules/${request.id}/respond`,
                            );
                        "
                    >
                        {{ copy.decline }}
                    </button>
                </form>
            </article>
            <p
                v-if="Object.keys(response.errors).length"
                role="alert"
                class="mt-4 text-sm text-red-700"
            >
                {{ Object.values(response.errors).join(' ') }}
            </p>
        </section>
        <form
            v-if="
                ['pending', 'approved'].includes(appointment.status) &&
                new Date(appointment.scheduled_starts_at).getTime() >=
                    Date.now() + cancellationNoticeHours * 3600000
            "
            class="mt-8 space-y-4 rounded-xl border p-6"
            @submit.prevent="
                cancel.post(`/appointments/${appointment.id}/cancel`)
            "
        >
            <h2 class="text-xl font-semibold">{{ copy.cancelBooking }}</h2>
            <p class="text-xs text-muted-foreground">
                {{ copy.cancellationPolicy }}: {{ cancellationNoticeHours }}
            </p>
            <label class="grid gap-2 text-sm"
                >{{ copy.cancelReason
                }}<textarea
                    v-model="cancel.reason"
                    required
                    minlength="5"
                    maxlength="1000"
                    rows="3"
                    class="rounded-md border bg-transparent p-3"
                />
            </label>
            <p
                v-if="Object.keys(cancel.errors).length"
                role="alert"
                class="text-sm text-red-700"
            >
                {{ Object.values(cancel.errors).join(' ') }}
            </p>
            <button
                :disabled="cancel.processing"
                class="rounded-md border px-5 py-2.5 text-sm"
            >
                {{ copy.confirmCancel }}
            </button>
        </form>
    </div>
</template>
