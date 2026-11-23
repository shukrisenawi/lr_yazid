<script setup lang="ts">
import AdminRecordAction from '@/components/AdminRecordAction.vue';
import { Head, Link } from '@inertiajs/vue3';
import Pagination from '@/components/Pagination.vue';
import CustomerContactEditor from '@/components/CustomerContactEditor.vue';
import { customerCopy as copy } from '@/lib/customerCopy';
import { money, type Paginated } from '@/types/catalog';
import { dateTime } from '@/types/commerce';
import type { Membership } from '@/types/membership';
import type { CustomerContactChange } from '@/types/customerContact';
type Row = {
    id: number;
    reference?: string;
    payment_status?: string;
    status?: string;
    type?: string;
    total_sen?: number;
    amount_sen?: number;
    created_at: string;
    expires_at?: string;
    program?: { title: string };
};
defineProps<{
    customer: {
        id: number;
        name: string;
        email: string;
        phone: string | null;
        created_at: string;
        email_verified_at: string | null;
        profile_version: number;
    };
    isVip: boolean;
    section: 'orders' | 'memberships' | 'programs' | 'appointments';
    records: Paginated<Row>;
    memberships: Membership[];
    orderCount: number;
    contactHistory: Paginated<CustomerContactChange>;
}>();
</script>
<template>
    <Head :title="`${copy.profile} · ${customer.name}`" />
    <div class="mx-auto w-full max-w-6xl p-6 md:p-10">
        <Link href="/admin/customers" class="text-sm underline">{{
            copy.back
        }}</Link>
        <h1 class="mt-5 text-3xl font-semibold">{{ customer.name }}</h1>
        <AdminRecordAction module="customers" :record-id="customer.id" />
        <section class="mt-6 rounded-xl border p-6">
            <p class="font-medium">{{ isVip ? copy.vip : copy.nonVip }}</p>
            <p class="mt-3 text-sm">
                {{ customer.email }} · {{ customer.phone }}
            </p>
            <p class="mt-3 text-sm text-muted-foreground">
                {{
                    customer.email_verified_at ? copy.verified : copy.unverified
                }}
                · {{ copy.joined }}: {{ dateTime(customer.created_at) }} ·
                {{ copy.orders }}: {{ orderCount }}
            </p>
        </section>
        <p class="mt-5 text-sm text-muted-foreground">{{ copy.readOnly }}</p>
        <CustomerContactEditor :customer="customer" :history="contactHistory" />
        <div class="mt-6 grid gap-4 md:grid-cols-2">
            <section
                v-for="membership in memberships"
                :key="membership.id"
                class="rounded-xl border p-5"
            >
                <h2 class="font-medium">{{ membership.plan.name }}</h2>
                <p class="mt-3 text-sm">
                    {{ membership.status }} · {{ copy.expiry }}:
                    {{ dateTime(membership.expires_at) }}
                </p>
            </section>
        </div>
        <h2 class="mt-10 text-2xl font-semibold">{{ copy.history }}</h2>
        <nav class="mt-5 flex flex-wrap gap-3" :aria-label="copy.history">
            <Link
                v-for="key in [
                    'orders',
                    'memberships',
                    'programs',
                    'appointments',
                ] as const"
                :key="key"
                :href="`/admin/customers/${customer.id}?section=${key}`"
                :aria-current="section === key ? 'page' : undefined"
                class="rounded-md border px-4 py-2 text-sm"
                :class="section === key ? 'bg-emerald-800 text-white' : ''"
                >{{ copy[key] }}</Link
            >
        </nav>
        <div class="mt-6 overflow-x-auto rounded-xl border">
            <table class="w-full text-left text-sm">
                <thead class="bg-muted">
                    <tr>
                        <th class="p-4">{{ copy.reference }}</th>
                        <th class="p-4">{{ copy.status }}</th>
                        <th class="p-4">{{ copy.amount }}</th>
                        <th class="p-4">{{ copy.date }}</th>
                    </tr>
                </thead>
                <tbody>
                    <tr
                        v-for="record in records.data"
                        :key="record.id"
                        class="border-t"
                    >
                        <td class="p-4">
                            <Link
                                v-if="
                                    section === 'appointments' ||
                                    section === 'orders'
                                "
                                :href="
                                    section === 'orders'
                                        ? `/admin/orders/${record.id}`
                                        : `/admin/appointments/${record.id}`
                                "
                                class="underline"
                                >{{ record.reference }}</Link
                            ><span v-else>{{
                                record.program?.title ??
                                record.reference ??
                                `#${record.id}`
                            }}</span>
                        </td>
                        <td class="p-4">
                            {{
                                record.payment_status ??
                                record.status ??
                                record.type
                            }}
                        </td>
                        <td class="p-4 whitespace-nowrap">
                            {{
                                record.total_sen !== undefined
                                    ? money(record.total_sen)
                                    : record.amount_sen !== undefined
                                      ? money(record.amount_sen)
                                      : '—'
                            }}
                        </td>
                        <td class="p-4 whitespace-nowrap">
                            {{ dateTime(record.created_at) }}
                        </td>
                    </tr>
                    <tr v-if="!records.data.length">
                        <td
                            colspan="4"
                            class="p-10 text-center text-muted-foreground"
                        >
                            {{ copy.noRecords }}
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
        <Pagination :links="records.links" />
    </div>
</template>
