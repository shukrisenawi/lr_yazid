<script setup lang="ts">
import { Head, router, useForm } from '@inertiajs/vue3';
import type { Category } from '@/types/catalog';
defineProps<{ categories: Category[] }>();
const form = useForm({ name: '', slug: '' });
const edit = useForm({ name: '', slug: '' });
function save(category: Category) {
    edit.name = category.name;
    edit.slug = category.slug;
    edit.put(`/admin/categories/${category.id}`);
}
function remove(id: number) {
    if (confirm('Delete this category? Products will become uncategorized.'))
        router.delete(`/admin/categories/${id}`);
}
</script>
<template>
    <Head title="Categories" />
    <div class="max-w-5xl p-6 md:p-10">
        <h1 class="text-3xl font-semibold">Categories</h1>
        <form
            class="mt-8 grid items-end gap-4 sm:grid-cols-3"
            @submit.prevent="
                form.post('/admin/categories', {
                    onSuccess: () => form.reset(),
                })
            "
        >
            <label class="grid gap-2 text-sm"
                ><span
                    >Category name
                    <span class="text-red-600" aria-hidden="true">*</span></span
                ><input
                    v-model="form.name"
                    required
                    class="rounded-md border bg-transparent p-2" /></label
            ><label class="grid gap-2 text-sm"
                ><span
                    >Slug
                    <span class="text-red-600" aria-hidden="true">*</span></span
                ><input
                    v-model="form.slug"
                    required
                    class="rounded-md border bg-transparent p-2" /></label
            ><button
                class="rounded-md bg-emerald-800 px-4 py-2 text-white"
                :disabled="form.processing"
            >
                Add category
            </button>
        </form>
        <p role="alert" class="mt-3 text-sm text-red-700">
            {{ Object.values(form.errors).join(' ') }}
            {{ Object.values(edit.errors).join(' ') }}
        </p>
        <div class="mt-8 space-y-4">
            <form
                v-for="category in categories"
                :key="category.id"
                class="grid items-end gap-4 rounded-lg border p-4 sm:grid-cols-4"
                @submit.prevent="save(category)"
            >
                <label class="grid gap-2 text-sm"
                    ><span
                        >Name
                        <span class="text-red-600" aria-hidden="true"
                            >*</span
                        ></span
                    ><input
                        v-model="category.name"
                        required
                        class="rounded border bg-transparent p-2" /></label
                ><label class="grid gap-2 text-sm"
                    ><span
                        >Slug
                        <span class="text-red-600" aria-hidden="true"
                            >*</span
                        ></span
                    ><input
                        v-model="category.slug"
                        required
                        class="rounded border bg-transparent p-2" /></label
                ><button
                    :disabled="edit.processing"
                    class="rounded border px-4 py-2"
                >
                    Save changes</button
                ><button
                    type="button"
                    class="px-4 py-2 text-sm text-red-700 underline"
                    @click="remove(category.id)"
                >
                    Delete ({{ category.products_count }} products)
                </button>
            </form>
            <p v-if="!categories.length" class="py-10 text-muted-foreground">
                No categories yet. Create one above.
            </p>
        </div>
    </div>
</template>
