<script setup lang="ts">
import Breadcrumbs from '@/components/Breadcrumbs.vue';
import NotificationBell from '@/components/NotificationBell.vue';
import { SidebarTrigger } from '@/components/ui/sidebar';
import type { BreadcrumbItem } from '@/types';
import { Link, usePage } from '@inertiajs/vue3';
import { House, Moon, Sun } from '@lucide/vue';
import { useAppearance } from '@/composables/useAppearance';
import { useInitials } from '@/composables/useInitials';

const page = usePage();
const { resolvedAppearance, updateAppearance } = useAppearance();
const { getInitials } = useInitials();

withDefaults(
    defineProps<{
        breadcrumbs?: BreadcrumbItem[];
    }>(),
    {
        breadcrumbs: () => [],
    },
);
</script>

<template>
    <header
        class="dashboard-header flex h-[70px] shrink-0 items-center justify-between gap-4 px-4 md:px-6"
    >
        <div class="flex items-center gap-2">
            <SidebarTrigger class="-ml-1" />
            <template v-if="breadcrumbs && breadcrumbs.length > 0">
                <Breadcrumbs :breadcrumbs="breadcrumbs" />
            </template>
            <span v-else class="hidden text-sm text-muted-foreground sm:block">
                {{
                    page.props.auth.user.role === 'admin'
                        ? 'Administration'
                        : 'My account'
                }}
            </span>
        </div>
        <div class="flex h-full items-center gap-1 sm:gap-3">
            <Link
                href="/"
                class="dashboard-icon-button"
                aria-label="Back to website"
                ><House class="size-5"
            /></Link>
            <button
                type="button"
                class="dashboard-icon-button"
                aria-label="Toggle colour theme"
                @click="
                    updateAppearance(
                        resolvedAppearance === 'dark' ? 'light' : 'dark',
                    )
                "
            >
                <Sun v-if="resolvedAppearance === 'dark'" class="size-5" /><Moon
                    v-else
                    class="size-5"
                />
            </button>
            <NotificationBell />
            <Link href="/settings/profile" class="dashboard-header-user">
                <span class="dashboard-avatar">{{
                    getInitials(page.props.auth.user.name)
                }}</span>
                <span class="hidden sm:grid">
                    <span class="max-w-40 truncate text-sm font-medium">{{
                        page.props.auth.user.name
                    }}</span>
                    <span class="text-xs text-muted-foreground">{{
                        page.props.auth.user.role === 'admin'
                            ? 'Administrator'
                            : page.props.auth.isVip
                              ? 'VIP member'
                              : 'Customer'
                    }}</span>
                </span>
            </Link>
        </div>
    </header>
</template>
