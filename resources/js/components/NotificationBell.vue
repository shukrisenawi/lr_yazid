<script setup lang="ts">
import { Link, usePage, usePoll } from '@inertiajs/vue3';
import { Bell } from '@lucide/vue';

const page = usePage();

usePoll(30000, () => ({
    only:
        page.component === 'customer/Notifications'
            ? ['unreadNotificationCount', 'notifications']
            : ['unreadNotificationCount'],
}));
</script>

<template>
    <Link
        href="/notifications"
        class="dashboard-icon-button relative"
        :aria-label="
            page.props.unreadNotificationCount
                ? `Notifications (${page.props.unreadNotificationCount} unread)`
                : 'Notifications'
        "
    >
        <Bell class="size-5" />
        <span
            v-if="page.props.unreadNotificationCount > 0"
            class="absolute -top-1 -right-1 flex min-w-5 items-center justify-center rounded-full bg-destructive px-1 text-[10px] leading-5 font-semibold text-white"
            aria-hidden="true"
        >
            {{
                page.props.unreadNotificationCount > 99
                    ? '99+'
                    : page.props.unreadNotificationCount
            }}
        </span>
    </Link>
</template>
