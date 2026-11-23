<script setup lang="ts">
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import { computed, ref, watch } from 'vue';
import AdminRecordAction from '@/components/AdminRecordAction.vue';
import AppointmentCalendar from '@/components/AppointmentCalendar.vue';
import AppointmentDetails from '@/components/AppointmentDetails.vue';
import { appointmentCopy as copy } from '@/lib/appointmentCopy';
import type {
    Appointment,
    AvailableAppointmentSlot,
} from '@/types/appointment';
import { dateTime } from '@/types/commerce';
const props = defineProps<{
    appointment: Appointment;
    membershipExpiresAt: string | null;
    slots: AvailableAppointmentSlot[];
    month: string;
}>();
const decision = useForm({
    decision: props.appointment.status === 'pending' ? 'approved' : 'cancelled',
    remarks: '',
});
const move = useForm({ approve: true, remarks: '' });
const proposal = useForm({ slot_id: null as number | null, reason: '' });
const loading = ref(false);
const terminal = computed(
    () => !['pending', 'approved'].includes(props.appointment.status),
);
watch(
    () => props.appointment.status,
    (status) => {
        decision.decision = status === 'pending' ? 'approved' : 'cancelled';
    },
);
function navigate(month: string) {
    loading.value = true;
    proposal.slot_id = null;
    router.get(
        `/admin/appointments/${props.appointment.id}`,
        { month },
        {
            preserveState: true,
            onFinish: () => {
                loading.value = false;
            },
        },
    );
}
</script>
<template>
    <Head :title="`${copy.review} · ${appointment.reference}`" />
    <div class="mx-auto w-full max-w-6xl p-6 md:p-10">
        <Link href="/admin/appointments" class="text-sm underline">{{
            copy.requests
        }}</Link>
        <div class="mt-6 grid items-start gap-8 lg:grid-cols-[1.5fr_1fr]">
            <div><AppointmentDetails :appointment="appointment" /></div>
            <aside class="space-y-6">
                <AdminRecordAction
                    module="appointments"
                    :record-id="appointment.id"
                />
                <section class="rounded-xl border p-6">
                    <h2 class="font-semibold">{{ appointment.user?.name }}</h2>
                    <p class="mt-3 text-sm">{{ appointment.user?.email }}</p>
                    <p class="mt-2 text-sm">{{ appointment.user?.phone }}</p>
                    <h3 class="mt-5 text-sm font-medium">
                        {{ copy.membershipExpires }}
                    </h3>
                    <p class="mt-2 text-sm">
                        {{
                            membershipExpiresAt
                                ? dateTime(membershipExpiresAt)
                                : copy.noActiveMembership
                        }}
                    </p>
                    <p class="mt-5 text-xs text-muted-foreground">
                        {{ copy.recordedAt }}:
                        {{ dateTime(appointment.created_at) }}
                    </p>
                </section>
                <form
                    v-if="!terminal"
                    class="space-y-4 rounded-xl border p-6"
                    @submit.prevent="
                        decision.put(`/admin/appointments/${appointment.id}`)
                    "
                >
                    <h2 class="text-xl font-semibold">{{ copy.review }}</h2>
                    <label class="grid gap-2 text-sm"
                        ><span
                            >{{ copy.decision }}
                            <span class="text-red-600" aria-hidden="true"
                                >*</span
                            ></span
                        ><select
                            v-model="decision.decision"
                            class="rounded-md border bg-background p-2.5"
                        >
                            <template v-if="appointment.status === 'pending'"
                                ><option value="approved">
                                    {{ copy.approve }}
                                </option>
                                <option value="rejected">
                                    {{ copy.reject }}
                                </option></template
                            >
                            <option value="cancelled">
                                {{ copy.cancelBooking }}
                            </option>
                            <template
                                v-if="
                                    appointment.status === 'approved' &&
                                    new Date(
                                        appointment.scheduled_ends_at,
                                    ).getTime() <= Date.now()
                                "
                                ><option value="completed">
                                    {{ copy.complete }}
                                </option>
                                <option value="no_show">
                                    {{ copy.noShow }}
                                </option></template
                            >
                        </select></label
                    ><label class="grid gap-2 text-sm"
                        ><span
                            >{{ copy.adminRemarks }}
                            <span
                                v-if="decision.decision === 'cancelled'"
                                class="text-red-600"
                                aria-hidden="true"
                                >*</span
                            ></span
                        ><textarea
                            v-model="decision.remarks"
                            :required="decision.decision === 'cancelled'"
                            maxlength="1000"
                            rows="3"
                            class="rounded-md border bg-transparent p-3"
                        />
                    </label>
                    <p
                        v-if="Object.keys(decision.errors).length"
                        role="alert"
                        class="text-sm text-red-700"
                    >
                        {{ Object.values(decision.errors).join(' ') }}
                    </p>
                    <button
                        :disabled="decision.processing"
                        class="rounded-md bg-emerald-800 px-5 py-2.5 text-white disabled:opacity-50"
                    >
                        {{ copy.saveDecision }}
                    </button>
                </form>
            </aside>
        </div>
        <section v-if="appointment.reschedule_requests?.length" class="mt-8">
            <h2 class="text-xl font-semibold">{{ copy.rescheduleRequests }}</h2>
            <article
                v-for="request in appointment.reschedule_requests"
                :key="request.id"
                class="mt-4 rounded-xl border p-6"
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
                <p class="mt-3 text-sm">{{ request.reason }}</p>
                <p v-if="request.review_remarks" class="mt-3 text-sm">
                    {{ request.review_remarks }}
                </p>
                <form
                    v-if="request.status === 'pending' && !terminal"
                    class="mt-5 space-y-4"
                    @submit.prevent="
                        move.approve = true;
                        move.put(
                            `/admin/appointment-reschedules/${request.id}`,
                        );
                    "
                >
                    <label class="grid gap-2 text-sm"
                        >{{ copy.adminRemarks
                        }}<textarea
                            v-model="move.remarks"
                            maxlength="1000"
                            rows="2"
                            class="rounded-md border bg-transparent p-3"
                        />
                    </label>
                    <div class="flex flex-wrap gap-4">
                        <button
                            :disabled="move.processing"
                            class="rounded-md bg-emerald-800 px-4 py-2 text-sm text-white"
                        >
                            {{ copy.approveMove }}</button
                        ><button
                            type="button"
                            :disabled="move.processing"
                            class="text-sm underline"
                            @click="
                                move.approve = false;
                                move.put(
                                    `/admin/appointment-reschedules/${request.id}`,
                                );
                            "
                        >
                            {{ copy.rejectMove }}
                        </button>
                    </div>
                </form>
            </article>
            <p
                v-if="Object.keys(move.errors).length"
                role="alert"
                class="mt-4 text-sm text-red-700"
            >
                {{ Object.values(move.errors).join(' ') }}
            </p>
        </section>
        <section
            v-if="
                !terminal &&
                !appointment.reschedule_requests?.some((request) =>
                    ['pending', 'proposed'].includes(request.status),
                )
            "
            class="mt-10"
        >
            <h2 class="text-2xl font-semibold">{{ copy.propose }}</h2>
            <p class="mt-3 text-sm text-muted-foreground">
                {{ copy.proposalHelp }}
            </p>
            <div class="mt-6 grid items-start gap-8 lg:grid-cols-[1.5fr_1fr]">
                <AppointmentCalendar
                    v-model="proposal.slot_id"
                    :month="month"
                    :slots="slots"
                    :loading="loading"
                    @month="navigate"
                />
                <form
                    class="space-y-4 rounded-xl border p-6"
                    @submit.prevent="
                        proposal.post(
                            `/admin/appointments/${appointment.id}/propose`,
                        )
                    "
                >
                    <label class="grid gap-2 text-sm"
                        ><span
                            >{{ copy.proposalReason }}
                            <span class="text-red-600" aria-hidden="true"
                                >*</span
                            ></span
                        ><textarea
                            v-model="proposal.reason"
                            required
                            minlength="5"
                            maxlength="1000"
                            rows="4"
                            class="rounded-md border bg-transparent p-3"
                        />
                    </label>
                    <p
                        v-if="Object.keys(proposal.errors).length"
                        role="alert"
                        class="text-sm text-red-700"
                    >
                        {{ Object.values(proposal.errors).join(' ') }}
                    </p>
                    <button
                        :disabled="
                            !proposal.slot_id || proposal.processing || loading
                        "
                        class="rounded-md bg-emerald-800 px-5 py-2.5 text-white disabled:opacity-50"
                    >
                        {{ copy.propose }}
                    </button>
                </form>
            </div>
        </section>
    </div>
</template>
