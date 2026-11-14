<script setup lang="ts">
import { Link, useForm } from '@inertiajs/vue3';
import { watch } from 'vue';
import { money } from '@/types/catalog';
import type { CartLine } from '@/types/commerce';
const props = defineProps<{ line: CartLine }>();
const form = useForm({ quantity: props.line.quantity });
watch(
    () => props.line.quantity,
    (quantity) => {
        form.quantity = quantity;
    },
);
</script>
<template>
    <article class="grid gap-5 border-b py-6 sm:grid-cols-[96px_1fr]">
        <img
            v-if="line.image_url"
            :src="line.image_url"
            :alt="line.name"
            class="size-24 rounded-md object-cover"
            width="96"
            height="96"
        />
        <div v-else class="size-24 rounded-md bg-muted" />
        <div>
            <div class="flex flex-wrap justify-between gap-3">
                <div>
                    <Link
                        :href="`/products/${line.slug}`"
                        class="font-medium hover:underline"
                        >{{ line.name }}</Link
                    >
                    <p
                        v-if="line.variant_name"
                        class="mt-1 text-sm text-muted-foreground"
                    >
                        {{ line.variant_name }}
                    </p>
                    <p class="mt-1 text-xs text-muted-foreground">
                        {{ line.sku }}
                    </p>
                </div>
                <p class="font-medium">{{ money(line.line_total_sen) }}</p>
            </div>
            <p class="mt-3 text-sm text-muted-foreground">
                {{ money(line.unit_price_sen) }} each ·
                {{ line.available }} available
            </p>
            <form
                class="mt-4 flex flex-wrap items-end gap-3"
                @submit.prevent="
                    form.patch(`/cart/items/${line.id}`, {
                        preserveScroll: true,
                    })
                "
            >
                <label class="grid gap-1 text-xs"
                    >Quantity<input
                        v-model="form.quantity"
                        type="number"
                        min="1"
                        max="99"
                        required
                        class="w-20 rounded-md border bg-transparent p-2"
                /></label>
                <button
                    :disabled="form.processing"
                    class="rounded-md border px-3 py-2 text-sm disabled:opacity-50"
                >
                    Update
                </button>
                <button
                    type="button"
                    :disabled="form.processing"
                    class="px-2 py-2 text-sm underline disabled:opacity-50"
                    @click="
                        form.delete(`/cart/items/${line.id}`, {
                            preserveScroll: true,
                        })
                    "
                >
                    Remove
                </button>
            </form>
            <p
                v-if="line.error || Object.keys(form.errors).length"
                role="alert"
                class="mt-3 text-sm text-red-700 dark:text-red-400"
            >
                {{ Object.values(form.errors).join(' ') || line.error }}
            </p>
        </div>
    </article>
</template>
