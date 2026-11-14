<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import Pagination from '@/components/Pagination.vue';
import ProgramCard from '@/components/ProgramCard.vue';
import { programCopy as copy } from '@/lib/programCopy';
import type { Paginated } from '@/types/catalog';
import type { VipProgram } from '@/types/program';
defineProps<{ programs: Paginated<VipProgram> }>();
</script>
<template>
    <Head :title="copy.title" />
    <div class="mx-auto w-full max-w-6xl p-6 md:p-10">
        <div class="flex flex-wrap items-start justify-between gap-5">
            <div>
                <h1 class="text-3xl font-semibold">{{ copy.title }}</h1>
                <p class="mt-3 text-muted-foreground">{{ copy.intro }}</p>
            </div>
            <Link href="/my-programs" class="text-sm underline">{{
                copy.myPrograms
            }}</Link>
        </div>
        <div
            v-if="programs.data.length"
            class="mt-8 grid gap-6 md:grid-cols-2 xl:grid-cols-3"
        >
            <ProgramCard
                v-for="program in programs.data"
                :key="program.id"
                :program="program"
            />
        </div>
        <p
            v-else
            class="mt-8 rounded-xl border border-dashed p-10 text-center text-muted-foreground"
        >
            {{ copy.empty }}
        </p>
        <Pagination :links="programs.links" />
    </div>
</template>
