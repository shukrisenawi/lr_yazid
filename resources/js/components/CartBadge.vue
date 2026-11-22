<script setup lang="ts">
import { usePage } from '@inertiajs/vue3';

const page = usePage();
</script>

<template>
    <span
        v-if="page.props.cartCount > 0"
        :aria-label="`${page.props.cartCount} items in cart`"
        class="ml-auto inline-flex h-5 min-w-5 shrink-0 items-center justify-center rounded-full bg-primary px-1.5 text-xs font-semibold text-primary-foreground group-data-[collapsible=icon]:hidden"
    >
        {{ page.props.cartCount > 99 ? '99+' : page.props.cartCount }}
    </span>
</template>
