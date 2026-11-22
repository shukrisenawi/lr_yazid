<script setup lang="ts">
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import { ref } from 'vue';
import AppointmentCalendar from '@/components/AppointmentCalendar.vue';
import { appointmentCopy as copy } from '@/lib/appointmentCopy';
import type { AvailableAppointmentSlot } from '@/types/appointment';
const props = defineProps<{
    services: { id: number; name: string; description: string | null }[];
    serviceId: number | null;
    month: string;
    slots: AvailableAppointmentSlot[];
    bookingKey: string;
    reschedule: { id: number; reference: string } | null;
}>();
const service = ref(props.serviceId);
const loading = ref(false);
const form = useForm({
    slot_id: null as number | null,
    booking_key: props.bookingKey,
    purpose: '',
    notes: '',
    reason: '',
});
function navigate(month = props.month) {
    form.slot_id = null;
    loading.value = true;
    router.get(
        '/vip/appointments/book',
        { service: service.value, month, reschedule: props.reschedule?.id },
        {
            preserveState: true,
            onFinish: () => {
                loading.value = false;
            },
        },
    );
}
function submit() {
    form.post(
        props.reschedule
            ? `/appointments/${props.reschedule.id}/reschedule`
            : '/vip/appointments',
    );
}
</script>
<template>
    <Head :title="reschedule ? copy.reschedule : copy.book" />
    <div class="mx-auto w-full max-w-6xl p-6 md:p-10">
        <Link href="/appointments" class="text-sm underline">{{
            copy.myAppointments
        }}</Link>
        <h1 class="mt-5 text-3xl font-semibold">
            {{ reschedule ? copy.reschedule : copy.book }}
        </h1>
        <p class="mt-3 text-sm text-muted-foreground">
            {{ reschedule ? copy.rescheduleHelp : copy.pendingHelp }}
        </p>
        <p v-if="!services.length" class="mt-8 rounded-xl border p-8">
            {{ copy.noServicesBook }}
        </p>
        <template v-else
            ><label class="mt-6 grid max-w-md gap-2 text-sm"
                ><span
                    >{{ copy.chooseService }}
                    <span class="text-red-600" aria-hidden="true">*</span></span
                ><select
                    v-model="service"
                    :disabled="loading || !!reschedule"
                    class="rounded-md border bg-background p-2.5"
                    @change="navigate()"
                >
                    <option
                        v-for="option in services"
                        :key="option.id"
                        :value="option.id"
                    >
                        {{ option.name }}
                    </option>
                </select></label
            >
            <div class="mt-6 grid items-start gap-8 lg:grid-cols-[1.5fr_1fr]">
                <AppointmentCalendar
                    v-model="form.slot_id"
                    :month="month"
                    :slots="slots"
                    :loading="loading"
                    @month="navigate"
                />
                <form
                    class="space-y-5 rounded-xl border p-6"
                    @submit.prevent="submit"
                >
                    <p
                        v-if="Object.keys(form.errors).length"
                        role="alert"
                        class="text-sm text-red-700 dark:text-red-400"
                    >
                        {{ Object.values(form.errors).join(' ') }}
                    </p>
                    <label v-if="reschedule" class="grid gap-2 text-sm"
                        ><span
                            >{{ copy.rescheduleReason }}
                            <span class="text-red-600" aria-hidden="true"
                                >*</span
                            ></span
                        ><textarea
                            v-model="form.reason"
                            required
                            minlength="5"
                            maxlength="1000"
                            rows="4"
                            class="rounded-md border bg-transparent p-3"
                        /></label
                    ><template v-else
                        ><label class="grid gap-2 text-sm"
                            ><span
                                >{{ copy.purpose }}
                                <span class="text-red-600" aria-hidden="true"
                                    >*</span
                                ></span
                            ><textarea
                                v-model="form.purpose"
                                required
                                minlength="5"
                                maxlength="1000"
                                rows="4"
                                class="rounded-md border bg-transparent p-3"
                            /></label
                        ><label class="grid gap-2 text-sm"
                            >{{ copy.additionalNotes
                            }}<textarea
                                v-model="form.notes"
                                maxlength="2000"
                                rows="3"
                                class="rounded-md border bg-transparent p-3"
                            /></label
                    ></template>
                    <p class="text-xs text-muted-foreground">
                        {{ copy.pendingHelp }}
                    </p>
                    <button
                        :disabled="!form.slot_id || form.processing || loading"
                        class="w-full rounded-md bg-emerald-800 px-5 py-3 text-white disabled:opacity-50"
                    >
                        {{
                            form.processing
                                ? copy.submitting
                                : copy.submitRequest
                        }}
                    </button>
                </form>
            </div></template
        >
    </div>
</template>
