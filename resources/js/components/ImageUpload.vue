<script setup lang="ts">
import { ImagePlus, Upload, Check } from '@lucide/vue';
import { onBeforeUnmount, ref } from 'vue';

const props = withDefaults(
    defineProps<{
        accept?: string;
        multiple?: boolean;
        disabled?: boolean;
        compact?: boolean;
        preview?: boolean;
    }>(),
    {
        accept: 'image/jpeg,image/png,image/webp',
        multiple: false,
        disabled: false,
        compact: false,
        preview: true,
    },
);
const emit = defineEmits<{ change: [event: Event] }>();
const input = ref<HTMLInputElement>();
const dragging = ref(false);
const selected = ref<{ name: string; size: string; url: string }[]>([]);
const error = ref('');

function release() {
    selected.value.forEach((file) => URL.revokeObjectURL(file.url));
}
onBeforeUnmount(release);

function change(event: Event) {
    const files = Array.from((event.target as HTMLInputElement).files ?? []);
    release();
    selected.value = files.map((file) => ({
        name: file.name,
        size: `${(file.size / 1024 / 1024).toFixed(2)} MB`,
        url: props.preview ? URL.createObjectURL(file) : '',
    }));
    error.value = '';
    emit('change', event);
}

function drop(event: DragEvent) {
    dragging.value = false;
    if (props.disabled || !input.value || !event.dataTransfer?.files.length)
        return;
    const files = Array.from(event.dataTransfer.files);
    if (files.some((file) => !props.accept.split(',').includes(file.type))) {
        error.value = 'Please choose a JPEG, PNG or WebP image.';
        return;
    }
    const transfer = new DataTransfer();
    (props.multiple ? files : files.slice(0, 1)).forEach((file) =>
        transfer.items.add(file),
    );
    input.value.files = transfer.files;
    input.value.dispatchEvent(new Event('change', { bubbles: true }));
}
</script>

<template>
    <div class="min-w-0">
        <div
            class="relative rounded-xl border border-dashed bg-background text-center focus-within:border-primary focus-within:ring-2 focus-within:ring-ring/30"
            :class="[
                compact ? 'p-3' : 'px-5 py-7',
                dragging && !disabled
                    ? 'border-primary bg-accent'
                    : 'border-input hover:border-primary hover:bg-accent/50',
                disabled ? 'pointer-events-none opacity-50' : '',
            ]"
            @dragenter.prevent="dragging = true"
            @dragover.prevent
            @dragleave.prevent="dragging = false"
            @drop.prevent="drop"
        >
            <input
                ref="input"
                type="file"
                :accept="accept"
                :multiple="multiple"
                :disabled="disabled"
                class="absolute inset-0 z-10 h-full w-full cursor-pointer opacity-0"
                @change="change"
            />
            <div class="pointer-events-none">
                <span
                    v-if="!compact"
                    class="mx-auto mb-3 flex size-12 items-center justify-center rounded-xl border border-primary/15 bg-accent text-primary"
                >
                    <ImagePlus class="size-6" aria-hidden="true" />
                </span>
                <span
                    class="flex items-center justify-center gap-2 text-sm font-semibold text-primary"
                >
                    <Upload class="size-4 shrink-0" aria-hidden="true" />
                    {{
                        selected.length
                            ? 'Choose another image'
                            : 'Choose image'
                    }}{{ multiple ? 's' : '' }}
                </span>
                <span
                    v-if="!compact"
                    class="mt-1 block text-sm text-muted-foreground"
                >
                    or drag and drop
                    {{ multiple ? 'your images' : 'an image' }} here
                </span>
                <span
                    v-if="!compact"
                    class="mt-3 block text-xs text-muted-foreground"
                >
                    JPEG, PNG or WebP
                </span>
            </div>
        </div>
        <p
            v-if="error"
            role="alert"
            class="mt-2 text-xs text-red-700 dark:text-red-400"
        >
            {{ error }}
        </p>
        <div v-if="selected.length" class="mt-3 grid gap-2" aria-live="polite">
            <div
                v-for="(file, index) in selected"
                :key="index"
                class="flex min-w-0 items-center gap-3 rounded-lg border bg-accent/40 p-2"
            >
                <img
                    v-if="preview"
                    :src="file.url"
                    alt="Selected image preview"
                    class="size-12 shrink-0 rounded-md bg-background object-contain"
                />
                <div class="min-w-0 flex-1 text-left">
                    <p class="truncate text-xs font-medium">{{ file.name }}</p>
                    <p class="mt-1 text-xs text-muted-foreground">
                        {{ file.size }}
                    </p>
                </div>
                <Check
                    class="size-4 shrink-0 text-primary"
                    aria-hidden="true"
                />
            </div>
        </div>
    </div>
</template>
