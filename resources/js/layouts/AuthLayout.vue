<script setup lang="ts">
import AuthLayout from '@/layouts/auth/AuthSimpleLayout.vue';
import WebsiteHead from '@/components/WebsiteHead.vue';

const { title = '', description = '' } = defineProps<{
    title?: string;
    description?: string;
}>();
</script>

<template>
    <WebsiteHead />
    <AuthLayout :title="title" :description="description">
        <slot />
    </AuthLayout>
</template>
