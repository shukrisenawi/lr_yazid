<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import Pagination from '@/components/Pagination.vue';
import AdminRecordAction from '@/components/AdminRecordAction.vue';
import AdminEditButton from '@/components/AdminEditButton.vue';
import { websiteCopy as copy } from '@/lib/websiteCopy';
import type { Banner } from '@/types/website';
import type { Paginated } from '@/types/catalog';
import { dateTime } from '@/types/commerce';
defineProps<{ banners: Paginated<Banner> }>();
</script>
<template>
    <Head :title="copy.banners" />
    <div class="mx-auto w-full max-w-5xl p-6 md:p-10">
        <div class="flex flex-wrap items-center justify-between gap-4">
            <h1 class="text-3xl font-semibold">{{ copy.banners }}</h1>
            <Link
                href="/admin/banners/create"
                class="rounded-md bg-emerald-800 px-4 py-2.5 text-sm text-white hover:bg-emerald-900"
                >{{ copy.create }}</Link
            >
        </div>
        <p class="mt-4 text-sm leading-relaxed text-muted-foreground">
            {{ copy.bannerHelp }}
        </p>
        <Link href="/" class="mt-4 inline-block text-sm underline">{{
            copy.view
        }}</Link>
        <p
            v-if="!banners.data.length"
            class="mt-10 text-sm text-muted-foreground"
        >
            {{ copy.empty }}
        </p>
        <div v-else class="mt-8 divide-y border-y">
            <div
                v-for="banner in banners.data"
                :key="banner.id"
                class="flex flex-wrap justify-between gap-4 py-5"
            >
                <div class="min-w-0">
                    <Link
                        :href="`/admin/banners/${banner.id}/edit`"
                        class="font-medium [overflow-wrap:anywhere] underline"
                        >{{ banner.title }}</Link
                    >
                    <p class="mt-2 text-sm text-muted-foreground">
                        {{ copy.position }} {{ banner.position }} ·
                        {{ copy.version }} {{ banner.version }}
                    </p>
                </div>
                <div class="text-sm">
                    <div class="flex items-center gap-4">
                        <AdminEditButton
                            :href="`/admin/banners/${banner.id}/edit`"
                            :label="`Edit ${banner.title}`"
                        />
                        <AdminRecordAction
                            module="banners"
                            :record-id="banner.id"
                            :record-name="banner.title"
                        />
                    </div>
                    <p>
                        {{
                            banner.status === 'published'
                                ? copy.published
                                : copy.draft
                        }}
                    </p>
                    <p v-if="banner.starts_at" class="mt-2">
                        {{ copy.starts }}: {{ dateTime(banner.starts_at) }}
                    </p>
                    <p v-if="banner.ends_at" class="mt-2">
                        {{ copy.ends }}: {{ dateTime(banner.ends_at) }}
                    </p>
                </div>
            </div>
        </div>
        <Pagination :links="banners.links" />
    </div>
</template>
