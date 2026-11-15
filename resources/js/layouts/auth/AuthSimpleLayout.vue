<script setup lang="ts">
import { Link, usePage } from '@inertiajs/vue3';
import { home } from '@/routes';
const page = usePage();

defineProps<{
    title?: string;
    description?: string;
}>();
</script>

<template>
    <div
        class="flex min-h-svh flex-col items-center justify-center gap-6 bg-background p-6 md:p-10"
    >
        <div class="w-full max-w-sm">
            <div class="flex flex-col gap-8">
                <div class="flex flex-col items-center gap-4">
                    <Link
                        :href="home()"
                        class="flex flex-col items-center gap-2 font-medium"
                    >
                        <div
                            class="mb-1 flex h-9 w-9 items-center justify-center rounded-md"
                        >
                            <img
                                v-if="page.props.website?.logo_url"
                                :src="page.props.website.logo_url"
                                alt=""
                                class="size-9 object-contain"
                            /><span v-else class="text-2xl font-semibold">{{
                                (
                                    page.props.website?.website_name ||
                                    page.props.name
                                ).charAt(0)
                            }}</span>
                        </div>
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
