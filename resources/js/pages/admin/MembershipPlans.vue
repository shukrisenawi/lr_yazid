<script setup lang="ts">
import { Head, Link, useForm } from '@inertiajs/vue3';
import { ref } from 'vue';
import AdminRecordAction from '@/components/AdminRecordAction.vue';
import AdminEditButton from '@/components/AdminEditButton.vue';
import RinggitInput from '@/components/RinggitInput.vue';
import { money } from '@/types/catalog';
import type { MembershipPlan } from '@/types/membership';
defineProps<{ plans: MembershipPlan[] }>();
const editing = ref<number | null>(null);
const benefits = ref('VIP dashboard\nVIP programs\nAppointment booking');
const form = useForm({
    name: 'Annual VIP Membership',
    price_sen: 19900,
    duration_months: 12,
    is_active: true,
    description: '',
    benefits: [] as string[],
});
function edit(plan: MembershipPlan) {
    editing.value = plan.id;
    form.name = plan.name;
    form.price_sen = plan.price_sen;
    form.duration_months = plan.duration_months;
    form.is_active = plan.is_active;
    form.description = plan.product?.short_description ?? '';
    benefits.value = plan.benefits.join('\n');
}
function save() {
    form.benefits = benefits.value
        .split('\n')
        .map((value) => value.trim())
        .filter(Boolean);
    const options = { preserveScroll: true };
    if (editing.value)
        form.put(`/admin/membership-plans/${editing.value}`, options);
    else
        form.post('/admin/membership-plans', {
            ...options,
            onSuccess: () => {
                form.reset();
            },
        });
}
</script>
<template>
    <Head title="Membership Plans" />
    <div class="max-w-5xl p-6 md:p-10">
        <h1 class="text-3xl font-semibold">Membership plans</h1>
        <div class="my-8 grid gap-4 md:grid-cols-2">
            <section
                v-for="plan in plans"
                :key="plan.id"
                class="rounded-xl border p-5"
            >
                <h2 class="font-semibold">{{ plan.name }}</h2>
                <p class="mt-3 text-sm">
                    {{ money(plan.price_sen) }} /
                    {{ plan.duration_months }} months ·
                    {{ plan.is_active ? 'Active' : 'Inactive' }}
                </p>
                <div class="mt-4 flex flex-wrap gap-4 text-sm">
                    <AdminEditButton
                        :label="`Edit ${plan.name}`"
                        @click="edit(plan)"
                    />
                    <AdminRecordAction
                        module="membership-plans"
                        :record-id="plan.id"
                        :record-name="plan.name"
                        @success="
                            editing = null;
                            form.reset();
                        "
                    />
                    <Link
                        v-if="plan.product"
                        :href="`/admin/products/${plan.product.id}/edit`"
                        class="underline"
                        >Manage images and description</Link
                    >
                </div>
            </section>
        </div>
        <form class="space-y-5 rounded-xl border p-6" @submit.prevent="save">
            <h2 class="text-xl font-semibold">
                {{
                    editing ? 'Edit membership plan' : 'Create membership plan'
                }}
            </h2>
            <p
                v-if="Object.keys(form.errors).length"
                role="alert"
                class="text-sm text-red-700"
            >
                {{ Object.values(form.errors).join(' ') }}
            </p>
            <label class="grid gap-2 text-sm"
                ><span
                    >Plan name
                    <span class="text-red-600" aria-hidden="true">*</span></span
                ><input
                    v-model="form.name"
                    required
                    class="rounded-md border bg-transparent p-2.5"
            /></label>
            <div class="grid gap-5 sm:grid-cols-2">
                <label class="grid gap-2 text-sm"
                    ><span
                        >Price (RM)
                        <span class="text-red-600" aria-hidden="true"
                            >*</span
                        ></span
                    ><RinggitInput
                        v-model="form.price_sen"
                        type="number"
                        min="1"
                        max="1000000"
                        required
                        class="rounded-md border bg-transparent p-2.5" /></label
                ><label class="grid gap-2 text-sm"
                    ><span
                        >Duration (calendar months)
                        <span class="text-red-600" aria-hidden="true"
                            >*</span
                        ></span
                    ><input
                        v-model="form.duration_months"
                        type="number"
                        min="1"
                        max="60"
                        required
                        class="rounded-md border bg-transparent p-2.5"
                /></label>
            </div>
            <label class="grid gap-2 text-sm"
                >Description<textarea
                    v-model="form.description"
                    rows="3"
                    class="rounded-md border bg-transparent p-3"
                /></label
            ><label class="grid gap-2 text-sm"
                ><span
                    >Benefits (one per line)
                    <span class="text-red-600" aria-hidden="true">*</span></span
                ><textarea
                    v-model="benefits"
                    required
                    rows="5"
                    class="rounded-md border bg-transparent p-3"
                /></label
            ><label class="flex items-center gap-2 text-sm"
                ><input v-model="form.is_active" type="checkbox" />Plan
                available for purchase</label
            >
            <div class="flex gap-4">
                <button
                    :disabled="form.processing"
                    class="rounded-md bg-emerald-800 px-5 py-2.5 text-white disabled:opacity-50"
                >
                    {{ form.processing ? 'Saving…' : 'Save plan' }}</button
                ><button
                    v-if="editing"
                    type="button"
                    class="underline"
                    @click="
                        editing = null;
                        form.reset();
                    "
                >
                    Create another plan
                </button>
            </div>
            <p class="text-xs text-muted-foreground">
                Program and appointment modules must be released before those
                benefits can be used.
            </p>
        </form>
    </div>
</template>
