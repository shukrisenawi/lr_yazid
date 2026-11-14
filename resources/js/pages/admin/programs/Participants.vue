<script setup lang="ts">
import { Head, Link, useForm } from '@inertiajs/vue3';
import Pagination from '@/components/Pagination.vue';
import StatusBadge from '@/components/StatusBadge.vue';
import { programCopy as copy } from '@/lib/programCopy';
import type { Paginated } from '@/types/catalog';
import { dateTime } from '@/types/commerce';
import type { VipProgram, ProgramRegistration } from '@/types/program';
const props = defineProps<{
    program: VipProgram;
    registrations: Paginated<ProgramRegistration>;
    filters: { status?: string; attendance?: string };
}>();
const filter = useForm({
    status: props.filters.status ?? '',
    attendance: props.filters.attendance ?? '',
});
const form = useForm({ attendance: 'unmarked' });
function attendance(id: number, event: Event) {
    form.attendance = (event.target as HTMLSelectElement).value;
    form.put(
        `/admin/programs/${props.program.id}/participants/${id}/attendance`,
        { preserveScroll: true },
    );
}
</script>
<template>
    <Head :title="`${copy.participants} · ${program.title}`" />
    <div class="p-6 md:p-10">
        <Link
            :href="`/admin/programs/${program.id}/edit`"
            class="text-sm underline"
            >{{ copy.edit }}</Link
        >
        <div class="mt-5 flex flex-wrap items-center justify-between gap-4">
            <h1 class="text-3xl font-semibold">
                {{ program.title }} · {{ copy.participants }}
            </h1>
            <a
                :href="`/admin/programs/${program.id}/export`"
                class="rounded-md border px-4 py-2 text-sm"
                >{{ copy.export }}</a
            >
        </div>
        <form
            class="mt-8 flex flex-wrap items-end gap-4"
            @submit.prevent="
                filter.get(`/admin/programs/${program.id}/participants`)
            "
        >
            <label class="grid gap-2 text-sm"
                >{{ copy.status
                }}<select
                    v-model="filter.status"
                    class="rounded-md border bg-background p-2.5"
                >
                    <option value="">{{ copy.all }}</option>
                    <option value="registered">{{ copy.registered }}</option>
                    <option value="cancelled">
                        {{ copy.states.cancelled }}
                    </option>
                </select></label
            ><label class="grid gap-2 text-sm"
                >{{ copy.attendance
                }}<select
                    v-model="filter.attendance"
                    class="rounded-md border bg-background p-2.5"
                >
                    <option value="">{{ copy.allAttendance }}</option>
                    <option
                        v-for="(label, value) in copy.attendanceStates"
                        :key="value"
                        :value="value"
                    >
                        {{ label }}
                    </option>
                </select></label
            ><button
                :disabled="filter.processing"
                class="rounded-md border px-4 py-2.5 text-sm"
            >
                {{ copy.apply }}
            </button>
        </form>
        <p
            v-if="Object.keys(form.errors).length"
            role="alert"
            class="mt-4 text-sm text-red-700 dark:text-red-400"
        >
            {{ Object.values(form.errors).join(' ') }}
        </p>
        <p class="mt-5 text-xs text-muted-foreground">
            {{ copy.attendanceHelp }}
        </p>
        <div class="mt-5 overflow-x-auto rounded-xl border">
            <table class="w-full text-left text-sm">
                <thead class="bg-muted">
                    <tr>
                        <th class="p-4">{{ copy.member }}</th>
                        <th class="p-4">{{ copy.reference }}</th>
                        <th class="p-4">{{ copy.status }}</th>
                        <th class="p-4">{{ copy.attendance }}</th>
                        <th class="p-4">{{ copy.registeredAt }}</th>
                    </tr>
                </thead>
                <tbody>
                    <tr
                        v-for="registration in registrations.data"
                        :key="registration.id"
                        class="border-t"
                    >
                        <td class="p-4">
                            <p class="font-medium">
                                {{ registration.user?.name }}
                            </p>
                            <p class="mt-1 text-xs text-muted-foreground">
                                {{ registration.user?.email }}
                            </p>
                            <p class="mt-1 text-xs text-muted-foreground">
                                {{ registration.user?.phone }}
                            </p>
                        </td>
                        <td class="p-4">{{ registration.reference }}</td>
                        <td class="p-4">
                            <StatusBadge :status="registration.status" />
                        </td>
                        <td class="p-4">
                            <select
                                :value="registration.attendance"
                                :aria-label="`${copy.updateAttendance}: ${registration.user?.name}`"
                                :disabled="
                                    form.processing ||
                                    registration.status !== 'registered' ||
                                    !['published', 'completed'].includes(
                                        program.status,
                                    ) ||
                                    new Date(program.starts_at).getTime() >
                                        Date.now()
                                "
                                class="rounded-md border bg-background p-2 disabled:opacity-50"
                                @change="attendance(registration.id, $event)"
                            >
                                <option
                                    v-for="(
                                        label, value
                                    ) in copy.attendanceStates"
                                    :key="value"
                                    :value="value"
                                >
                                    {{ label }}
                                </option>
                            </select>
                        </td>
                        <td class="p-4 whitespace-nowrap">
                            {{ dateTime(registration.registered_at) }}
                        </td>
                    </tr>
                    <tr v-if="!registrations.data.length">
                        <td
                            colspan="5"
                            class="p-10 text-center text-muted-foreground"
                        >
                            {{ copy.noParticipants }}
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
        <Pagination :links="registrations.links" />
    </div>
</template>
