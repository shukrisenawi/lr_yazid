<script setup lang="ts">
import { Head, Link, useForm } from '@inertiajs/vue3';
import AdminRecordAction from '@/components/AdminRecordAction.vue';
import { recordManagementCopy as recordCopy } from '@/lib/recordManagementCopy';
import Pagination from '@/components/Pagination.vue';
import StatusBadge from '@/components/StatusBadge.vue';
import { money, type Paginated } from '@/types/catalog';
import type { Payment } from '@/types/commerce';
const props = defineProps<{
    payments: Paginated<
        Payment & {
            archived_at: string | null;
            order: {
                reference: string;
                customer_name: string;
                customer_email: string;
            };
        }
    >;
    status: string;
    archive: string;
}>();
const filter = useForm({ status: props.status, archive: props.archive });
const reconcile = useForm({});
</script>
<template>
    <Head title="Payments" />
    <div class="p-6 md:p-10">
        <div class="flex flex-wrap items-center justify-between gap-4">
            <h1 class="text-3xl font-semibold">Payments</h1>
            <Link href="/admin/payment-settings" class="text-sm underline"
                >Payment settings</Link
            >
        </div>
        <form
            class="mt-6 flex items-end gap-3"
            @submit.prevent="filter.get('/admin/payments')"
        >
            <label class="grid gap-2 text-sm"
                >{{ recordCopy.view }}
                <select
                    v-model="filter.archive"
                    class="rounded-md border bg-background p-2"
                >
                    <option value="active">{{ recordCopy.active }}</option>
                    <option value="archived">{{ recordCopy.archived }}</option>
                    <option value="all">{{ recordCopy.all }}</option>
                </select>
            </label>
            <label class="grid gap-2 text-sm"
                >Payment status<select
                    v-model="filter.status"
                    class="rounded-md border bg-background p-2"
                >
                    <option value="">All statuses</option>
                    <option
                        v-for="status in [
                            'creating',
                            'creation_unknown',
                            'pending',
                            'paid',
                            'failed',
                            'expired',
                        ]"
                        :key="status"
                        :value="status"
                    >
                        {{ status.replaceAll('_', ' ') }}
                    </option>
                </select></label
            ><button class="rounded-md border px-4 py-2">Apply</button>
        </form>
        <p
            v-if="Object.keys(reconcile.errors).length"
            role="alert"
            class="mt-5 text-sm text-red-700"
        >
            {{ Object.values(reconcile.errors).join(' ') }}
        </p>
        <div class="mt-6 overflow-x-auto rounded-xl border">
            <table class="w-full text-left text-sm">
                <thead class="bg-muted">
                    <tr>
                        <th class="p-4">Order / customer</th>
                        <th class="p-4">Amount</th>
                        <th class="p-4">Payment</th>
                        <th class="p-4">References</th>
                        <th class="p-4">Action</th>
                    </tr>
                </thead>
                <tbody>
                    <tr
                        v-for="payment in payments.data"
                        :key="payment.id"
                        class="border-t"
                    >
                        <td class="p-4">
                            <p class="font-medium">
                                {{ payment.order.reference }}
                            </p>
                            <p class="mt-1">
                                {{ payment.order.customer_name }}
                            </p>
                            <p class="mt-1 text-xs text-muted-foreground">
                                {{ payment.order.customer_email }}
                            </p>
                        </td>
                        <td class="p-4 whitespace-nowrap">
                            {{ money(payment.amount_sen) }}
                        </td>
                        <td class="p-4">
                            <StatusBadge :status="payment.status" />
                            <p class="mt-2 text-xs text-muted-foreground">
                                {{ payment.environment }}
                            </p>
                            <p
                                v-if="payment.requires_review"
                                class="mt-2 text-xs text-red-700 dark:text-red-400"
                            >
                                Review required:
                                {{
                                    payment.review_reason?.replaceAll('_', ' ')
                                }}
                            </p>
                        </td>
                        <td class="p-4 text-xs">
                            <p>{{ payment.reference }}</p>
                            <p class="mt-2">
                                Bill: {{ payment.bill_code || 'Not confirmed' }}
                            </p>
                            <p class="mt-2">
                                Transaction:
                                {{
                                    payment.transaction_reference ||
                                    'Not confirmed'
                                }}
                            </p>
                        </td>
                        <td class="p-4">
                            <button
                                v-if="payment.bill_code"
                                :disabled="reconcile.processing"
                                class="underline disabled:opacity-50"
                                @click="
                                    reconcile.post(
                                        `/admin/payments/${payment.id}/reconcile`,
                                        { preserveScroll: true },
                                    )
                                "
                            >
                                Reconcile
                            </button>
                            <p
                                v-else
                                class="max-w-52 text-xs text-muted-foreground"
                            >
                                Check the provider dashboard before retrying an
                                unconfirmed bill creation.
                            </p>
                            <Link
                                :href="`/admin/orders/${payment.order_id}`"
                                class="mt-3 block underline"
                                >View order / cancel unpaid order</Link
                            >
                            <AdminRecordAction
                                module="payments"
                                :record-id="payment.id"
                                archive
                                :archived="!!payment.archived_at"
                            />
                        </td>
                    </tr>
                    <tr v-if="!payments.data.length">
                        <td
                            colspan="5"
                            class="p-12 text-center text-muted-foreground"
                        >
                            No payment attempts yet.
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
        <Pagination :links="payments.links" />
    </div>
</template>
