<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import Pagination from '@/components/Pagination.vue';
import StatusBadge from '@/components/StatusBadge.vue';
import { money, type Paginated } from '@/types/catalog';
import { dateTime, type Order } from '@/types/commerce';
defineProps<{ orders: Paginated<Order> }>();
</script>
<template>
    <Head title="My Orders" />
    <div class="p-6 md:p-10">
        <h1 class="text-3xl font-semibold">My orders</h1>
        <div
            v-if="orders.data.length"
            class="mt-8 overflow-x-auto rounded-xl border"
        >
            <table class="w-full text-left text-sm">
                <thead class="bg-muted">
                    <tr>
                        <th class="p-4">Order</th>
                        <th class="p-4">Date</th>
                        <th class="p-4">Payment</th>
                        <th class="p-4">Fulfillment</th>
                        <th class="p-4">Total</th>
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
                                :href="`/orders/${order.id}`"
                                class="font-medium underline"
                                >{{ order.reference }}</Link
                            >
                        </td>
                        <td class="p-4 whitespace-nowrap">
                            {{ dateTime(order.created_at) }}
                        </td>
                        <td class="p-4">
                            <StatusBadge :status="order.payment_status" />
                        </td>
                        <td class="p-4">
                            <StatusBadge
                                v-if="order.fulfillment_status"
                                :status="order.fulfillment_status"
                            /><span v-else>Not applicable</span>
                        </td>
                        <td class="p-4 whitespace-nowrap">
                            {{ money(order.total_sen) }}
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
        <section
            v-else
            class="mt-8 rounded-xl border border-dashed p-12 text-center"
        >
            <h2 class="text-xl font-medium">No orders yet</h2>
            <Link href="/products" class="mt-5 inline-block underline"
                >Continue shopping</Link
            >
        </section>
        <Pagination :links="orders.links" />
    </div>
</template>
