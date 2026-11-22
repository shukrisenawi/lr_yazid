<script setup lang="ts">
import { useForm, usePage } from '@inertiajs/vue3';
import type { Address } from '@/types/commerce';
const props = defineProps<{ address?: Address | null; states: string[] }>();
const emit = defineEmits<{ saved: []; cancel: [] }>();
const user = usePage().props.auth.user;
const form = useForm({
    full_name: props.address?.full_name ?? user.name,
    phone: props.address?.phone ?? user.phone ?? '',
    line1: props.address?.line1 ?? '',
    line2: props.address?.line2 ?? '',
    city: props.address?.city ?? '',
    state: props.address?.state ?? '',
    postcode: props.address?.postcode ?? '',
    is_default: props.address?.is_default ?? false,
});
function save() {
    const options = {
        preserveScroll: true,
        onSuccess: () => {
            if (!props.address) form.reset();
            emit('saved');
        },
    };
    if (props.address) form.put(`/addresses/${props.address.id}`, options);
    else form.post('/addresses', options);
}
</script>
<template>
    <form class="space-y-5 rounded-xl border p-6" @submit.prevent="save">
        <h2 class="text-xl font-semibold">
            {{ address ? 'Edit shipping address' : 'Add shipping address' }}
        </h2>
        <div
            v-if="Object.keys(form.errors).length"
            role="alert"
            class="text-sm text-red-700 dark:text-red-400"
        >
            {{ Object.values(form.errors).join(' ') }}
        </div>
        <div class="grid gap-4 sm:grid-cols-2">
            <label class="grid gap-2 text-sm"
                ><span
                    >Full name
                    <span class="text-red-600" aria-hidden="true">*</span></span
                ><input
                    v-model="form.full_name"
                    required
                    autocomplete="name"
                    class="rounded-md border bg-transparent p-2.5"
            /></label>
            <label class="grid gap-2 text-sm"
                ><span
                    >Phone number
                    <span class="text-red-600" aria-hidden="true">*</span></span
                ><input
                    v-model="form.phone"
                    type="tel"
                    required
                    autocomplete="tel"
                    class="rounded-md border bg-transparent p-2.5"
            /></label>
            <label class="grid gap-2 text-sm sm:col-span-2"
                ><span
                    >Address line 1
                    <span class="text-red-600" aria-hidden="true">*</span></span
                ><input
                    v-model="form.line1"
                    required
                    autocomplete="address-line1"
                    class="rounded-md border bg-transparent p-2.5"
            /></label>
            <label class="grid gap-2 text-sm sm:col-span-2"
                >Address line 2 (optional)<input
                    v-model="form.line2"
                    autocomplete="address-line2"
                    class="rounded-md border bg-transparent p-2.5"
            /></label>
            <label class="grid gap-2 text-sm"
                ><span
                    >City
                    <span class="text-red-600" aria-hidden="true">*</span></span
                ><input
                    v-model="form.city"
                    required
                    autocomplete="address-level2"
                    class="rounded-md border bg-transparent p-2.5"
            /></label>
            <label class="grid gap-2 text-sm"
                ><span
                    >State
                    <span class="text-red-600" aria-hidden="true">*</span></span
                ><select
                    v-model="form.state"
                    required
                    autocomplete="address-level1"
                    class="rounded-md border bg-background p-2.5"
                >
                    <option value="" disabled>Select a state</option>
                    <option v-for="state in states" :key="state" :value="state">
                        {{ state }}
                    </option>
                </select></label
            >
            <label class="grid gap-2 text-sm"
                ><span
                    >Postcode
                    <span class="text-red-600" aria-hidden="true">*</span></span
                ><input
                    v-model="form.postcode"
                    required
                    pattern="[0-9]{5}"
                    inputmode="numeric"
                    maxlength="5"
                    autocomplete="postal-code"
                    class="rounded-md border bg-transparent p-2.5"
            /></label>
            <label class="flex items-center gap-2 text-sm"
                ><input v-model="form.is_default" type="checkbox" />Set as
                default shipping address</label
            >
        </div>
        <p class="text-xs text-muted-foreground">
            Shipping is currently available within Malaysia only.
        </p>
        <div class="flex gap-3">
            <button
                :disabled="form.processing"
                class="rounded-md bg-emerald-800 px-5 py-2.5 text-white disabled:opacity-50"
            >
                {{ form.processing ? 'Saving…' : 'Save address' }}</button
            ><button
                v-if="address"
                type="button"
                class="rounded-md border px-4 py-2.5"
                @click="emit('cancel')"
            >
                Cancel
            </button>
        </div>
    </form>
</template>
