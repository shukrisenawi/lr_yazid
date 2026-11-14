<script setup lang="ts">
import { Head, Link, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';
const page = usePage();
const copy = computed(() => page.props.copy as Record<string, string>);
</script>
<template>
    <Head title="My Account" />
    <div class="mx-auto w-full max-w-5xl p-6 md:p-10">
        <p class="text-sm text-muted-foreground">
            {{ page.props.auth.user.name }}
        </p>
        <h1 class="mt-2 text-3xl font-semibold tracking-tight">
            {{ copy.welcome }}
        </h1>
        <p class="mt-3 text-muted-foreground">{{ copy.account_description }}</p>
        <div class="mt-10 grid gap-6 md:grid-cols-2">
            <section class="rounded-xl border p-6">
                <h2 class="font-semibold">{{ copy.profile }}</h2>
                <p class="mt-2 text-sm text-muted-foreground">
                    Update your name, email address and personal information.
                </p>
                <Link
                    href="/settings/profile"
                    class="mt-6 inline-block font-medium text-emerald-700 underline dark:text-emerald-400"
                    >Manage profile</Link
                >
            </section>
            <section class="rounded-xl border p-6">
                <h2 class="font-semibold">{{ copy.security }}</h2>
                <p class="mt-2 text-sm text-muted-foreground">
                    Manage your password and two-factor authentication.
                </p>
                <Link
                    href="/settings/security"
                    class="mt-6 inline-block font-medium text-emerald-700 underline dark:text-emerald-400"
                    >Manage security</Link
                >
            </section>
        </div>
        <p
            class="mt-8 rounded-lg bg-muted p-5 text-sm leading-relaxed text-muted-foreground"
        >
            {{ copy.foundation }}
        </p>
    </div>
</template>
