<script setup lang="ts">
defineProps<{ status: string }>();
</script>
<template>
    <span
        class="inline-flex rounded-md border bg-muted px-2.5 py-1 text-xs font-medium capitalize"
        >{{ status.replaceAll('_', ' ') }}</span
    >
</template>
