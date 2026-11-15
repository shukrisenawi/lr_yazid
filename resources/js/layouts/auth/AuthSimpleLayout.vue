<script setup lang="ts">
import { Link, usePage } from '@inertiajs/vue3';
import { home } from '@/routes';
import BrandLogo from '@/components/BrandLogo.vue';
const page = usePage();

defineProps<{
    title?: string;
    description?: string;
}>();
</script>

<template>
    <div
        class="brand-backdrop flex min-h-dvh flex-col items-center justify-center gap-6 p-6 md:p-10"
    >
        <div
            class="w-full max-w-md rounded-2xl border bg-card p-7 shadow-xl shadow-blue-950/5 sm:p-9"
        >
            <div class="flex flex-col gap-8">
                <div class="flex flex-col items-center gap-4">
                    <Link
                        :href="home()"
                        class="flex flex-col items-center gap-2 font-medium"
                    >
                        <BrandLogo
                            class="mb-2 h-20 w-56 rounded-lg bg-white p-2"
                        />
                        <span
                            class="text-center text-sm [overflow-wrap:anywhere]"
                            >{{
                                page.props.website?.website_name ||
                                page.props.name
                            }}</span
                        >
                    </Link>
                    <div class="space-y-2 text-center">
                        <h1 class="text-xl font-medium">{{ title }}</h1>
                        <p class="text-center text-sm text-muted-foreground">
                            {{ description }}
                        </p>
                    </div>
                </div>
                <slot />
            </div>
        </div>
    </div>
</template>
