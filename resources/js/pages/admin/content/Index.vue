<script setup lang="ts">
import { Head, Link, useForm } from '@inertiajs/vue3';
import Pagination from '@/components/Pagination.vue';
import { contentCopy as copy } from '@/lib/contentCopy';
import { dateTime } from '@/types/commerce';
import type { ContentPage } from '@/types/content';
import type { Paginated } from '@/types/catalog';
const props = defineProps<{
    pages: Paginated<ContentPage>;
    filters: { search?: string | null; status?: string | null };
}>();
const filter = useForm({
    search: props.filters.search ?? '',
    status: props.filters.status ?? '',
});
</script>
<template>
    <Head :title="copy.title" />
    <div class="mx-auto w-full max-w-6xl p-6 md:p-10">
        <div class="flex flex-wrap items-center justify-between gap-4">
            <h1 class="text-3xl font-semibold">{{ copy.title }}</h1>
            <Link
                href="/admin/content-pages/create"
                class="rounded-md bg-emerald-800 px-4 py-2.5 text-sm text-white hover:bg-emerald-900"
                >{{ copy.create }}</Link
            >
        </div>
        <p class="mt-4 max-w-3xl text-sm leading-relaxed text-muted-foreground">
            {{ copy.help }}
        </p>
        <form
            class="mt-8 flex flex-wrap items-end gap-4"
            @submit.prevent="filter.get('/admin/content-pages')"
        >
            <label class="grid gap-2 text-sm"
                >{{ copy.search
                }}<input
                    v-model="filter.search"
                    type="search"
                    maxlength="100"
                    class="rounded-md border bg-transparent p-2.5"
            /></label>
            <label class="grid gap-2 text-sm"
                >{{ copy.status
                }}<select
                    v-model="filter.status"
                    class="rounded-md border bg-background p-2.5"
                >
                    <option value="">{{ copy.all }}</option>
                    <option value="draft">{{ copy.draft }}</option>
                    <option value="published">{{ copy.published }}</option>
                </select></label
            >
            <button
                :disabled="filter.processing"
                class="rounded-md border px-4 py-2.5 text-sm disabled:opacity-50"
            >
                {{ copy.filter }}
            </button>
        </form>
        <p
            v-if="Object.keys(filter.errors).length"
            role="alert"
            class="mt-4 text-sm text-red-700 dark:text-red-400"
        >
            {{ Object.values(filter.errors).join(' ') }}
        </p>
        <p
            v-if="!pages.data.length"
            class="mt-10 text-sm text-muted-foreground"
        >
            {{ copy.empty }}
        </p>
        <div v-else class="mt-8 divide-y border-y">
            <div
                v-for="item in pages.data"
                :key="item.id"
                class="flex flex-wrap items-center justify-between gap-4 py-5"
            >
                <div class="min-w-0">
                    <Link
                        :href="`/admin/content-pages/${item.id}/edit`"
                        class="font-medium break-words underline underline-offset-4"
                        >{{ item.title }}</Link
                    >
                    <p class="mt-2 text-xs break-all text-muted-foreground">
                        /pages/{{ item.slug }}
                    </p>
                </div>
                <div class="text-sm">
                    <p>
                        {{
                            item.status === 'published'
                                ? copy.published
                                : copy.draft
                        }}
                        · {{ copy.version }} {{ item.version }}
                    </p>
                    <p class="mt-2 text-xs text-muted-foreground">
                        {{ dateTime(item.updated_at) }}
                    </p>
                </div>
            </div>
        </div>
        <Pagination :links="pages.links" />
    </div>
</template>
