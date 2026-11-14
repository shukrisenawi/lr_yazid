<script setup lang="ts">
import { Head, Link, useForm } from '@inertiajs/vue3';
import Pagination from '@/components/Pagination.vue';
import { money, type Paginated, type Product } from '@/types/catalog';
const props = defineProps<{ products: Paginated<Product>; search: string }>();
const form = useForm({ search: props.search });
</script>
<template>
    <Head title="Products" />
    <div class="p-6 md:p-10">
        <div class="flex flex-wrap items-center justify-between gap-4">
            <h1 class="text-3xl font-semibold">Products</h1>
            <Link
                href="/admin/products/create"
                class="rounded-md bg-emerald-800 px-5 py-2.5 text-white"
                >Add product</Link
            >
        </div>
        <form
            class="my-6 flex max-w-md items-end gap-3"
            @submit.prevent="form.get('/admin/products')"
        >
            <label class="grid flex-1 gap-2 text-sm"
                >Search products<input
                    v-model="form.search"
                    class="rounded-md border bg-transparent p-2"
                    type="search" /></label
            ><button
                class="rounded-md border px-4 py-2"
                :disabled="form.processing"
            >
                Search
            </button>
        </form>
        <div class="overflow-x-auto rounded-lg border">
            <table class="w-full text-left text-sm">
                <thead class="bg-muted">
                    <tr>
                        <th class="p-4">Product</th>
                        <th class="p-4">SKU</th>
                        <th class="p-4">Price</th>
                        <th class="p-4">Stock</th>
                        <th class="p-4">Status</th>
                        <th class="p-4">Action</th>
                    </tr>
                </thead>
                <tbody>
                    <tr
                        v-for="product in products.data"
                        :key="product.id"
                        class="border-t"
                    >
                        <td class="p-4 font-medium">{{ product.name }}</td>
                        <td class="p-4">{{ product.sku }}</td>
                        <td class="p-4">
                            {{ money(product.current_price_sen) }}
                        </td>
                        <td class="p-4">{{ product.stock }}</td>
                        <td class="p-4 capitalize">{{ product.status }}</td>
                        <td class="p-4">
                            <Link
                                :href="`/admin/products/${product.id}/edit`"
                                class="underline"
                                >Edit</Link
                            >
                        </td>
                    </tr>
                    <tr v-if="!products.data.length">
                        <td
                            colspan="6"
                            class="p-12 text-center text-muted-foreground"
                        >
                            No products yet. Add your first product to get
                            started.
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
        <Pagination :links="products.links" />
    </div>
</template>
