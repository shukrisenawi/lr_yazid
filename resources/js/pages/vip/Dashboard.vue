<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { dateTime } from '@/types/commerce';
import type { Membership } from '@/types/membership';
defineProps<{ membership: Membership; daysRemaining: number }>();
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
        <p class="mt-6 text-sm text-muted-foreground">
            Programs and appointments will appear as their modules are released.
        </p>
    </div>
</template>
