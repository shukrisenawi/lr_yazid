<script setup lang="ts">
import { usePage } from '@inertiajs/vue3';

const page = usePage();
</script>

<template>
    <div
        class="flex aspect-square size-8 items-center justify-center rounded-md bg-sidebar-primary text-sidebar-primary-foreground"
    >
        <img
            v-if="page.props.website?.logo_url"
            :src="page.props.website.logo_url"
            alt=""
            class="size-8 object-contain"
        /><span v-else class="text-lg font-semibold">{{
            (page.props.website?.website_name || page.props.name).charAt(0)
        }}</span>
    </div>
    <div class="ml-1 grid flex-1 text-left text-sm">
        <span class="mb-0.5 truncate leading-tight font-semibold">{{
            page.props.website?.website_name || page.props.name
        }}</span>
    </div>
</template>
