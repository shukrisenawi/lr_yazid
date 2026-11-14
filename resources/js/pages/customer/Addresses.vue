<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import { ref } from 'vue';
import AddressForm from '@/components/AddressForm.vue';
import type { Address } from '@/types/commerce';
defineProps<{ addresses: Address[]; states: string[] }>();
const editing = ref<Address | null>(null);
function remove(address: Address) {
    if (
        confirm(
            'Remove this shipping address? Existing orders will keep their saved address.',
        )
    )
        router.delete(`/addresses/${address.id}`, { preserveScroll: true });
}
function makeDefault(address: Address) {
    router.put(
        `/addresses/${address.id}`,
        { ...address, is_default: true },
        { preserveScroll: true },
    );
}
</script>
<template>
    <Head title="My Addresses" />
    <div class="mx-auto w-full max-w-5xl p-6 md:p-10">
        <div class="flex flex-wrap items-center justify-between gap-4">
            <h1 class="text-3xl font-semibold">My addresses</h1>
            <Link href="/checkout" class="text-sm underline"
                >Return to checkout</Link
            >
        </div>
        <div class="my-8 grid gap-4 sm:grid-cols-2">
            <article
                v-for="address in addresses"
                :key="address.id"
                class="rounded-xl border p-5"
            >
                <h2 class="font-medium">
                    {{ address.full_name }}
                    <span
                        v-if="address.is_default"
                        class="ml-2 text-xs text-emerald-700 dark:text-emerald-400"
                        >Default</span
                    >
                </h2>
                <p class="mt-3 text-sm leading-relaxed text-muted-foreground">
                    {{ address.line1 }}<br v-if="address.line2" />{{
                        address.line2
                    }}<br />{{ address.postcode }} {{ address.city }},
                    {{ address.state }}<br />{{ address.phone }}
                </p>
                <div class="mt-4 flex flex-wrap gap-4 text-sm">
                    <button class="underline" @click="editing = address">
                        Edit</button
                    ><button
                        v-if="!address.is_default"
                        class="underline"
                        @click="makeDefault(address)"
                    >
                        Set default</button
                    ><button
                        class="text-red-700 underline dark:text-red-400"
                        @click="remove(address)"
                    >
                        Remove
                    </button>
                </div>
            </article>
            <p v-if="!addresses.length" class="text-muted-foreground">
                No saved addresses yet.
            </p>
        </div>
        <AddressForm
            :key="editing?.id ?? 'new'"
            :address="editing"
            :states="states"
            @saved="editing = null"
            @cancel="editing = null"
        />
    </div>
</template>
