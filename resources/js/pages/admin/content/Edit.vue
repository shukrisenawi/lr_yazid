<script setup lang="ts">
import { Head, Link, useForm } from '@inertiajs/vue3';
import RichTextEditor from '@/components/RichTextEditor.vue';
import { contentCopy as copy } from '@/lib/contentCopy';
import type { ContentPage } from '@/types/content';
const props = defineProps<{
    contentPage: ContentPage | null;
    publicBaseUrl: string;
}>();
const form = useForm({
    title: props.contentPage?.title ?? '',
    slug: props.contentPage?.slug ?? '',
    content: props.contentPage?.content ?? '',
    seo_title: props.contentPage?.seo_title ?? '',
    meta_description: props.contentPage?.meta_description ?? '',
    status: props.contentPage?.status ?? 'draft',
    show_in_footer: props.contentPage?.show_in_footer ?? false,
    is_landing: props.contentPage?.is_landing ?? false,
    custom_domain: props.contentPage?.custom_domain ?? '',
    version: props.contentPage?.version ?? null,
});
function save() {
    const options = {
        preserveScroll: true,
        onSuccess: () => {
            form.version = props.contentPage?.version ?? null;
        },
    };
    if (props.contentPage)
        form.put(`/admin/content-pages/${props.contentPage.id}`, options);
    else form.post('/admin/content-pages', options);
}
</script>
<template>
    <Head :title="contentPage ? copy.edit : copy.create" />
    <div class="mx-auto w-full max-w-4xl p-6 md:p-10">
        <Link href="/admin/content-pages" class="text-sm underline">{{
            copy.back
        }}</Link>
        <h1 class="mt-5 text-3xl font-semibold">
            {{ contentPage ? copy.edit : copy.create }}
        </h1>
        <p class="mt-4 text-sm leading-relaxed text-muted-foreground">
            {{ copy.help }}
        </p>
        <a
            v-if="contentPage?.status === 'published'"
            :href="`${publicBaseUrl}/${contentPage.is_landing ? 'lp' : 'pages'}/${contentPage.slug}`"
            target="_blank"
            rel="noopener"
            class="mt-4 inline-block text-sm underline"
            >{{ copy.view }}: {{ publicBaseUrl }}/{{
                contentPage.is_landing ? 'lp' : 'pages'
            }}/{{ contentPage.slug }}</a
        >
        <form class="mt-8 space-y-6" @submit.prevent="save">
            <section class="space-y-4 rounded-md border p-5">
                <label class="flex items-center gap-2 text-sm">
                    <input v-model="form.is_landing" type="checkbox" />
                    Landing page (tanpa menu dan footer kedai)
                </label>
                <p class="text-sm text-muted-foreground">
                    Gunakan editor di bawah untuk teks, gambar, pautan dan
                    jadual. Terbitkan untuk mengaktifkan pautan awam.
                </p>
                <label v-if="form.is_landing" class="grid gap-2 text-sm">
                    Subdomain / domain (pilihan)
                    <input
                        v-model="form.custom_domain"
                        maxlength="253"
                        placeholder="promosi.example.com"
                        class="rounded-md border bg-transparent p-2.5"
                    />
                    <span class="text-muted-foreground"
                        >Masukkan nama sahaja tanpa https:// atau laluan.
                        Tetapkan Document root subdomain ke folder
                        landing-public aplikasi serta aktifkan SSL.</span
                    >
                    <span
                        v-if="form.errors.custom_domain"
                        class="text-red-700 dark:text-red-400"
                        >{{ form.errors.custom_domain }}</span
                    >
                </label>
                <a
                    v-if="
                        contentPage?.is_landing &&
                        contentPage?.custom_domain &&
                        contentPage?.status === 'published'
                    "
                    :href="`https://${contentPage.custom_domain}`"
                    target="_blank"
                    rel="noopener"
                    class="block text-sm break-all underline"
                    >https://{{ contentPage.custom_domain }} (selepas DNS / SSL
                    siap)</a
                >
            </section>
            <div class="grid gap-5 sm:grid-cols-2">
                <label class="grid gap-2 text-sm"
                    ><span
                        >{{ copy.name }}
                        <span class="text-red-600" aria-hidden="true"
                            >*</span
                        ></span
                    ><input
                        v-model="form.title"
                        required
                        maxlength="200"
                        class="rounded-md border bg-transparent p-2.5"
                    /><span
                        v-if="form.errors.title"
                        class="text-red-700 dark:text-red-400"
                        >{{ form.errors.title }}</span
                    ></label
                >
                <label class="grid gap-2 text-sm"
                    ><span
                        >{{ copy.slug }}
                        <span class="text-red-600" aria-hidden="true"
                            >*</span
                        ></span
                    ><input
                        v-model="form.slug"
                        required
                        maxlength="200"
                        pattern="[a-z0-9]+(-[a-z0-9]+)*"
                        class="rounded-md border bg-transparent p-2.5"
                    /><span
                        v-if="form.errors.slug"
                        class="text-red-700 dark:text-red-400"
                        >{{ form.errors.slug }}</span
                    ></label
                >
            </div>
            <div>
                <p class="mb-2 text-sm">{{ copy.content }}</p>
                <RichTextEditor v-model="form.content" :label="copy.content" />
                <p
                    v-if="form.errors.content"
                    class="mt-2 text-sm text-red-700 dark:text-red-400"
                >
                    {{ form.errors.content }}
                </p>
            </div>
            <label class="grid gap-2 text-sm"
                >{{ copy.seo
                }}<input
                    v-model="form.seo_title"
                    maxlength="200"
                    class="rounded-md border bg-transparent p-2.5"
                /><span
                    v-if="form.errors.seo_title"
                    class="text-red-700 dark:text-red-400"
                    >{{ form.errors.seo_title }}</span
                ></label
            >
            <label class="grid gap-2 text-sm"
                >{{ copy.description
                }}<textarea
                    v-model="form.meta_description"
                    maxlength="300"
                    rows="3"
                    class="rounded-md border bg-transparent p-3"
                /><span
                    v-if="form.errors.meta_description"
                    class="text-red-700 dark:text-red-400"
                    >{{ form.errors.meta_description }}</span
                ></label
            >
            <label class="grid gap-2 text-sm"
                ><span
                    >{{ copy.status }}
                    <span class="text-red-600" aria-hidden="true">*</span></span
                ><select
                    v-model="form.status"
                    class="rounded-md border bg-background p-2.5"
                >
                    <option value="draft">{{ copy.draft }}</option>
                    <option value="published">{{ copy.published }}</option>
                </select></label
            >
            <label class="flex items-center gap-2 text-sm"
                ><input v-model="form.show_in_footer" type="checkbox" />{{
                    copy.footer
                }}</label
            >
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
