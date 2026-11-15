<script setup lang="ts">
import { Head, Link, useForm } from '@inertiajs/vue3';
import Pagination from '@/components/Pagination.vue';
import { orderCopy as copy } from '@/lib/orderCopy';
import { money, type Paginated } from '@/types/catalog';
import type { Order } from '@/types/commerce';
const props = defineProps<{
    orders: Paginated<Order>;
    filters: { search?: string; payment?: string; fulfillment?: string };
}>();
const filter = useForm({
    search: props.filters.search ?? '',
    payment: props.filters.payment ?? '',
    fulfillment: props.filters.fulfillment ?? '',
});
</script>
<template>
    <Head :title="copy.title" />
    <div class="p-6 md:p-10">
        <h1 class="text-3xl font-semibold">{{ copy.title }}</h1>
        <form
            class="mt-8 flex flex-wrap items-end gap-4"
            @submit.prevent="filter.get('/admin/orders')"
        >
            <label class="grid gap-2 text-sm"
                >{{ copy.search
                }}<input
                    v-model="filter.search"
                    type="search"
                    class="rounded-md border bg-transparent p-2.5" /></label
            ><label class="grid gap-2 text-sm"
                >{{ copy.payment
                }}<select
                    v-model="filter.payment"
                    class="rounded-md border bg-background p-2.5"
                >
                    <option value="">{{ copy.all }}</option>
                    <option v-for="status in copy.paymentStates" :key="status">
                        {{ status }}
                    </option>
                </select></label
            ><label class="grid gap-2 text-sm"
                >{{ copy.fulfillment
                }}<select
                    v-model="filter.fulfillment"
                    class="rounded-md border bg-background p-2.5"
                >
                    <option value="">{{ copy.all }}</option>
                    <option v-for="status in copy.states" :key="status">
                        {{ status }}
                    </option>
                    <option>cancelled</option>
                </select></label
            ><button class="rounded-md border px-4 py-2.5">
                {{ copy.apply }}
            </button>
        </form>
        <div class="mt-6 overflow-x-auto rounded-xl border">
            <table class="w-full text-left text-sm">
                <thead class="bg-muted">
                    <tr>
                        <th class="p-4">{{ copy.reference }}</th>
                        <th class="p-4">{{ copy.customer }}</th>
                        <th class="p-4">{{ copy.payment }}</th>
                        <th class="p-4">{{ copy.fulfillment }}</th>
                        <th class="p-4">{{ copy.total }}</th>
                    </tr>
                </thead>
                <tbody>
                    <tr
                        v-for="order in orders.data"
                        :key="order.id"
                        class="border-t"
                    >
                        <td class="p-4">
                            <Link
                                :href="`/admin/orders/${order.id}`"
                                class="underline"
                                >{{ order.reference }}</Link
                            >
                        </td>
                        <td class="p-4">
                            {{ order.customer_name }}
                            <p class="mt-1 text-xs text-muted-foreground">
                                {{ order.customer_email }}
                            </p>
                        </td>
                        <td class="p-4">{{ order.payment_status }}</td>
                        <td class="p-4">
                            {{ order.fulfillment_status ?? '—' }}
                        </td>
                        <td class="p-4 whitespace-nowrap">
                            {{ money(order.total_sen) }}
                        </td>
                    </tr>
                    <tr v-if="!orders.data.length">
                        <td colspan="5" class="p-10 text-center">
                            {{ copy.empty }}
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
        <Pagination :links="orders.links" />
    </div>
</template>
