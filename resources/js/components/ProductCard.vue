<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { Package } from '@lucide/vue';
import AddToCartForm from '@/components/AddToCartForm.vue';
import { money, type Product } from '@/types/catalog';
defineProps<{ product: Product }>();
</script>
<template>
    <article>
        <Link :href="`/products/${product.slug}`" class="group block">
            <div
                class="flex aspect-square items-center justify-center overflow-hidden rounded-lg bg-stone-100 dark:bg-stone-900"
            >
                <img
                    v-if="product.images[0]"
                    :src="product.images[0].url"
                    :alt="product.images[0].alt || product.name"
                    width="600"
                    height="600"
                    loading="lazy"
                    class="h-full w-full object-cover transition-transform group-hover:scale-105"
                />
                <Package
                    v-else
                    :size="50"
                    class="text-stone-400"
                    aria-label="No product image"
                />
            </div>
            <p
                v-if="product.category"
                class="mt-4 text-xs text-muted-foreground"
            >
                {{ product.category.name }}
            </p>
            <h2 class="mt-1 font-medium group-hover:underline">
                {{ product.name }}
            </h2>
            <p class="mt-2 text-sm">
                <span>{{ money(product.current_price_sen) }}</span>
                <del
                    v-if="product.current_price_sen < product.price_sen"
                    class="ml-2 text-muted-foreground"
                    >{{ money(product.price_sen) }}</del
                >
            </p>
            <p class="mt-1 text-xs text-muted-foreground">
                {{
                    product.type === 'membership'
                        ? 'Membership · No shipping'
                        : product.stock > 0
                          ? 'In stock'
                          : 'Out of stock'
                }}
            </p>
        </Link>
        <Link
            v-if="(product.variants_count ?? 0) > 0"
            :href="`/products/${product.slug}`"
            class="mt-5 inline-block rounded-md border px-4 py-2 text-sm"
            >Choose options</Link
        >
        <AddToCartForm
            v-else
            compact
            :product-id="product.id"
            :available="product.type === 'membership' ? 1 : product.stock"
        />
    </article>
</template>
