<script setup lang="ts">
import { useForm, usePage } from '@inertiajs/vue3';

const page = usePage();
const form = useForm({});
</script>

<template>
    <div
        v-if="page.props.auth.impersonating"
        role="status"
        class="sticky top-0 z-50 flex flex-wrap items-center justify-center gap-3 border-b border-amber-300 bg-amber-100 px-4 py-3 text-sm text-amber-950"
    >
        <span
            >Anda sedang login sebagai
            <strong>{{ page.props.auth.user?.name }}</strong
            >.</span
        >
        <button
            type="button"
            :disabled="form.processing"
            class="rounded-md border border-amber-700 px-3 py-2 font-semibold hover:bg-amber-200 disabled:opacity-50"
            @click="form.post('/impersonation/stop')"
        >
            Kembali ke admin
        </button>
    </div>
    <slot />
</template>
