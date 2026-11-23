<script setup lang="ts">
import { useForm } from '@inertiajs/vue3';
import { ref } from 'vue';
import { Trash2 } from '@lucide/vue';
import {
    Tooltip,
    TooltipContent,
    TooltipProvider,
    TooltipTrigger,
} from '@/components/ui/tooltip';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { recordManagementCopy as copy } from '@/lib/recordManagementCopy';

const props = defineProps<{
    module: string;
    recordId: number;
    archive?: boolean;
    archived?: boolean;
    deleteUrl?: string;
    recordName?: string;
}>();
const emit = defineEmits<{ success: [] }>();
const open = ref(false);
const form = useForm({ reason: '', archived: false });
function submit() {
    const url = `/admin/records/${props.module}/${props.recordId}`;
    const options = {
        preserveScroll: true,
        onSuccess: () => {
            open.value = false;
            form.reset();
            emit('success');
        },
    };
    if (props.archive) {
        form.archived = !props.archived;
        form.patch(`${url}/archive`, options);
    } else {
        form.transform(() => ({})).delete(props.deleteUrl ?? url, options);
    }
}
</script>

<template>
    <div class="inline-flex text-sm">
        <TooltipProvider :delay-duration="200">
            <Tooltip>
                <TooltipTrigger as-child>
                    <button
                        type="button"
                        :aria-label="
                            archive
                                ? archived
                                    ? copy.restore
                                    : copy.archive
                                : recordName
                                  ? `${copy.delete} ${recordName}`
                                  : copy.delete
                        "
                        class="inline-flex min-h-10 min-w-10 shrink-0 items-center justify-center rounded-md border px-2 text-red-700 hover:bg-red-50 focus-visible:outline-2 focus-visible:outline-offset-2 dark:text-red-400 dark:hover:bg-red-950"
                        @click="
                            open = !open;
                            form.clearErrors();
                        "
                    >
                        <template v-if="archive">{{
                            archived ? copy.restore : copy.archive
                        }}</template>
                        <Trash2 v-else :size="18" aria-hidden="true" />
                    </button>
                </TooltipTrigger>
                <TooltipContent>{{
                    archive
                        ? archived
                            ? copy.restore
                            : copy.archive
                        : copy.delete
                }}</TooltipContent>
            </Tooltip>
        </TooltipProvider>
        <Dialog v-model:open="open">
            <DialogContent
                :show-close-button="!form.processing"
                @escape-key-down="form.processing && $event.preventDefault()"
                @interact-outside="form.processing && $event.preventDefault()"
            >
                <DialogHeader>
                    <DialogTitle
                        >{{
                            archive
                                ? archived
                                    ? copy.restore
                                    : copy.archive
                                : copy.confirmDelete
                        }}<template v-if="recordName">
                            · {{ recordName }}</template
                        ></DialogTitle
                    >
                    <DialogDescription>{{
                        archive ? copy.archiveHelp : copy.deleteHelp
                    }}</DialogDescription>
                </DialogHeader>
                <form class="space-y-4" @submit.prevent="submit">
                    <label v-if="archive" class="grid gap-2">
                        {{ copy.reason }}
                        <textarea
                            v-model="form.reason"
                            required
                            minlength="5"
                            maxlength="1000"
                            rows="2"
                            class="rounded-md border bg-background p-2"
                        />
                    </label>
                    <p
                        v-if="Object.keys(form.errors).length"
                        role="alert"
                        class="text-red-700 dark:text-red-400"
                    >
                        {{ Object.values(form.errors).join(' ') }}
                    </p>
                    <div class="flex gap-4">
                        <button
                            :disabled="form.processing"
                            class="rounded-md bg-red-700 px-4 py-2 text-white hover:bg-red-800 disabled:opacity-50"
                        >
                            {{ archive ? copy.confirm : copy.confirmDelete }}
                        </button>
                        <button
                            type="button"
                            :disabled="form.processing"
                            class="rounded-md border px-4 py-2 hover:bg-muted disabled:opacity-50"
                            @click="open = false"
                        >
                            {{ copy.cancel }}
                        </button>
                    </div>
                </form>
            </DialogContent>
        </Dialog>
    </div>
</template>
