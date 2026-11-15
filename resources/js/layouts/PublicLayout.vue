<script setup lang="ts">
import { Link, usePage } from '@inertiajs/vue3';
import { ArrowUpRight } from '@lucide/vue';
import { computed } from 'vue';
import { Toaster } from '@/components/ui/sonner';
import { contentCopy } from '@/lib/contentCopy';
const page = usePage();
const copy = computed(() => page.props.copy as Record<string, string>);
</script>

<template>
    <div
        class="min-h-dvh bg-stone-50 text-stone-900 dark:bg-stone-950 dark:text-stone-100"
    >
        <header class="border-b border-stone-200 dark:border-stone-800">
            <nav
                class="mx-auto flex min-h-20 max-w-7xl flex-wrap items-center justify-between gap-4 px-6 py-4"
                aria-label="Main navigation"
            >
                <Link href="/" class="text-xl font-semibold tracking-tight"
                    >{{ copy.brand
                    }}<span class="text-emerald-700">.</span></Link
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
            <span>{{ copy.brand }} &copy; {{ new Date().getFullYear() }}</span>
            <span>{{ copy.footer }}</span>
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
