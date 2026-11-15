<script setup lang="ts">
import { Head, useForm } from '@inertiajs/vue3';
import ProductCard from '@/components/ProductCard.vue';
import Pagination from '@/components/Pagination.vue';
import type { Category, ProductCardData, Paginated } from '@/types/catalog';
const props = defineProps<{
    products: Paginated<ProductCardData>;
    categories: Category[];
    filters: {
        search?: string | null;
        category?: number | string | null;
        sort?: string | null;
        min?: number | string | null;
        max?: number | string | null;
    };
}>();
const form = useForm({
    search: props.filters.search ?? '',
    category: props.filters.category ?? '',
    sort:
        typeof props.filters.sort === 'string' ? props.filters.sort : 'newest',
    min: props.filters.min ?? '',
    max: props.filters.max ?? '',
});
</script>
<template>
    <Head title="Products" />
    <div class="mx-auto max-w-7xl px-6 py-12">
        <h1 class="text-4xl font-semibold tracking-tight">The collection</h1>
        <p class="mt-3 text-stone-600 dark:text-stone-400">
            Find something for your everyday.
        </p>
        <form
            class="mt-8 grid items-end gap-4 rounded-lg border p-5 sm:grid-cols-2 lg:grid-cols-6"
            @submit.prevent="form.get('/products', { preserveState: true })"
        >
            <label class="grid gap-2 text-sm lg:col-span-2"
                >Search products<input
                    v-model="form.search"
                    class="rounded-md border bg-transparent p-2.5"
                    type="search"
            /></label>
            <label class="grid gap-2 text-sm"
                >Category<select
                    v-model="form.category"
                    class="rounded-md border bg-background p-2.5"
                >
                    <option value="">All categories</option>
                    <option
                        v-for="category in categories"
                        :key="category.id"
                        :value="category.id"
                    >
                        {{ category.name }}
                    </option>
                </select></label
            >
            <label class="grid gap-2 text-sm"
                >Sort by<select
                    v-model="form.sort"
                    class="rounded-md border bg-background p-2.5"
                >
                    <option value="newest">Newest</option>
                    <option value="price_asc">Price: Low to high</option>
                    <option value="price_desc">Price: High to low</option>
                </select></label
            >
            <div class="grid grid-cols-2 gap-2">
                <label class="grid gap-2 text-sm"
                    >Min (sen)<input
                        v-model="form.min"
                        type="number"
                        min="0"
                        class="w-full rounded-md border bg-transparent p-2.5" /></label
                ><label class="grid gap-2 text-sm"
                    >Max (sen)<input
                        v-model="form.max"
                        type="number"
                        min="0"
                        class="w-full rounded-md border bg-transparent p-2.5"
                /></label>
            </div>
            <button
                class="rounded-md bg-emerald-800 px-5 py-2.5 text-white disabled:opacity-50"
                :disabled="form.processing"
            >
                Apply filters
            </button>
            <p
                v-if="Object.keys(form.errors).length"
                role="alert"
                class="text-sm text-red-700 sm:col-span-2 lg:col-span-6"
            >
                {{ Object.values(form.errors).join(' ') }}
            </p>
        </form>
        <p class="my-6 text-sm text-muted-foreground">
            {{ products.total }}
            {{ products.total === 1 ? 'product' : 'products' }}
        </p>
        <div
            v-if="products.data.length"
            class="grid grid-cols-1 gap-x-6 gap-y-10 sm:grid-cols-2 lg:grid-cols-4"
        >
            <ProductCard
                v-for="product in products.data"
                :key="product.id"
                :product="product"
            />
        </div>
        <div
            v-else
            class="rounded-lg border border-dashed px-6 py-16 text-center"
        >
            <h2 class="text-xl font-medium">No products found</h2>
            <p class="mt-3 text-muted-foreground">
                Try a different search or check back as the collection grows.
            </p>
        </div>
        <Pagination :links="products.links" />
    </div>
</template>
