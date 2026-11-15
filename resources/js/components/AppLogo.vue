<script setup lang="ts">
import BrandLogo from '@/components/BrandLogo.vue';
</script>

<template>
    <BrandLogo
        compact
        class="hidden size-8 shrink-0 group-data-[collapsible=icon]:block"
    />
    <BrandLogo
        class="h-11 w-36 rounded-md bg-white px-2 py-1 group-data-[collapsible=icon]:hidden"
    />
</template>
