<script setup lang="ts">
import { Head, Link, useForm } from '@inertiajs/vue3';
import Pagination from '@/components/Pagination.vue';
import {
    commerceNoticeCopy as copy,
    channelState,
} from '@/lib/commerceNoticeCopy';
import { dateTime } from '@/types/commerce';
import type { CommerceDelivery } from '@/types/commerceNotice';
import type { Paginated } from '@/types/catalog';
const props = defineProps<{
    deliveries: Paginated<CommerceDelivery>;
    filters: {
        search?: string | null;
        kind?: string | null;
        delivery?: string | null;
    };
    kindLabels: Record<string, string>;
    reasonLabels: Record<string, string>;
}>();
const filter = useForm({
    search: props.filters.search ?? '',
    kind: props.filters.kind ?? '',
    delivery: props.filters.delivery ?? '',
});
const channels = ['database', 'mail'] as const;
</script>
<template>
    <Head :title="copy.title" />
    <div class="mx-auto w-full max-w-7xl min-w-0 p-6 md:p-10">
        <h1 class="text-3xl font-semibold">{{ copy.title }}</h1>
        <p class="mt-4 max-w-4xl text-sm leading-relaxed text-muted-foreground">
            {{ copy.help }}
        </p>
        <p class="mt-3 max-w-4xl text-sm leading-relaxed text-muted-foreground">
            {{ copy.transportHelp }}
        </p>
        <form
            class="mt-7 flex flex-wrap items-end gap-4"
            @submit.prevent="filter.get('/admin/commerce-notices')"
        >
            <label class="grid gap-2 text-sm"
                >{{ copy.search
                }}<input
                    v-model="filter.search"
                    type="search"
                    maxlength="100"
                    class="rounded-md border bg-transparent p-2.5"
            /></label>
            <label class="grid gap-2 text-sm"
                >{{ copy.kind
                }}<select
                    v-model="filter.kind"
                    class="rounded-md border bg-background p-2.5"
                >
                    <option value="">{{ copy.allKinds }}</option>
                    <option
                        v-for="(label, kind) in kindLabels"
                        :key="kind"
                        :value="kind"
                    >
                        {{ label }}
                    </option>
                </select></label
            >
            <label class="grid gap-2 text-sm"
                >{{ copy.delivery
                }}<select
                    v-model="filter.delivery"
                    class="rounded-md border bg-background p-2.5"
                >
                    <option value="">{{ copy.allStates }}</option>
                    <option value="waiting">{{ copy.waiting }}</option>
                    <option value="processed">{{ copy.processed }}</option>
                    <option value="suppressed">{{ copy.suppressed }}</option>
                </select></label
            >
            <button
                :disabled="filter.processing"
                class="rounded-md border px-4 py-2.5 text-sm disabled:opacity-50"
            >
                {{ copy.apply }}
            </button>
        </form>
        <p
            v-if="Object.keys($page.props.errors).length"
            role="alert"
            class="mt-4 text-sm text-red-700 dark:text-red-400"
        >
            {{ Object.values($page.props.errors).join(' ') }}
        </p>
        <p
            v-if="!deliveries.data.length"
            class="mt-10 text-sm text-muted-foreground"
        >
            {{ copy.empty }}
        </p>
        <div v-else class="mt-8 overflow-x-auto rounded-md border">
            <table class="w-full text-left text-sm">
                <caption class="sr-only">
                    {{
                        copy.title
                    }}
                </caption>
                <thead>
                    <tr class="border-b bg-muted/40">
                        <th class="p-3">{{ copy.notice }}</th>
                        <th class="p-3">{{ copy.customer }}</th>
                        <th class="p-3">{{ copy.order }}</th>
                        <th class="p-3">{{ copy.queued }}</th>
                        <th
                            v-for="channel in channels"
                            :key="channel"
                            class="p-3"
                        >
                            {{ copy[channel] }}
                        </th>
                    </tr>
                </thead>
                <tbody>
                    <tr
                        v-for="item in deliveries.data"
                        :key="item.id"
                        class="border-b align-top last:border-0"
                    >
                        <td class="min-w-36 p-3 font-medium">
                            {{ kindLabels[item.kind] }}
                        </td>
                        <td class="min-w-44 p-3 [overflow-wrap:anywhere]">
                            {{ item.user.name }}
                            <p class="mt-2 text-xs text-muted-foreground">
                                {{ item.user.email }}
                            </p>
                        </td>
                        <td class="min-w-44 p-3">
                            <Link
                                v-if="item.order"
                                :href="`/admin/orders/${item.order.id}`"
                                class="break-all underline"
                                >{{ item.order.reference }}</Link
                            ><span v-else>{{ copy.noOrder }}</span>
                            <p
                                v-if="item.payment"
                                class="mt-2 text-xs break-all text-muted-foreground"
                            >
                                {{ item.payment.reference }} ·
                                {{ item.payment.environment }}
                            </p>
                        </td>
                        <td class="min-w-36 p-3">
                            {{ dateTime(item.queued_at) }}
                        </td>
                        <td
                            v-for="channel in channels"
                            :key="channel"
                            class="min-w-40 p-3"
                        >
                            <p>
                                {{
                                    copy[
                                        channelState(
                                            item[`${channel}_sent_at`],
                                            item[`${channel}_suppressed_at`],
                                        )
                                    ]
                                }}
                            </p>
                            <p
                                v-if="
                                    item[`${channel}_sent_at`] ||
                                    item[`${channel}_suppressed_at`]
                                "
                                class="mt-2 text-xs text-muted-foreground"
                            >
                                {{
                                    dateTime(
                                        item[`${channel}_sent_at`] ||
                                            item[`${channel}_suppressed_at`] ||
                                            '',
                                    )
                                }}
                            </p>
                            <p
                                v-if="item[`${channel}_suppression_reason`]"
                                class="mt-2 text-xs text-muted-foreground"
                            >
                                {{
                                    reasonLabels[
                                        item[`${channel}_suppression_reason`] ||
                                            ''
                                    ]
                                }}
                            </p>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
        <Pagination :links="deliveries.links" />
    </div>
</template>
