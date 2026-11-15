<script setup lang="ts">
import { Head, Link, useForm } from '@inertiajs/vue3';
import { documentCopy as copy } from '@/lib/documentCopy';
const props = defineProps<{
    settings: {
        enabled: boolean;
        seller_name: string | null;
        seller_address: string | null;
        seller_email: string | null;
        registration_number: string | null;
        footer_note: string | null;
    };
}>();
const form = useForm({
    enabled: props.settings.enabled,
    seller_name: props.settings.seller_name ?? '',
    seller_address: props.settings.seller_address ?? '',
    seller_email: props.settings.seller_email ?? '',
    registration_number: props.settings.registration_number ?? '',
    footer_note: props.settings.footer_note ?? '',
});
</script>
<template>
    <Head :title="copy.settings" />
    <div class="mx-auto w-full max-w-3xl p-6 md:p-10">
        <Link href="/admin/orders" class="text-sm underline">{{
            copy.back
        }}</Link>
        <h1 class="mt-5 text-3xl font-semibold">{{ copy.settings }}</h1>
        <p class="mt-4 text-sm leading-relaxed text-muted-foreground">
            {{ copy.help }}
        </p>
        <form
            class="mt-8 space-y-5"
            @submit.prevent="form.put('/admin/invoice-settings')"
        >
            <label class="grid gap-2 text-sm"
                >{{ copy.name
                }}<input
                    v-model="form.seller_name"
                    required
                    maxlength="200"
                    class="rounded-md border bg-transparent p-2.5" /></label
            ><label class="grid gap-2 text-sm"
                >{{ copy.address
                }}<textarea
                    v-model="form.seller_address"
                    required
                    maxlength="2000"
                    rows="4"
                    class="rounded-md border bg-transparent p-3"
                /></label
            ><label class="grid gap-2 text-sm"
                >{{ copy.email
                }}<input
                    v-model="form.seller_email"
                    type="email"
                    required
                    maxlength="254"
                    class="rounded-md border bg-transparent p-2.5" /></label
            ><label class="grid gap-2 text-sm"
                >{{ copy.registration
                }}<input
                    v-model="form.registration_number"
                    maxlength="100"
                    class="rounded-md border bg-transparent p-2.5" /></label
            ><label class="grid gap-2 text-sm"
                >{{ copy.footer
                }}<textarea
                    v-model="form.footer_note"
                    maxlength="1000"
                    rows="3"
                    class="rounded-md border bg-transparent p-3"
                /></label
            ><label class="flex items-center gap-2 text-sm"
                ><input v-model="form.enabled" type="checkbox" />{{
                    copy.enabled
                }}</label
            >
            <p
                v-if="Object.keys(form.errors).length"
                role="alert"
                class="text-sm text-red-700"
            >
                {{ Object.values(form.errors).join(' ') }}
            </p>
            <button
                :disabled="form.processing"
                class="rounded-md bg-emerald-800 px-5 py-3 text-white disabled:opacity-50"
            >
                {{ copy.save }}
            </button>
        </form>
    </div>
</template>
