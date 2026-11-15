<script setup lang="ts">
import { Link, usePage } from '@inertiajs/vue3';
import { ArrowUpRight } from '@lucide/vue';
import { computed } from 'vue';
import { Toaster } from '@/components/ui/sonner';
import { contentCopy } from '@/lib/contentCopy';
import { websiteCopy } from '@/lib/websiteCopy';
import WebsiteHead from '@/components/WebsiteHead.vue';
const page = usePage();
const copy = computed(() => page.props.copy as Record<string, string>);
</script>

<template>
    <WebsiteHead />
    <div
        class="min-h-dvh bg-stone-50 text-stone-900 dark:bg-stone-950 dark:text-stone-100"
    >
        <header class="border-b border-stone-200 dark:border-stone-800">
            <nav
                class="mx-auto flex min-h-20 max-w-7xl flex-wrap items-center justify-between gap-4 px-6 py-4"
                aria-label="Main navigation"
            >
                <Link
                    href="/"
                    class="flex max-w-full min-w-0 items-center gap-3 text-xl font-semibold tracking-tight"
                    ><img
                        v-if="page.props.website.logo_url"
                        :src="page.props.website.logo_url"
                        alt=""
                        width="40"
                        height="40"
                        class="size-10 shrink-0 object-contain"
                    /><span class="[overflow-wrap:anywhere]"
                        >{{ page.props.website.website_name
                        }}<span class="text-emerald-700">.</span></span
                    ></Link
                >
                <div
                    class="flex flex-wrap items-center gap-x-4 gap-y-3 text-sm"
                >
                    <Link href="/" class="hover:underline">{{
                        copy.home
                    }}</Link>
                    <Link href="/products" class="hover:underline"
                        >Products</Link
                    >
                    <Link href="/vip-membership" class="hover:underline"
                        >VIP Membership</Link
                    >
                    <Link href="/cart" class="whitespace-nowrap hover:underline"
                        >Cart ({{ page.props.cartCount ?? 0 }})</Link
                    >
                    <template v-if="page.props.auth.user">
                        <Link
                            href="/dashboard"
                            class="font-medium hover:underline"
                            >{{ copy.account }}</Link
                        >
                    </template>
                    <template v-else>
                        <Link href="/login" class="hover:underline">{{
                            copy.login
                        }}</Link>
                        <Link
                            href="/register"
                            class="inline-flex items-center gap-2 rounded-md bg-emerald-800 px-4 py-2.5 font-medium text-white hover:bg-emerald-900"
                            >{{ copy.register }} <ArrowUpRight :size="16"
                        /></Link>
                    </template>
                </div>
            </nav>
        </header>
        <main><slot /></main>
        <footer
            class="mx-auto flex max-w-7xl flex-wrap justify-between gap-4 border-t border-stone-200 px-6 py-8 text-sm text-stone-600 dark:border-stone-800 dark:text-stone-400"
        >
            <span class="max-w-full [overflow-wrap:anywhere]"
                >{{ page.props.website.website_name }} &copy;
                {{ new Date().getFullYear() }}</span
            >
            <span class="max-w-full [overflow-wrap:anywhere]">{{
                page.props.website.footer_information
            }}</span>
            <div
                v-if="
                    page.props.website.contact_email ||
                    page.props.website.contact_phone ||
                    page.props.website.address
                "
                class="grid w-full gap-3 [overflow-wrap:anywhere]"
                :aria-label="websiteCopy.contact"
            >
                <a
                    v-if="page.props.website.contact_email"
                    :href="`mailto:${page.props.website.contact_email}`"
                    class="w-fit max-w-full underline"
                    >{{ page.props.website.contact_email }}</a
                >
                <a
                    v-if="page.props.website.contact_phone"
                    :href="`tel:${page.props.website.contact_phone.replace(/[^+0-9]/g, '')}`"
                    class="w-fit max-w-full underline"
                    >{{ page.props.website.contact_phone }}</a
                >
                <address
                    v-if="page.props.website.address"
                    class="whitespace-pre-line not-italic"
                >
                    {{ page.props.website.address }}
                </address>
            </div>
            <nav
                v-if="page.props.website.social_links.length"
                :aria-label="websiteCopy.socialNavigation"
                class="flex w-full flex-wrap gap-5"
            >
                <a
                    v-for="(social, index) in page.props.website.social_links"
                    :key="index"
                    :href="social.url"
                    rel="noopener noreferrer"
                    class="max-w-full min-w-0 [overflow-wrap:anywhere] underline"
                    >{{ social.label }}</a
                >
            </nav>
            <nav
                v-if="page.props.footerPages?.length"
                class="flex w-full flex-wrap gap-x-6 gap-y-3"
                :aria-label="contentCopy.navigation"
            >
                <Link
                    v-for="item in page.props.footerPages"
                    :key="item.slug"
                    :href="`/pages/${item.slug}`"
                    class="max-w-full min-w-0 [overflow-wrap:anywhere] hover:underline"
                    >{{ item.title }}</Link
                >
            </nav>
        </footer>
        <Toaster />
    </div>
</template>
