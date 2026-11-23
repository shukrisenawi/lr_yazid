<script setup lang="ts">
import { Head, Link, useForm } from '@inertiajs/vue3';
import { LogIn } from '@lucide/vue';
import {
    Tooltip,
    TooltipContent,
    TooltipProvider,
    TooltipTrigger,
} from '@/components/ui/tooltip';
import Pagination from '@/components/Pagination.vue';
import AdminRecordAction from '@/components/AdminRecordAction.vue';
import AdminEditButton from '@/components/AdminEditButton.vue';
import { customerCopy as copy } from '@/lib/customerCopy';
import { dateTime } from '@/types/commerce';
import type { Paginated } from '@/types/catalog';
const props = defineProps<{
    customers: Paginated<{
        id: number;
        name: string;
        email: string;
        phone: string | null;
        created_at: string;
        orders_count: number;
        is_vip: boolean;
    }>;
    filters: { search?: string; membership?: string };
}>();
const filter = useForm({
    search: props.filters.search ?? '',
    membership: props.filters.membership ?? '',
});
const login = useForm({});
</script>
<template>
    <Head :title="copy.title" />
    <div class="p-6 md:p-10">
        <h1 class="text-3xl font-semibold">{{ copy.title }}</h1>
        <form
            class="mt-8 flex flex-wrap items-end gap-4"
            @submit.prevent="filter.get('/admin/customers')"
        >
            <label class="grid gap-2 text-sm"
                >{{ copy.search
                }}<input
                    v-model="filter.search"
                    type="search"
                    maxlength="100"
                    class="rounded-md border bg-transparent p-2.5" /></label
            ><label class="grid gap-2 text-sm"
                >{{ copy.membership
                }}<select
                    v-model="filter.membership"
                    class="rounded-md border bg-background p-2.5"
                >
                    <option value="">{{ copy.all }}</option>
                    <option
                        v-for="key in [
                            'normal',
                            'active',
                            'expired',
                            'expiring',
                        ] as const"
                        :key="key"
                        :value="key"
                    >
                        {{ copy[key] }}
                    </option>
                </select></label
            ><button
                :disabled="filter.processing"
                class="rounded-md border px-5 py-2.5 text-sm"
            >
                {{ copy.apply }}
            </button>
        </form>
        <div class="mt-6 overflow-x-auto rounded-xl border">
            <table class="w-full text-left text-sm">
                <thead class="bg-muted">
                    <tr>
                        <th class="p-4">{{ copy.name }}</th>
                        <th class="p-4">{{ copy.phone }}</th>
                        <th class="p-4">{{ copy.membership }}</th>
                        <th class="p-4">{{ copy.orders }}</th>
                        <th class="p-4">{{ copy.joined }}</th>
                        <th class="p-4">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <tr
                        v-for="customer in customers.data"
                        :key="customer.id"
                        class="border-t"
                    >
                        <td class="p-4">
                            <Link
                                :href="`/admin/customers/${customer.id}`"
                                class="font-medium underline"
                                >{{ customer.name }}</Link
                            >
                            <p class="mt-2 text-xs text-muted-foreground">
                                {{ customer.email }}
                            </p>
                        </td>
                        <td class="p-4">{{ customer.phone }}</td>
                        <td class="p-4">
                            {{ customer.is_vip ? copy.vip : copy.nonVip }}
                        </td>
                        <td class="p-4">{{ customer.orders_count }}</td>
                        <td class="p-4 whitespace-nowrap">
                            {{ dateTime(customer.created_at) }}
                        </td>
                        <td class="p-4">
                            <div class="flex items-center gap-2">
                                <TooltipProvider :delay-duration="200">
                                    <Tooltip>
                                        <TooltipTrigger as-child>
                                            <button
                                                type="button"
                                                :disabled="login.processing"
                                                :aria-label="`Log in as ${customer.name}`"
                                                class="inline-flex size-10 items-center justify-center rounded-md border hover:bg-muted focus-visible:outline-2 focus-visible:outline-offset-2 disabled:opacity-50"
                                                @click="
                                                    login.post(
                                                        `/admin/customers/${customer.id}/login`,
                                                    )
                                                "
                                            >
                                                <LogIn
                                                    :size="18"
                                                    aria-hidden="true"
                                                />
                                            </button>
                                        </TooltipTrigger>
                                        <TooltipContent
                                            >Log in as
                                            {{ customer.name }}</TooltipContent
                                        >
                                    </Tooltip>
                                </TooltipProvider>
                                <AdminEditButton
                                    :href="`/admin/customers/${customer.id}`"
                                    :label="`Edit ${customer.name}`"
                                />
                                <AdminRecordAction
                                    module="customers"
                                    :record-id="customer.id"
                                    :record-name="customer.name"
                                />
                            </div>
                        </td>
                    </tr>
                    <tr v-if="!customers.data.length">
                        <td
                            colspan="6"
                            class="p-10 text-center text-muted-foreground"
                        >
                            {{ copy.empty }}
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
        <Pagination :links="customers.links" />
    </div>
</template>
