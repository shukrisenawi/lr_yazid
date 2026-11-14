<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
defineProps<{
    links: { url: string | null; label: string; active: boolean }[];
}>();
const label = (text: string) =>
    text.replace('&laquo;', '‹').replace('&raquo;', '›');
</script>
<template>
    <nav
        v-if="links.length > 3"
        aria-label="Pagination"
        class="mt-8 flex flex-wrap gap-2"
    >
        <template v-for="(link, index) in links" :key="index"
            ><Link
                v-if="link.url"
                :href="link.url"
                :aria-current="link.active ? 'page' : undefined"
                class="rounded-md border px-3 py-2 text-sm"
                :class="{ 'bg-emerald-800 text-white': link.active }"
                >{{ label(link.label) }}</Link
            ><span v-else class="px-3 py-2 text-sm text-muted-foreground">{{
                label(link.label)
            }}</span></template
        >
    </nav>
</template>
