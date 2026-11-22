<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { ArrowUpRight } from '@lucide/vue';
import { computed, onMounted, onUnmounted, ref, watch } from 'vue';
import { websiteCopy as copy } from '@/lib/websiteCopy';
import type { PublicBanner } from '@/types/website';
const props = defineProps<{ banners: PublicBanner[] }>();
const index = ref(0);
const paused = ref(false);
const hovered = ref(false);
let timer: ReturnType<typeof setInterval> | undefined;
let mounted = false;
const current = computed(() => props.banners[index.value] ?? props.banners[0]);
function stopAutoplay() {
    clearInterval(timer);
    timer = undefined;
}
function startAutoplay() {
    stopAutoplay();
    if (!mounted || paused.value || hovered.value || props.banners.length < 2) {
        return;
    }
    timer = setInterval(() => {
        index.value = (index.value + 1) % props.banners.length;
    }, 5000);
}
function selectBanner(itemIndex: number) {
    index.value = itemIndex;
    startAutoplay();
}
watch([paused, hovered, () => props.banners.length], startAutoplay);
onMounted(() => {
    mounted = true;
    startAutoplay();
});
onUnmounted(stopAutoplay);
watch(
    () => props.banners,
    () => {
        index.value = 0;
        startAutoplay();
    },
);
</script>
<template>
    <section
        v-if="current"
        class="mx-auto max-w-7xl px-6 py-12 md:py-16"
        aria-roledescription="carousel"
        :aria-label="copy.banners"
        @mouseenter="hovered = true"
        @mouseleave="hovered = false"
        @focusin="paused = true"
    >
        <div class="grid items-center gap-8 md:grid-cols-2 md:gap-12">
            <div class="min-w-0">
                <h1
                    class="max-w-xl text-4xl leading-[1.1] font-semibold tracking-tight [overflow-wrap:anywhere] lg:text-5xl"
                >
                    {{ current.title }}
                </h1>
                <p
                    v-if="current.subtitle"
                    class="mt-5 max-w-lg text-lg leading-relaxed [overflow-wrap:anywhere] text-stone-600 dark:text-stone-400"
                >
                    {{ current.subtitle }}
                </p>
                <Link
                    v-if="current.link_path && current.link_label"
                    :href="current.link_path"
                    class="mt-7 inline-flex max-w-full items-center gap-4 rounded-md bg-emerald-800 px-5 py-3 text-sm font-medium text-white hover:bg-emerald-900"
                    >{{ current.link_label
                    }}<ArrowUpRight :size="18" class="shrink-0"
                /></Link>
            </div>
            <img
                :key="current.id"
                :src="current.image_url"
                :alt="current.image_alt || ''"
                width="1000"
                height="800"
                :fetchpriority="index === 0 ? 'high' : 'auto'"
                class="aspect-[5/4] w-full rounded-lg object-cover"
            />
        </div>
        <div
            v-if="banners.length > 1"
            role="group"
            :aria-label="copy.slide"
            class="mt-6 flex flex-wrap gap-3"
        >
            <button
                v-for="(banner, itemIndex) in banners"
                :key="banner.id"
                type="button"
                :aria-label="`${copy.slide} ${itemIndex + 1}: ${banner.title}`"
                :aria-pressed="index === itemIndex"
                class="min-h-11 min-w-11 rounded-md border px-4 text-sm hover:underline"
                :class="
                    index === itemIndex
                        ? 'border-emerald-800 bg-emerald-800 text-white'
                        : 'border-stone-400 dark:border-stone-600'
                "
                @click="selectBanner(itemIndex)"
            >
                {{ itemIndex + 1 }}
            </button>
            <button
                type="button"
                class="min-h-11 rounded-md border border-stone-400 px-4 text-sm hover:underline dark:border-stone-600"
                @click="paused = !paused"
            >
                {{ paused ? copy.playSlides : copy.pauseSlides }}
            </button>
        </div>
    </section>
</template>
