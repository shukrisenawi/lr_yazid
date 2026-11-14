<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
defineProps<{ customers: number; activeVipMembers: number }>();
</script>
<template>
    <Head title="Administration" />
    <div class="p-6 md:p-10">
        <h1 class="text-3xl font-semibold tracking-tight">Administration</h1>
        <p class="mt-3 text-muted-foreground">
            An overview of your customer community.
        </p>
        <div class="mt-8 grid max-w-3xl gap-5 sm:grid-cols-2">
            <section class="rounded-xl border p-6">
                <h2 class="text-sm text-muted-foreground">Total customers</h2>
                <p class="mt-4 text-4xl font-semibold">{{ customers }}</p>
            </section>
            <section class="rounded-xl border p-6">
                <h2 class="text-sm text-muted-foreground">
                    Active VIP members
                </h2>
                <p class="mt-4 text-4xl font-semibold">
                    {{ activeVipMembers }}
                </p>
            </section>
        </div>
        <p class="mt-8 text-sm text-muted-foreground">
            Product, order and appointment management will appear here as their
            modules are released.
        </p>
    </div>
</template>
