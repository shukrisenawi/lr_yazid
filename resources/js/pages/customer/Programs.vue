<script setup lang="ts">
import { Head, useForm } from '@inertiajs/vue3';
import Pagination from '@/components/Pagination.vue';
import StatusBadge from '@/components/StatusBadge.vue';
import { programCopy as copy } from '@/lib/programCopy';
import type { Paginated } from '@/types/catalog';
import { dateTime } from '@/types/commerce';
import type { ProgramRegistration } from '@/types/program';
defineProps<{ registrations: Paginated<ProgramRegistration> }>();
const form = useForm({});
function cancel(id: number) {
    if (confirm(copy.cancelConfirm))
        form.post(`/my-programs/${id}/cancel`, { preserveScroll: true });
}
</script>
<template>
    <Head :title="copy.myPrograms" />
    <div class="mx-auto w-full max-w-4xl p-6 md:p-10">
        <h1 class="text-3xl font-semibold">{{ copy.myPrograms }}</h1>
        <p class="mt-3 text-muted-foreground">{{ copy.historyIntro }}</p>
        <p
            v-if="Object.keys(form.errors).length"
            role="alert"
            class="mt-5 text-sm text-red-700 dark:text-red-400"
        >
            {{ Object.values(form.errors).join(' ') }}
        </p>
        <div class="mt-8 space-y-5">
            <article
                v-for="registration in registrations.data"
                :key="registration.id"
                class="rounded-xl border p-6"
            >
                <div class="flex flex-wrap items-center justify-between gap-3">
                    <h2 class="text-xl font-semibold">
                        {{ registration.program.title }}
                    </h2>
                    <StatusBadge :status="registration.status" />
                </div>
                <p class="mt-3 text-sm text-muted-foreground">
                    {{ registration.reference }}
                </p>
                <p class="mt-4 text-sm">
                    {{ dateTime(registration.program.starts_at) }} —
                    {{ dateTime(registration.program.ends_at) }}
                </p>
                <p class="mt-2 text-sm">{{ registration.program.location }}</p>
                <p class="mt-4 text-sm">
                    {{ copy.attendance }}:
                    {{
                        copy.attendanceStates[
                            registration.attendance as keyof typeof copy.attendanceStates
                        ]
                    }}
                </p>
                <button
                    v-if="
                        registration.status === 'registered' &&
                        registration.program.status === 'published' &&
                        new Date(registration.program.starts_at).getTime() >
                            Date.now()
                    "
                    :disabled="form.processing"
                    class="mt-5 text-sm underline disabled:opacity-50"
                    @click="cancel(registration.id)"
                >
                    {{ copy.cancel }}
                </button>
            </article>
            <p
                v-if="!registrations.data.length"
                class="rounded-xl border border-dashed p-10 text-center text-muted-foreground"
            >
                {{ copy.historyEmpty }}
            </p>
        </div>
        <Pagination :links="registrations.links" />
    </div>
</template>
