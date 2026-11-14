<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { CalendarDays, MapPin } from '@lucide/vue';
import { programCopy as copy } from '@/lib/programCopy';
import { dateTime } from '@/types/commerce';
import type { VipProgram } from '@/types/program';
defineProps<{ program: VipProgram }>();
</script>
<template>
    <article class="overflow-hidden rounded-xl border">
        <img
            v-if="program.images[0]"
            :src="program.images[0].url"
            :alt="program.title"
            class="aspect-[16/9] w-full object-cover"
            loading="lazy"
        />
        <div class="p-6">
            <p
                class="text-xs font-medium tracking-wide text-emerald-800 uppercase dark:text-emerald-300"
            >
                {{ copy.free }}
            </p>
            <h2 class="mt-3 text-xl font-semibold">
                <Link
                    :href="`/vip/programs/${program.slug}`"
                    class="hover:underline"
                    >{{ program.title }}</Link
                >
            </h2>
            <div class="mt-5 grid gap-3 text-sm text-muted-foreground">
                <p class="flex gap-2">
                    <CalendarDays
                        class="size-4 shrink-0"
                        aria-hidden="true"
                    />{{ dateTime(program.starts_at) }}
                </p>
                <p class="flex gap-2">
                    <MapPin class="size-4 shrink-0" aria-hidden="true" />{{
                        program.location
                    }}
                </p>
            </div>
            <p
                v-if="program.short_description"
                class="mt-5 text-sm leading-relaxed"
            >
                {{ program.short_description }}
            </p>
            <p class="mt-5 text-sm text-muted-foreground">
                {{ Math.max(0, program.capacity - program.registered_count) }}
                {{ copy.seats }}
            </p>
            <Link
                :href="`/vip/programs/${program.slug}`"
                class="mt-5 inline-block text-sm font-medium underline underline-offset-4"
                >{{ copy.details }}</Link
            >
        </div>
    </article>
</template>
