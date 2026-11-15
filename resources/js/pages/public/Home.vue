<script setup lang="ts">
import { Head, Link, usePage } from '@inertiajs/vue3';
import { ArrowUpRight, ShieldCheck, Users, Sparkles } from '@lucide/vue';
import { computed } from 'vue';
import ProductCard from '@/components/ProductCard.vue';
import HomeBanner from '@/components/HomeBanner.vue';
import type { PublicBanner } from '@/types/website';
import type { ProductCardData, Category } from '@/types/catalog';
defineProps<{
    banners: PublicBanner[];
    featured: ProductCardData[];
    latest: ProductCardData[];
    categories: Category[];
}>();
const page = usePage();
const copy = computed(() => page.props.copy as Record<string, string>);
</script>

<template>
    <Head title="Home"
        ><meta head-key="description" name="description" :content="copy.intro"
    /></Head>
    <HomeBanner v-if="banners.length" :banners="banners" />
    <section
        v-else
        class="mx-auto grid max-w-7xl items-center gap-10 px-6 py-16 md:grid-cols-2 md:py-20"
    >
        <div>
            <p
                class="mb-6 text-xs font-semibold tracking-[0.2em] text-emerald-800 uppercase dark:text-emerald-400"
            >
                {{ page.props.website.website_name }}
            </p>
            <h1
                class="max-w-lg text-5xl leading-[1.06] font-semibold tracking-tight lg:text-6xl"
            >
                {{ copy.headline }}
            </h1>
            <p
                class="mt-6 max-w-md text-lg leading-relaxed text-stone-600 dark:text-stone-400"
            >
                {{ copy.intro }}
            </p>
            <Link
                href="/products"
                class="mt-8 inline-flex items-center gap-5 rounded-md bg-emerald-800 px-6 py-3.5 font-medium text-white transition-colors hover:bg-emerald-900"
            >
                Browse products
                <ArrowUpRight :size="18" />
            </Link>
        </div>
        <div class="relative">
            <img
                src="/images/collective.jpg"
                alt="A bright shared space with natural materials and greenery"
                width="1000"
                height="1100"
                fetchpriority="high"
                class="aspect-[1.05] w-full rounded-lg object-cover"
            />
        </div>
    </section>
    <section v-if="featured.length" class="mx-auto max-w-7xl px-6 pb-16">
        <div class="mb-8 flex items-center justify-between gap-4">
            <h2 class="text-3xl font-semibold tracking-tight">
                Selected for your everyday
            </h2>
            <Link href="/products" class="text-sm underline"
                >View all products</Link
            >
        </div>
        <div class="grid gap-6 sm:grid-cols-2 lg:grid-cols-4">
            <ProductCard
                v-for="product in featured"
                :key="product.id"
                :product="product"
            />
        </div>
    </section>
    <section v-if="latest.length" class="mx-auto max-w-7xl px-6 pb-16">
        <h2 class="mb-8 text-3xl font-semibold">New to the collection</h2>
        <div class="grid gap-6 sm:grid-cols-2 lg:grid-cols-4">
            <ProductCard
                v-for="product in latest"
                :key="product.id"
                :product="product"
            />
        </div>
    </section>
    <nav
        v-if="categories.length"
        aria-label="Product categories"
        class="mx-auto flex max-w-7xl flex-wrap items-center gap-4 px-6 pb-12"
    >
        <h2 class="mr-4 font-medium">Explore categories</h2>
        <Link
            v-for="category in categories"
            :key="category.id"
            :href="`/products?category=${category.id}`"
            class="rounded-md border px-4 py-2 text-sm hover:underline"
            >{{ category.name }}</Link
        >
    </nav>
    <section
        class="mx-auto grid max-w-7xl gap-8 border-t border-stone-200 px-6 py-10 md:grid-cols-3 dark:border-stone-800"
    >
        <div class="flex gap-4">
            <Sparkles class="mt-1 shrink-0 text-emerald-700" />
            <div>
                <h2 class="font-semibold">Considered essentials</h2>
                <p
                    class="mt-2 text-sm leading-relaxed text-stone-600 dark:text-stone-400"
                >
                    A thoughtful approach to everyday living.
                </p>
            </div>
        </div>
        <div class="flex gap-4">
            <Users class="mt-1 shrink-0 text-emerald-700" />
            <div>
                <h2 class="font-semibold">A personal connection</h2>
                <p
                    class="mt-2 text-sm leading-relaxed text-stone-600 dark:text-stone-400"
                >
                    One account for your collective experience.
                </p>
            </div>
        </div>
        <div class="flex gap-4">
            <ShieldCheck class="mt-1 shrink-0 text-emerald-700" />
            <div>
                <h2 class="font-semibold">Your account, protected</h2>
                <p
                    class="mt-2 text-sm leading-relaxed text-stone-600 dark:text-stone-400"
                >
                    Email verification and additional security controls.
                </p>
            </div>
        </div>
    </section>
</template>
