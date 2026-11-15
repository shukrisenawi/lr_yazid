<script setup lang="ts">
import { appointmentCopy as copy } from '@/lib/appointmentCopy';
import type { Appointment } from '@/types/appointment';
import { dateTime } from '@/types/commerce';
defineProps<{ appointment: Appointment }>();
</script>
<template>
    <section class="rounded-xl border p-6">
        <div class="flex flex-wrap justify-between gap-4">
            <h1 class="text-2xl font-semibold">{{ appointment.reference }}</h1>
            <span class="h-fit rounded-md border px-3 py-1 text-sm">{{
                copy.states[appointment.status as keyof typeof copy.states]
            }}</span>
        </div>
        <h2 class="mt-6 text-xl font-medium">{{ appointment.service_name }}</h2>
        <p class="mt-3 text-sm">
            {{ dateTime(appointment.scheduled_starts_at) }} —
            {{ dateTime(appointment.scheduled_ends_at) }}
        </p>
        <p class="mt-2 text-sm text-muted-foreground">
            {{ appointment.location }}
        </p>
        <dl class="mt-6 space-y-4 text-sm">
            <div>
                <dt class="font-medium">{{ copy.purpose }}</dt>
                <dd class="mt-1 whitespace-pre-wrap text-muted-foreground">
                    {{ appointment.purpose }}
                </dd>
            </div>
            <div v-if="appointment.notes">
                <dt class="font-medium">{{ copy.additionalNotes }}</dt>
                <dd class="mt-1 whitespace-pre-wrap text-muted-foreground">
                    {{ appointment.notes }}
                </dd>
            </div>
            <div v-if="appointment.admin_remarks">
                <dt class="font-medium">{{ copy.adminRemarks }}</dt>
                <dd class="mt-1 whitespace-pre-wrap text-muted-foreground">
                    {{ appointment.admin_remarks }}
                </dd>
            </div>
            <div v-if="appointment.cancellation_reason">
                <dt class="font-medium">{{ copy.cancelReason }}</dt>
                <dd class="mt-1 whitespace-pre-wrap text-muted-foreground">
                    {{ appointment.cancellation_reason }}
                </dd>
            </div>
        </dl>
    </section>
    <section v-if="appointment.histories?.length" class="mt-8">
        <h2 class="text-xl font-semibold">{{ copy.historyTitle }}</h2>
        <ol class="mt-4 space-y-3">
            <li
                v-for="event in appointment.histories"
                :key="event.id"
                class="rounded-md border p-4 text-sm"
            >
                <p class="font-medium">
                    {{ event.event.replaceAll('_', ' ') }}
                </p>
                <p class="mt-1 text-xs text-muted-foreground">
                    {{ dateTime(event.created_at) }} ·
                    {{
                        copy.states[
                            event.new_status as keyof typeof copy.states
                        ]
                    }}
                    · #{{ event.changed_by }}
                </p>
                <p v-if="event.remarks" class="mt-2 whitespace-pre-wrap">
                    {{ event.remarks }}
                </p>
                <p
                    v-if="
                        event.previous_starts_at &&
                        event.new_starts_at &&
                        event.previous_starts_at !== event.new_starts_at
                    "
                    class="mt-2 text-xs text-muted-foreground"
                >
                    {{ dateTime(event.previous_starts_at) }} →
                    {{ dateTime(event.new_starts_at) }}
                </p>
            </li>
        </ol>
    </section>
</template>
