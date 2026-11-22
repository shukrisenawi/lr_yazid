<script setup lang="ts">
import { computed } from 'vue';
import { useForm } from '@inertiajs/vue3';
import { manualRefundCopy as copy } from '@/lib/manualRefundCopy';
import { money } from '@/types/catalog';
import { dateTime, type Order, type Payment } from '@/types/commerce';
const props = defineProps<{ order: Order; refundKey?: string }>();
const refunds = computed(() => props.order.manual_refunds ?? []);
const total = computed(() =>
    refunds.value.reduce((sum, refund) => sum + refund.amount_sen, 0),
);
function remaining(payment: Payment) {
    return (
        payment.amount_sen -
        refunds.value
            .filter((refund) => refund.payment_id === payment.id)
            .reduce((sum, refund) => sum + refund.amount_sen, 0)
    );
}
const eligible = computed(() =>
    props.order.payment_status === 'paid' && props.order.currency === 'MYR'
        ? (props.order.payments ?? []).filter(
              (payment) =>
                  payment.status === 'paid' &&
                  payment.paid_at &&
                  payment.checked_at &&
                  payment.transaction_reference &&
                  payment.amount_sen === props.order.total_sen &&
                  ['sandbox', 'production'].includes(payment.environment) &&
                  remaining(payment) > 0,
          )
        : [],
);
const form = useForm({
    idempotency_key: props.refundKey ?? '',
    payment_id: '',
    amount: '',
    external_reference: '',
    method: 'bank_transfer',
    refunded_at: '',
    reason: '',
    confirmed: false,
});
function submit() {
    if (!window.confirm(copy.dialog)) return;
    form.post(`/admin/orders/${props.order.id}/manual-refunds`, {
        preserveScroll: true,
        onSuccess: () => {
            form.reset();
            form.idempotency_key = props.refundKey ?? '';
        },
    });
}
</script>
<template>
    <section
        class="mt-8 space-y-5 rounded-xl border p-6 print:hidden"
        aria-labelledby="refund-title"
    >
        <h2 id="refund-title" class="text-xl font-semibold">
            {{ copy.title }}
        </h2>
        <p class="text-sm leading-relaxed text-muted-foreground">
            {{ copy.help }}
        </p>
        <p class="text-sm leading-relaxed text-muted-foreground">
            {{ copy.review }} {{ copy.immutable }}
        </p>
        <p class="font-medium">{{ copy.total }}: {{ money(total) }}</p>
        <p v-if="!refunds.length" class="text-sm text-muted-foreground">
            {{ copy.empty }}
        </p>
        <ol v-else class="space-y-4">
            <li
                v-for="refund in refunds"
                :key="refund.id"
                class="rounded-md border p-4 text-sm [overflow-wrap:anywhere]"
            >
                <p class="font-medium">
                    {{ refund.reference }} · {{ money(refund.amount_sen) }}
                </p>
                <p class="mt-2">
                    {{ dateTime(refund.refunded_at) }} ·
                    {{ refund.environment }} · {{ refund.method }}
                </p>
                <p
                    v-if="refund.environment === 'sandbox'"
                    class="mt-2 font-medium"
                >
                    {{ copy.sandbox }}
                </p>
                <p class="mt-2">
                    {{ refund.external_reference }} · {{ copy.paymentId }} #{{
                        refund.payment_id
                    }}
                    · {{ copy.actor }} #{{ refund.recorded_by }}
                </p>
                <p v-if="refund.created_at" class="mt-2">
                    {{ copy.recordedAt }}: {{ dateTime(refund.created_at) }}
                </p>
                <p class="mt-2 whitespace-pre-line">{{ refund.reason }}</p>
            </li>
        </ol>
        <form
            v-if="eligible.length && refundKey"
            class="space-y-4 border-t pt-5"
            @submit.prevent="submit"
        >
            <label class="grid gap-2 text-sm"
                ><span
                    >{{ copy.payment }}
                    <span class="text-red-600" aria-hidden="true">*</span></span
                >
                <select
                    v-model="form.payment_id"
                    required
                    class="min-w-0 rounded-md border bg-background p-2.5"
                >
                    <option value="">{{ copy.choose }}</option>
                    <option
                        v-for="payment in eligible"
                        :key="payment.id"
                        :value="payment.id"
                    >
                        {{ payment.reference }} · {{ payment.environment }} ·
                        {{ copy.balance }} {{ money(remaining(payment)) }}
                    </option>
                </select>
            </label>
            <label class="grid gap-2 text-sm"
                ><span
                    >{{ copy.amount }}
                    <span class="text-red-600" aria-hidden="true">*</span></span
                ><input
                    v-model="form.amount"
                    type="text"
                    inputmode="decimal"
                    required
                    pattern="(?:0|[1-9][0-9]{0,6})(?:\.[0-9]{1,2})?"
                    maxlength="10"
                    placeholder="0.00"
                    class="rounded-md border bg-transparent p-2.5"
            /></label>
            <label class="grid gap-2 text-sm"
                ><span
                    >{{ copy.external }}
                    <span class="text-red-600" aria-hidden="true">*</span></span
                ><input
                    v-model="form.external_reference"
                    required
                    minlength="3"
                    maxlength="100"
                    pattern="[A-Za-z0-9][A-Za-z0-9._:\/\-]{2,99}"
                    class="rounded-md border bg-transparent p-2.5"
                /><span class="text-xs text-muted-foreground">{{
                    copy.externalHelp
                }}</span></label
            >
            <label class="grid gap-2 text-sm"
                ><span
                    >{{ copy.method }}
                    <span class="text-red-600" aria-hidden="true">*</span></span
                ><select
                    v-model="form.method"
                    class="rounded-md border bg-background p-2.5"
                >
                    <option
                        v-for="(label, key) in copy.methods"
                        :key="key"
                        :value="key"
                    >
                        {{ label }}
                    </option>
                </select></label
            >
            <label class="grid gap-2 text-sm"
                ><span
                    >{{ copy.date }}
                    <span class="text-red-600" aria-hidden="true">*</span></span
                ><input
                    v-model="form.refunded_at"
                    type="datetime-local"
                    required
                    class="min-w-0 rounded-md border bg-transparent p-2.5"
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
            <label class="flex items-start gap-3 text-sm leading-relaxed"
                ><input
                    v-model="form.confirmed"
                    type="checkbox"
                    required
                    class="mt-1"
                /><span
                    >{{ copy.confirm }}
                    <span class="text-red-600" aria-hidden="true">*</span></span
                ></label
            >
            <p
                v-if="Object.keys(form.errors).length"
                role="alert"
                class="text-sm text-red-700 dark:text-red-400"
            >
                {{ Object.values(form.errors).join(' ') }}
            </p>
            <button
                :disabled="form.processing"
                class="rounded-md border px-5 py-3 text-sm disabled:opacity-50"
            >
                {{ copy.submit }}
            </button>
        </form>
        <p v-else class="text-sm text-muted-foreground">
            {{ copy.noEligible }}
        </p>
    </section>
</template>
