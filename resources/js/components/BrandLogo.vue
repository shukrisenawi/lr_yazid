<script setup lang="ts">
import { usePage } from '@inertiajs/vue3';

defineProps<{ compact?: boolean }>();
const page = usePage();
</script>

<template>
    <img
        :src="
            page.props.website?.logo_url ||
            (compact
                ? '/images/brand/logo-mark.png'
                : '/images/brand/logo-full.png')
        "
        :alt="page.props.website?.website_name || page.props.name"
        :width="compact ? 1027 : 1626"
        :height="compact ? 965 : 594"
        class="object-contain"
    />
</template>
