<script setup lang="ts">
import { EditorContent, useEditor } from '@tiptap/vue-3';
import StarterKit from '@tiptap/starter-kit';
import Image from '@tiptap/extension-image';
import TextAlign from '@tiptap/extension-text-align';
import { TableKit } from '@tiptap/extension-table';
import { onBeforeUnmount, ref, watch } from 'vue';
const props = withDefaults(
    defineProps<{ modelValue: string; label?: string }>(),
    { label: 'Product description' },
);
const emit = defineEmits<{ 'update:modelValue': [value: string] }>();
const preview = ref(false);
const uploading = ref(false);
const error = ref('');
const link = ref('');
const AlignedImage = Image.extend({
    addAttributes() {
        return {
            ...this.parent?.(),
            alignment: {
                default: 'left',
                parseHTML: (element: HTMLElement) =>
                    element.getAttribute('data-align') || 'left',
                renderHTML: (attributes: Record<string, string>) => ({
                    'data-align': attributes.alignment,
                }),
            },
            width: {
                default: '100%',
                parseHTML: (element: HTMLElement) =>
                    element.getAttribute('width') || '100%',
                renderHTML: (attributes: Record<string, string>) => ({
                    width: attributes.width,
                }),
            },
        };
    },
});
const editor = useEditor({
    extensions: [
        StarterKit,
        AlignedImage,
        TextAlign.configure({ types: ['heading', 'paragraph'] }),
        TableKit,
    ],
    content: props.modelValue,
    editorProps: {
        attributes: {
            class: 'rich-content min-h-64 p-4 outline-none',
            'aria-label': props.label,
            role: 'textbox',
        },
    },
    onUpdate: ({ editor }) => emit('update:modelValue', editor.getHTML()),
});
watch(
    () => props.modelValue,
    (value) => {
        if (editor.value && editor.value.getHTML() !== value)
            editor.value.commands.setContent(value, { emitUpdate: false });
    },
);
onBeforeUnmount(() => editor.value?.destroy());
async function upload(event: Event) {
    const file = (event.target as HTMLInputElement).files?.[0];
    if (!file) return;
    uploading.value = true;
    error.value = '';
    try {
        const data = new FormData();
        data.append('image', file);
        const token =
            document.cookie
                .split('; ')
                .find((row) => row.startsWith('XSRF-TOKEN='))
                ?.split('=')
                .slice(1)
                .join('=') ?? '';
        const response = await fetch('/admin/editor-images', {
            method: 'POST',
            body: data,
            headers: {
                Accept: 'application/json',
                'X-XSRF-TOKEN': decodeURIComponent(token),
            },
            credentials: 'same-origin',
        });
        const result = await response.json();
        if (!response.ok)
            throw new Error(result.message || 'Image upload failed.');
        editor.value
            ?.chain()
            .focus()
            .setImage({ src: result.url, alt: file.name })
            .run();
    } catch (reason) {
        error.value =
            reason instanceof Error ? reason.message : 'Image upload failed.';
    } finally {
        uploading.value = false;
    }
}
</script>
<template>
    <div class="overflow-hidden rounded-md border">
        <div
            v-if="editor"
            class="flex flex-wrap items-center gap-1 border-b bg-muted/50 p-2 [&_button]:rounded [&_button]:border [&_button]:px-2 [&_button]:py-1 [&_button]:text-xs"
        >
            <button
                type="button"
                :aria-pressed="editor.isActive('bold')"
                @click="editor.chain().focus().toggleBold().run()"
            >
                Bold
            </button>
            <button
                type="button"
                :aria-pressed="editor.isActive('italic')"
                @click="editor.chain().focus().toggleItalic().run()"
            >
                Italic
            </button>
            <button
                type="button"
                :aria-pressed="editor.isActive('underline')"
                @click="editor.chain().focus().toggleUnderline().run()"
            >
                Underline
            </button>
            <button
                type="button"
                @click="
                    editor.chain().focus().toggleHeading({ level: 2 }).run()
                "
            >
                Heading
            </button>
            <button
                type="button"
                @click="editor.chain().focus().setParagraph().run()"
            >
                Paragraph
            </button>
            <button
                type="button"
                @click="editor.chain().focus().toggleBulletList().run()"
            >
                Bullet list
            </button>
            <button
                type="button"
                @click="editor.chain().focus().toggleOrderedList().run()"
            >
                Numbered list
            </button>
            <button
                v-for="align in ['left', 'center', 'right']"
                :key="align"
                type="button"
                @click="editor.chain().focus().setTextAlign(align).run()"
            >
                {{ align }}
            </button>
            <button
                type="button"
                @click="
                    editor
                        .chain()
                        .focus()
                        .insertTable({ rows: 3, cols: 3, withHeaderRow: true })
                        .run()
                "
            >
                Insert table
            </button>
            <button type="button" @click="preview = !preview">
                {{ preview ? 'Edit content' : 'Preview' }}
            </button>
            <template v-if="editor.isActive('image')">
                <button
                    v-for="alignment in ['left', 'center', 'right']"
                    :key="`image-${alignment}`"
                    type="button"
                    @click="
                        editor
                            .chain()
                            .focus()
                            .updateAttributes('image', { alignment })
                            .run()
                    "
                >
                    Image {{ alignment }}
                </button>
                <button
                    v-for="width in ['50%', '75%', '100%']"
                    :key="width"
                    type="button"
                    @click="
                        editor
                            .chain()
                            .focus()
                            .updateAttributes('image', { width })
                            .run()
                    "
                >
                    Width {{ width }}
                </button>
            </template>
        </div>
        <div class="flex flex-wrap items-end gap-2 border-b p-3">
            <label class="grid gap-1 text-xs"
                >Link URL<input
                    v-model="link"
                    type="url"
                    class="rounded border bg-transparent px-2 py-1"
                    placeholder="https://"
            /></label>
            <button
                type="button"
                class="rounded border px-2 py-1 text-xs"
                @click="editor?.chain().focus().setLink({ href: link }).run()"
            >
                Apply link
            </button>
            <label class="grid gap-1 text-xs"
                >Insert image<input
                    type="file"
                    accept="image/jpeg,image/png,image/webp"
                    :disabled="uploading"
                    class="max-w-52 text-xs"
                    @change="upload"
            /></label>
            <span v-if="uploading" role="status" class="text-xs"
                >Uploading image…</span
            >
        </div>
        <p v-if="error" role="alert" class="p-3 text-sm text-red-700">
            {{ error }}
        </p>
        <div
            v-if="preview"
            class="rich-content min-h-64 p-4"
            v-html="editor?.getHTML()"
        />
        <EditorContent v-else :editor="editor" />
    </div>
</template>
