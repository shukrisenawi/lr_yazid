<script setup lang="ts">
import { Head, useForm } from '@inertiajs/vue3';
import RinggitInput from '@/components/RinggitInput.vue';
const props = defineProps<{
    settings: {
        shipping_fee_sen: number;
        shipping_enabled: boolean;
        reservation_minutes: number;
    };
}>();
const form = useForm({
    shipping_fee_sen: props.settings.shipping_fee_sen,
    shipping_enabled: props.settings.shipping_enabled,
    reservation_minutes: props.settings.reservation_minutes,
});
</script>
<template>
    <Head title="Shipping Settings" />
    <div class="max-w-3xl p-6 md:p-10">
        <h1 class="text-3xl font-semibold">Shipping settings</h1>
        <p class="mt-3 text-muted-foreground">
            Configure flat-rate shipping within Malaysia. Existing orders retain
            their saved charges.
        </p>
        <form
            class="mt-8 space-y-6 rounded-xl border p-6"
            @submit.prevent="form.put('/admin/shipping')"
        >
            <div
                v-if="Object.keys(form.errors).length"
                role="alert"
                class="text-sm text-red-700"
            >
                {{ Object.values(form.errors).join(' ') }}
            </div>
            <label class="grid gap-2 text-sm"
                >Shipping charge (RM)<RinggitInput
                    v-model="form.shipping_fee_sen"
                    type="number"
                    min="0"
                    max="10000"
                    required
                    class="rounded-md border bg-transparent p-2.5"
                /><span class="text-xs text-muted-foreground"
                    >Enter the amount in RM, for example 10.00.</span
                ></label
            ><label class="grid gap-2 text-sm"
                >Stock reservation duration (minutes)<input
                    v-model="form.reservation_minutes"
                    type="number"
                    min="5"
                    max="120"
                    required
                    class="rounded-md border bg-transparent p-2.5" /></label
            ><label class="flex items-center gap-2 text-sm"
                ><input
                    v-model="form.shipping_enabled"
                    type="checkbox"
                />Shipping available</label
            ><button
                :disabled="form.processing"
                class="rounded-md bg-emerald-800 px-5 py-2.5 text-white disabled:opacity-50"
            >
                {{ form.processing ? 'Saving…' : 'Save settings' }}
            </button>
        </form>
    </div>
</template>
