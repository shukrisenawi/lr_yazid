<script setup lang="ts">
import { router } from '@inertiajs/vue3';
import { Pencil } from '@lucide/vue';
import {
    Tooltip,
    TooltipContent,
    TooltipProvider,
    TooltipTrigger,
} from '@/components/ui/tooltip';

const props = defineProps<{ href?: string; label?: string }>();
const emit = defineEmits<{ click: [] }>();
function edit() {
    if (props.href) router.visit(props.href);
    else emit('click');
}
</script>

<template>
    <TooltipProvider :delay-duration="200">
        <Tooltip>
            <TooltipTrigger as-child>
                <button
                    type="button"
                    :aria-label="label ?? 'Edit'"
                    class="inline-flex size-10 shrink-0 items-center justify-center rounded-md border text-foreground hover:bg-muted focus-visible:outline-2 focus-visible:outline-offset-2"
                    @click="edit"
                >
                    <Pencil :size="18" aria-hidden="true" />
                </button>
            </TooltipTrigger>
            <TooltipContent>{{ label ?? 'Edit' }}</TooltipContent>
        </Tooltip>
    </TooltipProvider>
</template>
