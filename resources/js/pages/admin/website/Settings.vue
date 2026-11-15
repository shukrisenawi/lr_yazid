<script setup lang="ts">
import { Head, Link, useForm, usePage } from '@inertiajs/vue3';
import { ref } from 'vue';
import { websiteCopy as copy } from '@/lib/websiteCopy';
import { paymentSettingsCopy } from '@/lib/paymentSettingsCopy';
import type { Website } from '@/types/website';
const props = defineProps<{
    settings: Omit<Website, 'logo_url' | 'favicon_url'> & { version: number };
}>();
const page = usePage();
const uploadKey = ref(0);
const form = useForm({
    _method: 'put',
    website_name: props.settings.website_name,
    contact_email: props.settings.contact_email ?? '',
    contact_phone: props.settings.contact_phone ?? '',
    address: props.settings.address ?? '',
    footer_information: props.settings.footer_information,
    social_links: [...(props.settings.social_links ?? [])],
    logo: null as File | null,
    favicon: null as File | null,
    remove_logo: false,
    remove_favicon: false,
    version: props.settings.version,
});
function upload(event: Event, field: 'logo' | 'favicon') {
    form[field] = (event.target as HTMLInputElement).files?.[0] ?? null;
}
function save() {
    form.post('/admin/website-settings', {
        forceFormData: true,
        preserveScroll: true,
        onSuccess: () => {
            form.version = props.settings.version;
            form.logo = null;
            form.favicon = null;
            form.remove_logo = false;
            form.remove_favicon = false;
            uploadKey.value++;
        },
    });
}
</script>
<template>
    <Head :title="copy.settings" />
    <div class="mx-auto w-full max-w-4xl p-6 md:p-10">
        <h1 class="text-3xl font-semibold">{{ copy.settings }}</h1>
        <p class="mt-4 text-sm leading-relaxed text-muted-foreground">
            {{ copy.settingsHelp }}
        </p>
        <nav class="mt-4 flex flex-wrap gap-5 text-sm">
            <Link href="/admin/banners" class="underline">{{
                copy.banners
            }}</Link
            ><Link href="/admin/payment-settings" class="underline">{{
                paymentSettingsCopy.title
            }}</Link>
        </nav>
        <form class="mt-8 space-y-6" @submit.prevent="save">
            <label class="grid gap-2 text-sm"
                >{{ copy.name
                }}<input
                    v-model="form.website_name"
                    required
                    maxlength="80"
                    class="rounded-md border bg-transparent p-2.5"
            /></label>
            <div class="grid gap-5 sm:grid-cols-2">
                <label class="grid gap-2 text-sm"
                    >{{ copy.email
                    }}<input
                        v-model="form.contact_email"
                        type="email"
                        maxlength="254"
                        class="rounded-md border bg-transparent p-2.5"
                /></label>
                <label class="grid gap-2 text-sm"
                    >{{ copy.phone
                    }}<input
                        v-model="form.contact_phone"
                        type="tel"
                        maxlength="40"
                        class="rounded-md border bg-transparent p-2.5"
                /></label>
            </div>
            <label class="grid gap-2 text-sm"
                >{{ copy.address
                }}<textarea
                    v-model="form.address"
                    rows="3"
                    maxlength="1000"
                    class="rounded-md border bg-transparent p-3"
                />
            </label>
            <label class="grid gap-2 text-sm"
                >{{ copy.footer
                }}<textarea
                    v-model="form.footer_information"
                    required
                    rows="2"
                    maxlength="300"
                    class="rounded-md border bg-transparent p-3"
                />
            </label>
            <p class="text-sm text-muted-foreground">{{ copy.imageHelp }}</p>
            <div class="grid gap-6 sm:grid-cols-2">
                <div>
                    <img
                        v-if="page.props.website.logo_url"
                        :src="page.props.website.logo_url"
                        alt=""
                        class="mb-3 h-16 max-w-full object-contain"
                    /><label class="grid gap-2 text-sm"
                        >{{ copy.logo
                        }}<input
                            :key="`logo-${uploadKey}`"
                            type="file"
                            accept="image/jpeg,image/png,image/webp"
                            class="max-w-full rounded-md border p-2"
                            @change="upload($event, 'logo')" /></label
                    ><label class="mt-3 flex items-center gap-2 text-sm"
                        ><input v-model="form.remove_logo" type="checkbox" />{{
                            copy.removeLogo
                        }}</label
                    >
                </div>
                <div>
                    <img
                        v-if="page.props.website.favicon_url"
                        :src="page.props.website.favicon_url"
                        alt=""
                        class="mb-3 size-12 object-contain"
                    /><label class="grid gap-2 text-sm"
                        >{{ copy.favicon
                        }}<input
                            :key="`favicon-${uploadKey}`"
                            type="file"
                            accept="image/jpeg,image/png,image/webp"
                            class="max-w-full rounded-md border p-2"
                            @change="upload($event, 'favicon')" /></label
                    ><label class="mt-3 flex items-center gap-2 text-sm"
                        ><input
                            v-model="form.remove_favicon"
                            type="checkbox"
                        />{{ copy.removeFavicon }}</label
                    >
                </div>
            </div>
            <fieldset class="space-y-4 border-y py-5">
                <legend class="text-base font-semibold">
                    {{ copy.social }}
                </legend>
                <div
                    v-for="(link, index) in form.social_links"
                    :key="index"
                    class="grid gap-3 sm:grid-cols-[1fr_2fr_auto] sm:items-end"
                >
                    <label class="grid gap-2 text-sm"
                        >{{ copy.label
                        }}<input
                            v-model="link.label"
                            required
                            maxlength="40"
                            class="min-w-0 rounded-md border bg-transparent p-2.5"
                    /></label>
                    <label class="grid gap-2 text-sm"
                        >{{ copy.url
                        }}<input
                            v-model="link.url"
                            required
                            type="url"
                            maxlength="500"
                            class="min-w-0 rounded-md border bg-transparent p-2.5"
                    /></label>
                    <button
                        type="button"
                        class="rounded-md border px-3 py-2.5 text-sm"
                        @click="form.social_links.splice(index, 1)"
                    >
                        {{ copy.remove }}
                    </button>
                </div>
                <button
                    type="button"
                    :disabled="form.social_links.length >= 6"
                    class="rounded-md border px-4 py-2 text-sm disabled:opacity-50"
                    @click="form.social_links.push({ label: '', url: '' })"
                >
                    {{ copy.addSocial }}
                </button>
            </fieldset>
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
