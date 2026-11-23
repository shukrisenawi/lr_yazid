<script setup lang="ts">
import { usePage } from '@inertiajs/vue3';
import { computed } from 'vue';

const props = defineProps<{ href?: string; count?: number; label: string }>();
const page = usePage();
const count = computed(() => {
    if (props.count !== undefined) return props.count;
    if (props.href === '/notifications') return page.props.unreadNotificationCount ?? 0;
    return page.props.menuBadges?.[props.href ?? ''] ?? 0;
});
</script>

<template>
    <span
        v-if="count > 0"
        :aria-label="`${label}: ${count}`"
        :title="`${label}: ${count}`"
        class="ml-auto inline-flex h-5 min-w-5 shrink-0 items-center justify-center rounded-full bg-primary px-1.5 text-xs font-semibold text-primary-foreground group-data-[collapsible=icon]:hidden"
    >
        {{ count > 99 ? '99+' : count }}
    </span>
</template>
