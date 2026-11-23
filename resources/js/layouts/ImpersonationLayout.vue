<script setup lang="ts">
import { useForm, usePage } from '@inertiajs/vue3';
import { Undo2 } from '@lucide/vue';
import {
    Tooltip,
    TooltipContent,
    TooltipProvider,
    TooltipTrigger,
} from '@/components/ui/tooltip';

const page = usePage();
const form = useForm({});
</script>

<template>
    <div
        v-if="page.props.auth.impersonating"
        role="status"
        class="sticky top-0 z-50 flex flex-wrap items-center justify-center gap-2 border-b border-amber-300 bg-amber-100 px-3 py-1 text-xs text-amber-950"
    >
        <span
            >You are logged in as
            <strong>{{ page.props.auth.user?.name }}</strong
            >.</span
        >
        <TooltipProvider :delay-duration="200">
            <Tooltip>
                <TooltipTrigger as-child>
                    <button
                        type="button"
                        :disabled="form.processing"
                        aria-label="Return to admin"
                        class="inline-flex size-7 items-center justify-center rounded border border-amber-700 hover:bg-amber-200 focus-visible:outline-2 focus-visible:outline-offset-2 disabled:opacity-50"
                        @click="form.post('/impersonation/stop')"
                    >
                        <Undo2 :size="14" aria-hidden="true" />
                    </button>
                </TooltipTrigger>
                <TooltipContent>Return to admin</TooltipContent>
            </Tooltip>
        </TooltipProvider>
    </div>
    <slot />
</template>
