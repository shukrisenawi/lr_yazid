<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import ProgramCard from '@/components/ProgramCard.vue';
import { programCopy as copy } from '@/lib/programCopy';
import { dateTime } from '@/types/commerce';
import type { Membership } from '@/types/membership';
import type { VipProgram, ProgramRegistration } from '@/types/program';
defineProps<{
    membership: Membership;
    daysRemaining: number;
    upcomingPrograms: VipProgram[];
    registeredPrograms: ProgramRegistration[];
}>();
</script>
<template>
    <Head title="VIP Dashboard" />
    <div class="p-6 md:p-10">
        <p class="text-sm font-medium text-emerald-700 dark:text-emerald-400">
            Active VIP Member
        </p>
        <h1 class="mt-3 text-3xl font-semibold">Your VIP space</h1>
        <h2 class="mt-6 text-xl font-medium">{{ membership.plan.name }}</h2>
        <dl class="mt-5 grid gap-5 sm:grid-cols-3">
            <div>
                <dt class="text-sm text-muted-foreground">
                    Membership started
                </dt>
                <dd class="mt-2">{{ dateTime(membership.starts_at) }}</dd>
            </div>
            <div>
                <dt class="text-sm text-muted-foreground">Expires</dt>
                <dd class="mt-2">{{ dateTime(membership.expires_at) }}</dd>
            </div>
            <div>
                <dt class="text-sm text-muted-foreground">Days remaining</dt>
                <dd class="mt-2 text-3xl font-semibold">{{ daysRemaining }}</dd>
            </div>
        </dl>
        <p
            v-if="daysRemaining <= 30"
            class="mt-6 rounded-md border p-4 text-sm"
        >
            Your membership expires soon. Renew before expiry to keep your VIP
            access.
        </p>
        <Link
            href="/membership"
            class="mt-8 inline-block rounded-md bg-emerald-800 px-5 py-3 text-white"
            >Renew membership</Link
        >
        <section class="mt-10">
            <div class="flex flex-wrap items-center justify-between gap-4">
                <h2 class="text-2xl font-semibold">{{ copy.title }}</h2>
                <Link href="/vip/programs" class="text-sm underline">{{
                    copy.back
                }}</Link>
            </div>
            <div class="mt-5 grid gap-5 md:grid-cols-2 xl:grid-cols-3">
                <ProgramCard
                    v-for="program in upcomingPrograms"
                    :key="program.id"
                    :program="program"
                />
            </div>
            <p
                v-if="!upcomingPrograms.length"
                class="mt-4 text-sm text-muted-foreground"
            >
                {{ copy.empty }}
            </p>
        </section>
        <section class="mt-10">
            <h2 class="text-2xl font-semibold">{{ copy.myPrograms }}</h2>
            <ul class="mt-5 space-y-3">
                <li
                    v-for="registration in registeredPrograms"
                    :key="registration.id"
                    class="rounded-md border p-4 text-sm"
                >
                    {{ registration.program.title }} ·
                    {{ dateTime(registration.program.starts_at) }}
                </li>
            </ul>
            <Link
                href="/my-programs"
                class="mt-4 inline-block text-sm underline"
                >{{ copy.myPrograms }}</Link
            >
        </section>
    </div>
</template>
