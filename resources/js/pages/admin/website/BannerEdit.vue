<script setup lang="ts">
import { Head, Link, useForm } from '@inertiajs/vue3';
import { ref } from 'vue';
import ImageUpload from '@/components/ImageUpload.vue';
import { websiteCopy as copy } from '@/lib/websiteCopy';
import { localDateTime } from '@/types/program';
import type { Banner } from '@/types/website';
const props = defineProps<{ banner: Banner | null; imageUrl: string | null }>();
const uploadKey = ref(0);
const form = useForm({
    _method: props.banner ? 'put' : 'post',
    title: props.banner?.title ?? '',
    subtitle: props.banner?.subtitle ?? '',
    image: null as File | null,
    image_alt: props.banner?.image_alt ?? '',
    link_label: props.banner?.link_label ?? '',
    link_path: props.banner?.link_path ?? '',
    status: props.banner?.status ?? 'draft',
    position: props.banner?.position ?? 0,
    version: props.banner?.version ?? null,
    starts_at: localDateTime(props.banner?.starts_at ?? undefined).slice(0, 16),
    ends_at: localDateTime(props.banner?.ends_at ?? undefined).slice(0, 16),
});
function upload(event: Event) {
    form.image = (event.target as HTMLInputElement).files?.[0] ?? null;
}
function save() {
    form._method = props.banner ? 'put' : 'post';
    form.post(
        props.banner ? `/admin/banners/${props.banner.id}` : '/admin/banners',
        {
            forceFormData: true,
            preserveScroll: true,
            onSuccess: () => {
                form.version = props.banner?.version ?? null;
                form.image = null;
                uploadKey.value++;
            },
        },
    );
}
</script>
<template>
    <Head :title="banner ? copy.edit : copy.create" />
    <div class="mx-auto w-full max-w-4xl p-6 md:p-10">
        <Link href="/admin/banners" class="text-sm underline">{{
            copy.back
        }}</Link>
        <h1 class="mt-5 text-3xl font-semibold">
            {{ banner ? copy.edit : copy.create }}
        </h1>
        <p class="mt-4 text-sm leading-relaxed text-muted-foreground">
            {{ copy.bannerHelp }}
        </p>
        <form class="mt-8 space-y-6" @submit.prevent="save">
            <label class="grid gap-2 text-sm"
                ><span
                    >{{ copy.title }}
                    <span class="text-red-600" aria-hidden="true">*</span></span
                ><input
                    v-model="form.title"
                    required
                    maxlength="100"
                    class="rounded-md border bg-transparent p-2.5"
            /></label>
            <label class="grid gap-2 text-sm"
                >{{ copy.subtitle
                }}<textarea
                    v-model="form.subtitle"
                    maxlength="200"
                    rows="2"
                    class="rounded-md border bg-transparent p-3"
                />
            </label>
            <img
                v-if="imageUrl"
                :src="imageUrl"
                :alt="form.image_alt"
                class="aspect-[5/4] max-h-64 max-w-full rounded-md object-cover"
            />
            <label class="grid gap-2 text-sm"
                ><span
                    >{{ copy.image }}
                    <span
                        v-if="form.status === 'published'"
                        class="text-red-600"
                        aria-hidden="true"
                        >*</span
                    ></span
                ><ImageUpload
                    :key="uploadKey"
                    accept="image/jpeg,image/png,image/webp"
                    @change="upload"
                /><span class="text-xs text-muted-foreground">{{
                    copy.bannerImageHelp
                }}</span></label
            >
            <label class="grid gap-2 text-sm"
                ><span
                    >{{ copy.alt }}
                    <span
                        v-if="form.status === 'published'"
                        class="text-red-600"
                        aria-hidden="true"
                        >*</span
                    ></span
                ><input
                    v-model="form.image_alt"
                    :required="form.status === 'published'"
                    maxlength="200"
                    class="rounded-md border bg-transparent p-2.5"
            /></label>
            <div class="grid gap-5 sm:grid-cols-2">
                <label class="grid gap-2 text-sm"
                    ><span
                        >{{ copy.label }}
                        <span
                            v-if="form.link_path"
                            class="text-red-600"
                            aria-hidden="true"
                            >*</span
                        ></span
                    ><input
                        v-model="form.link_label"
                        :required="!!form.link_path"
                        maxlength="40"
                        class="rounded-md border bg-transparent p-2.5"
                /></label>
                <label class="grid gap-2 text-sm"
                    ><span
                        >{{ copy.path }}
                        <span
                            v-if="form.link_label"
                            class="text-red-600"
                            aria-hidden="true"
                            >*</span
                        ></span
                    ><input
                        v-model="form.link_path"
                        :required="!!form.link_label"
                        maxlength="500"
                        class="rounded-md border bg-transparent p-2.5"
                /></label>
            </div>
            <p class="text-xs text-muted-foreground">{{ copy.pathHelp }}</p>
            <div class="grid gap-5 sm:grid-cols-2">
                <label class="grid gap-2 text-sm"
                    ><span
                        >{{ copy.status }}
                        <span class="text-red-600" aria-hidden="true"
                            >*</span
                        ></span
                    ><select
                        v-model="form.status"
                        class="rounded-md border bg-background p-2.5"
                    >
                        <option value="draft">{{ copy.draft }}</option>
                        <option value="published">{{ copy.published }}</option>
                    </select></label
                >
                <label class="grid gap-2 text-sm"
                    ><span
                        >{{ copy.position }}
                        <span class="text-red-600" aria-hidden="true"
                            >*</span
                        ></span
                    ><input
                        v-model="form.position"
                        type="number"
                        required
                        min="0"
                        max="10000"
                        class="rounded-md border bg-transparent p-2.5"
                /></label>
                <label class="grid gap-2 text-sm"
                    >{{ copy.starts
                    }}<input
                        v-model="form.starts_at"
                        type="datetime-local"
                        class="min-w-0 rounded-md border bg-transparent p-2.5"
                /></label>
                <label class="grid gap-2 text-sm"
                    >{{ copy.ends
                    }}<input
                        v-model="form.ends_at"
                        type="datetime-local"
                        class="min-w-0 rounded-md border bg-transparent p-2.5"
                /></label>
            </div>
            <p class="text-xs text-muted-foreground">{{ copy.scheduleHelp }}</p>
            <p
                v-if="Object.keys(form.errors).length"
                role="alert"
                class="text-sm text-red-700 dark:text-red-400"
            >
                {{ Object.values(form.errors).join(' ') }}
            </p>
            <button
                :disabled="form.processing || !!form.errors.version"
                class="rounded-md bg-emerald-800 px-5 py-3 text-sm text-white hover:bg-emerald-900 disabled:opacity-50"
            >
                {{ copy.save }}
            </button>
        </form>
    </div>
</template>
