<script setup lang="ts">
import type { HTMLAttributes } from 'vue';

defineOptions({ inheritAttrs: false });
defineProps<{ className?: HTMLAttributes['class'] }>();
</script>

<template>
    <img
        src="/images/brand/logo-mark.png"
        alt=""
        width="1027"
        height="965"
        :class="className"
        v-bind="$attrs"
    />
</template>
