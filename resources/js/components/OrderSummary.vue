<script setup lang="ts">
import { money } from '@/types/catalog';
defineProps<{ subtotal: number; shipping: number; total: number }>();
</script>
<template>
    <dl class="space-y-4 text-sm">
        <div class="flex justify-between gap-4">
            <dt class="text-muted-foreground">Subtotal</dt>
            <dd>{{ money(subtotal) }}</dd>
        </div>
        <div class="flex justify-between gap-4">
            <dt class="text-muted-foreground">Shipping</dt>
            <dd>{{ shipping ? money(shipping) : 'Free' }}</dd>
        </div>
        <div
            class="flex justify-between gap-4 border-t pt-4 text-lg font-semibold"
        >
            <dt>Total (RM)</dt>
            <dd>{{ money(total) }}</dd>
        </div>
    </dl>
</template>
