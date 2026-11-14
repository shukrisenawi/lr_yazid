<script setup lang="ts">
import MembershipOverview from '@/components/MembershipOverview.vue';
import type { MembershipPageProps } from '@/types/membership';
const props = defineProps<MembershipPageProps>();
</script>
<template><MembershipOverview v-bind="props" /></template>
