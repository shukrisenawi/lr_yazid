<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
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
    <Head :title="contentPage.seo_title || contentPage.title">
        <meta
            v-if="contentPage.meta_description"
            name="description"
            :content="contentPage.meta_description"
        />
    </Head>
    <main class="min-h-[100dvh] bg-background text-foreground">
        <article class="mx-auto max-w-6xl px-6 py-12 md:px-10 md:py-16">
            <h1
                class="mb-8 text-4xl leading-tight font-semibold tracking-tight break-words md:text-5xl"
            >
                {{ contentPage.title }}
            </h1>
            <div
                class="rich-content min-w-0 [overflow-wrap:anywhere] [&_img]:h-auto [&_img]:max-w-full [&_table]:block [&_table]:max-w-full [&_table]:overflow-x-auto"
                v-html="contentPage.content"
            />
        </article>
    </main>
</template>
