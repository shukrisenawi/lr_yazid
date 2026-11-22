<script setup lang="ts">
import { useForm } from '@inertiajs/vue3';
import Pagination from '@/components/Pagination.vue';
import { customerContactCopy as copy } from '@/lib/customerContactCopy';
import { dateTime } from '@/types/commerce';
import type { Paginated } from '@/types/catalog';
import type {
    CustomerContact,
    CustomerContactChange,
} from '@/types/customerContact';
const props = defineProps<{
    customer: CustomerContact;
    history: Paginated<CustomerContactChange>;
}>();
const form = useForm({
    name: props.customer.name,
    phone: props.customer.phone ?? '',
    profile_version: props.customer.profile_version,
    reason: '',
});
function save() {
    if (!window.confirm(copy.confirm)) return;
    form.put(`/admin/customers/${props.customer.id}`, {
        preserveScroll: true,
        onSuccess: () => {
            form.name = props.customer.name;
            form.phone = props.customer.phone ?? '';
            form.profile_version = props.customer.profile_version;
            form.reason = '';
        },
    });
}
</script>
<template>
    <section
        class="mt-8 space-y-5 rounded-xl border p-6"
        aria-labelledby="contact-editor-title"
    >
        <h2 id="contact-editor-title" class="text-xl font-semibold">
            {{ copy.title }}
        </h2>
        <p class="text-sm leading-relaxed text-muted-foreground">
            {{ copy.help }}
        </p>
        <form class="space-y-4" @submit.prevent="save">
            <label class="grid gap-2 text-sm"
                ><span
                    >{{ copy.name }}
                    <span class="text-red-600" aria-hidden="true">*</span></span
                ><input
                    v-model="form.name"
                    required
                    maxlength="255"
                    autocomplete="off"
                    class="rounded-md border bg-transparent p-2.5"
            /></label>
            <label class="grid gap-2 text-sm"
                ><span
                    >{{ copy.phone }}
                    <span class="text-red-600" aria-hidden="true">*</span></span
                ><input
                    v-model="form.phone"
                    type="tel"
                    required
                    maxlength="40"
                    autocomplete="off"
                    class="rounded-md border bg-transparent p-2.5"
            /></label>
            <label class="grid gap-2 text-sm"
                ><span
                    >{{ copy.reason }}
                    <span class="text-red-600" aria-hidden="true">*</span></span
                ><textarea
                    v-model="form.reason"
                    required
                    minlength="5"
                    maxlength="1000"
                    rows="3"
                    class="rounded-md border bg-transparent p-3"
                />
            </label>
            <p
                v-if="Object.keys(form.errors).length"
                role="alert"
                class="text-sm text-red-700 dark:text-red-400"
            >
                {{ Object.values(form.errors).join(' ') }}
            </p>
            <a
                v-if="form.errors.profile_version"
                :href="`/admin/customers/${customer.id}`"
                class="block text-sm underline"
                >{{ copy.reload }}</a
            >
            <button
                :disabled="form.processing || !!form.errors.profile_version"
                class="rounded-md border px-5 py-3 text-sm disabled:opacity-50"
            >
                {{ copy.save }}
            </button>
        </form>
    </section>
    <section class="mt-8 space-y-4" aria-labelledby="contact-history-title">
        <h2 id="contact-history-title" class="text-xl font-semibold">
            {{ copy.history }}
        </h2>
        <p class="text-sm leading-relaxed text-muted-foreground">
            {{ copy.historyHelp }}
        </p>
        <p v-if="!history.data.length" class="text-sm text-muted-foreground">
            {{ copy.empty }}
        </p>
        <ol v-else class="space-y-3">
            <li
                v-for="change in history.data"
                :key="change.id"
                class="rounded-md border p-4 text-sm [overflow-wrap:anywhere]"
            >
                <p>
                    {{ dateTime(change.created_at) }} · {{ copy.actor }} #{{
                        change.admin_id
                    }}
                    · {{ copy.revision }} {{ change.profile_version }}
                </p>
                <p class="mt-3">
                    {{ copy.before }}: {{ change.previous_values.name }} ·
                    {{ change.previous_values.phone ?? '—' }}
                </p>
                <p class="mt-2">
                    {{ copy.after }}: {{ change.new_values.name }} ·
                    {{ change.new_values.phone ?? '—' }}
                </p>
                <p class="mt-3 whitespace-pre-line">{{ change.reason }}</p>
            </li>
        </ol>
        <Pagination :links="history.links" />
    </section>
</template>
