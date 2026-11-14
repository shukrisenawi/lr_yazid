<script setup lang="ts">
import { Head, Link, useForm } from '@inertiajs/vue3';
import Pagination from '@/components/Pagination.vue';
import StatusBadge from '@/components/StatusBadge.vue';
import { programCopy as copy } from '@/lib/programCopy';
import type { Paginated } from '@/types/catalog';
import { dateTime } from '@/types/commerce';
import type { VipProgram } from '@/types/program';
const props = defineProps<{
    programs: Paginated<VipProgram>;
    filters: { search?: string; status?: string };
}>();
const form = useForm({
    search: props.filters.search ?? '',
    status: props.filters.status ?? '',
});
</script>
<template>
    <Head :title="copy.title" />
    <div class="p-6 md:p-10">
        <div class="flex flex-wrap justify-between gap-4">
            <h1 class="text-3xl font-semibold">{{ copy.title }}</h1>
            <Link
                href="/admin/programs/create"
                class="rounded-md bg-emerald-800 px-5 py-2.5 text-sm text-white"
                >{{ copy.create }}</Link
            >
        </div>
        <form
            class="mt-8 flex flex-wrap items-end gap-4"
            @submit.prevent="form.get('/admin/programs')"
        >
            <label class="grid gap-2 text-sm"
                >{{ copy.search
                }}<input
                    v-model="form.search"
                    type="search"
                    class="rounded-md border bg-transparent p-2.5" /></label
            ><label class="grid gap-2 text-sm"
                >{{ copy.status
                }}<select
                    v-model="form.status"
                    class="rounded-md border bg-background p-2.5"
                >
                    <option value="">{{ copy.all }}</option>
                    <option
                        v-for="(label, value) in copy.states"
                        :key="value"
                        :value="value"
                    >
                        {{ label }}
                    </option>
                </select></label
            ><button
                :disabled="form.processing"
                class="rounded-md border px-4 py-2.5 text-sm"
            >
                {{ copy.apply }}
            </button>
        </form>
        <div class="mt-6 overflow-x-auto rounded-xl border">
            <table class="w-full text-left text-sm">
                <thead class="bg-muted">
                    <tr>
                        <th class="p-4">{{ copy.name }}</th>
                        <th class="p-4">{{ copy.starts }}</th>
                        <th class="p-4">{{ copy.status }}</th>
                        <th class="p-4">{{ copy.participants }}</th>
                    </tr>
                </thead>
                <tbody>
                    <tr
                        v-for="program in programs.data"
                        :key="program.id"
                        class="border-t"
                    >
                        <td class="p-4">
                            <Link
                                :href="`/admin/programs/${program.id}/edit`"
                                class="font-medium underline"
                                >{{ program.title }}</Link
                            >
                            <p class="mt-2 text-xs text-muted-foreground">
                                {{ program.location }}
                            </p>
                        </td>
                        <td class="p-4 whitespace-nowrap">
                            {{ dateTime(program.starts_at) }}
                        </td>
                        <td class="p-4">
                            <StatusBadge :status="program.status" />
                        </td>
                        <td class="p-4">
                            <Link
                                :href="`/admin/programs/${program.id}/participants`"
                                class="underline"
                                >{{ program.registered_count }} /
                                {{ program.capacity }}</Link
                            >
                        </td>
                    </tr>
                    <tr v-if="!programs.data.length">
                        <td
                            colspan="4"
                            class="p-10 text-center text-muted-foreground"
                        >
                            {{ copy.empty }}
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
        <Pagination :links="programs.links" />
    </div>
</template>
