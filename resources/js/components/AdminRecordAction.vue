<script setup lang="ts">
import { useForm } from '@inertiajs/vue3';
import { ref } from 'vue';
import { recordManagementCopy as copy } from '@/lib/recordManagementCopy';

const props = defineProps<{
    module: string;
    recordId: number;
    archive?: boolean;
    archived?: boolean;
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
        form.delete(url, options);
    }
}
</script>

<template>
    <div class="my-3 text-sm">
        <button
            type="button"
            class="text-red-700 underline dark:text-red-400"
            @click="
                open = !open;
                form.clearErrors();
            "
        >
            {{
                archive ? (archived ? copy.restore : copy.archive) : copy.delete
            }}
        </button>
        <form
            v-if="open"
            class="mt-3 max-w-lg space-y-3 rounded-md border p-4"
            @submit.prevent="submit"
        >
            <p>{{ archive ? copy.archiveHelp : copy.deleteHelp }}</p>
            <label class="grid gap-2">
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
                    class="rounded-md border px-3 py-2 disabled:opacity-50"
                >
                    {{ copy.confirm }}
                </button>
                <button
                    type="button"
                    :disabled="form.processing"
                    class="underline"
                    @click="open = false"
                >
                    {{ copy.cancel }}
                </button>
            </div>
        </form>
    </div>
</template>
