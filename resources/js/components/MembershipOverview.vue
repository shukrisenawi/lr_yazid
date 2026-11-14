<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import AddToCartForm from '@/components/AddToCartForm.vue';
import Pagination from '@/components/Pagination.vue';
import StatusBadge from '@/components/StatusBadge.vue';
import { money } from '@/types/catalog';
import { dateTime } from '@/types/commerce';
import type { Membership, MembershipPageProps } from '@/types/membership';
defineProps<MembershipPageProps>();
const status = (membership: Membership) =>
    membership.status === 'revoked'
        ? 'revoked'
        : new Date(membership.expires_at).getTime() <= Date.now()
          ? 'expired'
          : membership.status;
</script>
<template>
    <Head title="VIP Membership" />
    <div class="mx-auto w-full max-w-6xl px-6 py-12">
        <h1 class="text-4xl font-semibold tracking-tight">
            Your VIP membership
        </h1>
        <p class="mt-4 max-w-2xl leading-relaxed text-muted-foreground">
            An annual membership for a more personal experience. Membership
            starts only after your payment is verified.
        </p>
        <div v-if="memberships.length" class="mt-8 grid gap-4 md:grid-cols-2">
            <section
                v-for="membership in memberships"
                :key="membership.id"
                class="rounded-xl border p-6"
            >
                <div class="flex flex-wrap items-center justify-between gap-3">
                    <h2 class="font-semibold">{{ membership.plan.name }}</h2>
                    <StatusBadge :status="status(membership)" />
                </div>
                <dl class="mt-5 space-y-2 text-sm">
                    <div>
                        <dt class="text-muted-foreground">Started</dt>
                        <dd>{{ dateTime(membership.starts_at) }}</dd>
                    </div>
                    <div>
                        <dt class="text-muted-foreground">Expires</dt>
                        <dd>{{ dateTime(membership.expires_at) }}</dd>
                    </div>
                </dl>
                <Link
                    v-if="status(membership) === 'active'"
                    href="/vip/dashboard"
                    class="mt-5 inline-block text-sm underline"
                    >VIP dashboard</Link
                >
                <p
                    v-if="status(membership) === 'expired'"
                    class="mt-4 text-sm text-muted-foreground"
                >
                    Renew to restore VIP access. Your order and membership
                    history remain available.
                </p>
            </section>
        </div>
        <div class="mt-10 grid gap-6 md:grid-cols-2">
            <section
                v-for="plan in plans"
                :key="plan.id"
                class="rounded-xl border p-6"
            >
                <h2 class="text-xl font-semibold">{{ plan.name }}</h2>
                <p class="mt-4 text-3xl font-semibold">
                    {{ money(plan.price_sen) }}
                    <span class="text-sm font-normal text-muted-foreground"
                        >/ {{ plan.duration_months }} months</span
                    >
                </p>
                <p
                    v-if="plan.product?.short_description"
                    class="mt-4 text-sm text-muted-foreground"
                >
                    {{ plan.product.short_description }}
                </p>
                <ul class="mt-5 list-disc space-y-2 pl-5 text-sm">
                    <li v-for="benefit in plan.benefits" :key="benefit">
                        {{ benefit }}
                    </li>
                </ul>
                <AddToCartForm
                    v-if="plan.product"
                    compact
                    :product-id="plan.product.id"
                    :available="1"
                />
                <p class="mt-4 text-xs leading-relaxed text-muted-foreground">
                    No shipping or physical inventory. Renewing before expiry
                    extends the existing end date; renewing after expiry starts
                    a new period.
                </p>
            </section>
            <p v-if="!plans.length" class="text-muted-foreground">
                No membership plan is available at the moment.
            </p>
        </div>
        <section v-if="history.data.length" class="mt-12">
            <h2 class="text-2xl font-semibold">Membership payment history</h2>
            <div class="mt-6 overflow-x-auto rounded-xl border">
                <table class="w-full text-left text-sm">
                    <thead class="bg-muted">
                        <tr>
                            <th class="p-4">Payment date</th>
                            <th class="p-4">Type</th>
                            <th class="p-4">Amount</th>
                            <th class="p-4">New expiry</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr
                            v-for="transaction in history.data"
                            :key="transaction.id"
                            class="border-t"
                        >
                            <td class="p-4 whitespace-nowrap">
                                {{ dateTime(transaction.paid_at) }}
                            </td>
                            <td class="p-4 capitalize">
                                {{ transaction.type }}
                            </td>
                            <td class="p-4 whitespace-nowrap">
                                {{ money(transaction.amount_sen) }}
                            </td>
                            <td class="p-4 whitespace-nowrap">
                                {{ dateTime(transaction.expires_at) }}
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
            <Pagination :links="history.links" />
        </section>
    </div>
</template>
