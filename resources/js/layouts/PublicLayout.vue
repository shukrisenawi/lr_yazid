<script setup lang="ts">
import { Link, usePage } from '@inertiajs/vue3';
import { ArrowUpRight, Menu, X } from '@lucide/vue';
import { computed, ref } from 'vue';
import { Toaster } from '@/components/ui/sonner';
import { contentCopy } from '@/lib/contentCopy';
import { websiteCopy } from '@/lib/websiteCopy';
import WebsiteHead from '@/components/WebsiteHead.vue';
import BrandLogo from '@/components/BrandLogo.vue';
const page = usePage();
const menuOpen = ref(false);
const copy = computed(() => page.props.copy as Record<string, string>);
</script>

<template>
    <WebsiteHead />
    <div class="storefront min-h-dvh bg-background text-foreground">
        <header class="storefront-header border-b border-border bg-card">
            <nav
                class="mx-auto flex max-w-7xl flex-wrap items-center justify-between gap-3 px-6 py-3"
                aria-label="Main navigation"
            >
                <Link href="/" class="shrink-0 rounded-lg bg-white px-2 py-1"
                    ><BrandLogo class="h-11 w-32 sm:w-36"
                /></Link>
                <button
                    type="button"
                    class="flex min-h-11 items-center gap-2 rounded-md border px-3 text-sm font-medium lg:hidden"
                    aria-controls="storefront-navigation"
                    :aria-expanded="menuOpen"
                    @click="menuOpen = !menuOpen"
                >
                    <component
                        :is="menuOpen ? X : Menu"
                        :size="18"
                        aria-hidden="true"
                    />
                    Menu
                </button>
                <div
                    id="storefront-navigation"
                    class="storefront-links w-full flex-wrap items-center gap-x-5 gap-y-3 pb-2 text-sm font-medium lg:flex lg:w-auto lg:pb-0"
                    :class="menuOpen ? 'flex' : 'hidden'"
                    @click="menuOpen = false"
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
                            class="brand-button inline-flex items-center gap-2 rounded-md bg-primary px-4 py-2.5 font-medium text-primary-foreground"
                            >{{ copy.register }} <ArrowUpRight :size="16"
                        /></Link>
                    </template>
                </div>
            </nav>
        </header>
        <main><slot /></main>
        <footer
            class="mx-auto flex max-w-7xl flex-wrap items-center justify-between gap-4 border-t px-6 py-8 text-sm text-muted-foreground"
        >
            <Link href="/" class="rounded-lg bg-white p-2"
                ><BrandLogo class="h-12 w-36"
            /></Link>
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
