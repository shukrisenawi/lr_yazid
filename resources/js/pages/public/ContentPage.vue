<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { contentCopy as copy } from '@/lib/contentCopy';
import { dateTime } from '@/types/commerce';
import type { ContentPage } from '@/types/content';
defineProps<{
    contentPage: Pick<
        ContentPage,
        | 'title'
        | 'slug'
        | 'content'
        | 'seo_title'
        | 'meta_description'
        | 'updated_at'
    >;
}>();
</script>
<template>
    <Head :title="contentPage.seo_title || contentPage.title"
        ><meta
            v-if="contentPage.meta_description"
            name="description"
            :content="contentPage.meta_description"
    /></Head>
    <article class="mx-auto max-w-3xl px-6 py-12 md:py-16">
        <Link href="/" class="text-sm underline underline-offset-4">{{
            copy.home
        }}</Link>
        <header
            class="mt-8 border-b border-stone-200 pb-8 dark:border-stone-800"
        >
            <h1
                class="text-4xl leading-tight font-semibold tracking-tight break-words md:text-5xl"
            >
                {{ contentPage.title }}
            </h1>
            <p class="mt-5 text-sm text-stone-600 dark:text-stone-400">
                {{ copy.updated }} {{ dateTime(contentPage.updated_at) }}
            </p>
        </header>
        <div
            class="rich-content mt-8 min-w-0 [overflow-wrap:anywhere] [&_img]:h-auto [&_img]:max-w-full [&_table]:block [&_table]:max-w-full [&_table]:overflow-x-auto"
            v-html="contentPage.content"
        />
    </article>
</template>
