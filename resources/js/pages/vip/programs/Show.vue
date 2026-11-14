<script setup lang="ts">
import { Head, Link, useForm } from '@inertiajs/vue3';
import { computed } from 'vue';
import StatusBadge from '@/components/StatusBadge.vue';
import { programCopy as copy } from '@/lib/programCopy';
import { dateTime } from '@/types/commerce';
import {
    registrationOpen,
    type VipProgram,
    type ProgramRegistration,
} from '@/types/program';
const props = defineProps<{
    program: VipProgram;
    registration: ProgramRegistration | null;
    eligible: boolean;
}>();
const form = useForm({});
const open = computed(() => registrationOpen(props.program));
</script>
<template>
    <Head :title="program.title" />
    <div class="mx-auto w-full max-w-5xl p-6 md:p-10">
        <Link href="/vip/programs" class="text-sm underline">{{
            copy.back
        }}</Link>
        <div class="mt-6 flex flex-wrap items-center gap-4">
            <h1 class="text-3xl font-semibold">{{ program.title }}</h1>
            <StatusBadge :status="program.status" />
        </div>
        <p class="mt-4 max-w-3xl leading-relaxed text-muted-foreground">
            {{ program.short_description }}
        </p>
        <div
            v-if="program.images.length"
            class="mt-8 grid gap-4 sm:grid-cols-2"
        >
            <img
                v-for="image in program.images"
                :key="image.id"
                :src="image.url"
                :alt="program.title"
                class="aspect-[4/3] w-full rounded-xl object-cover"
            />
        </div>
        <div class="mt-8 grid items-start gap-8 lg:grid-cols-[1.5fr_1fr]">
            <section>
                <div class="rich-content" v-html="program.description" />
            </section>
            <aside class="space-y-5 rounded-xl border p-6">
                <p class="font-medium text-emerald-800 dark:text-emerald-300">
                    {{ copy.free }}
                </p>
                <dl class="space-y-4 text-sm">
                    <div>
                        <dt class="text-muted-foreground">{{ copy.starts }}</dt>
                        <dd class="mt-1">{{ dateTime(program.starts_at) }}</dd>
                    </div>
                    <div>
                        <dt class="text-muted-foreground">{{ copy.ends }}</dt>
                        <dd class="mt-1">{{ dateTime(program.ends_at) }}</dd>
                    </div>
                    <div>
                        <dt class="text-muted-foreground">
                            {{ copy.location }}
                        </dt>
                        <dd class="mt-1">{{ program.location }}</dd>
                    </div>
                    <div>
                        <dt class="text-muted-foreground">{{ copy.closes }}</dt>
                        <dd class="mt-1">
                            {{ dateTime(program.registration_closes_at) }}
                        </dd>
                    </div>
                </dl>
                <a
                    v-if="program.maps_url"
                    :href="program.maps_url"
                    target="_blank"
                    rel="noopener noreferrer"
                    class="inline-block text-sm underline"
                    >{{ copy.mapLink }}</a
                >
                <p class="text-sm">
                    {{
                        Math.max(0, program.capacity - program.registered_count)
                    }}
                    {{ copy.seats }}
                </p>
                <p
                    v-if="registration?.status === 'registered'"
                    role="status"
                    class="rounded-md bg-muted p-4 text-sm"
                >
                    {{ copy.confirmed }}<br />{{ registration.reference }}
                </p>
                <template v-else>
                    <p v-if="!eligible" class="text-sm text-muted-foreground">
                        {{ copy.eligibility }}
                        <Link href="/membership" class="underline">{{
                            copy.renew
                        }}</Link>
                    </p>
                    <p v-else-if="!open" class="text-sm text-muted-foreground">
                        {{ copy.closed }}
                    </p>
                    <p
                        v-else-if="program.registered_count >= program.capacity"
                        class="text-sm text-muted-foreground"
                    >
                        {{ copy.full }}
                    </p>
                    <form
                        v-else
                        @submit.prevent="
                            form.post(`/vip/programs/${program.id}/register`)
                        "
                    >
                        <button
                            :disabled="form.processing"
                            class="w-full rounded-md bg-emerald-800 px-5 py-3 text-white disabled:opacity-50"
                        >
                            {{
                                form.processing
                                    ? copy.registering
                                    : copy.register
                            }}
                        </button>
                    </form>
                </template>
                <p
                    v-if="Object.keys(form.errors).length"
                    role="alert"
                    class="text-sm text-red-700 dark:text-red-400"
                >
                    {{ Object.values(form.errors).join(' ') }}
                </p>
            </aside>
        </div>
    </div>
</template>
