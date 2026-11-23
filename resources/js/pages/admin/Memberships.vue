<script setup lang="ts">
import { Head, useForm } from '@inertiajs/vue3';
import { ref } from 'vue';
import AdminRecordAction from '@/components/AdminRecordAction.vue';
import Pagination from '@/components/Pagination.vue';
import StatusBadge from '@/components/StatusBadge.vue';
import { dateTime } from '@/types/commerce';
import type { Paginated } from '@/types/catalog';
import type { Membership } from '@/types/membership';
type Row = Membership & {
    user: { name: string; email: string; phone: string | null };
};
const props = defineProps<{
    memberships: Paginated<Row>;
    filters: { status?: string; search?: string };
}>();
const filter = useForm({
    status: props.filters.status ?? '',
    search: props.filters.search ?? '',
});
const selected = ref<Row | null>(null);
const adjustment = useForm({ expires_at: '', reason: '' });
function select(row: Row) {
    selected.value = row;
    adjustment.expires_at = new Intl.DateTimeFormat('sv-SE', {
        timeZone: 'Asia/Kuala_Lumpur',
        year: 'numeric',
        month: '2-digit',
        day: '2-digit',
        hour: '2-digit',
        minute: '2-digit',
        hour12: false,
    })
        .format(new Date(row.expires_at))
        .replace(' ', 'T');
    adjustment.reason = '';
    adjustment.clearErrors();
}
</script>
<template>
    <Head title="VIP Memberships" />
    <div class="p-6 md:p-10">
        <h1 class="text-3xl font-semibold">VIP memberships</h1>
        <form
            class="mt-6 flex flex-wrap items-end gap-3"
            @submit.prevent="filter.get('/admin/memberships')"
        >
            <label class="grid gap-2 text-sm"
                >Customer search<input
                    v-model="filter.search"
                    type="search"
                    class="rounded-md border bg-transparent p-2" /></label
            ><label class="grid gap-2 text-sm"
                >Membership status<select
                    v-model="filter.status"
                    class="rounded-md border bg-background p-2"
                >
                    <option value="">All memberships</option>
                    <option value="active">Active</option>
                    <option value="expired">Expired</option>
                    <option value="expiring">Expiring within 30 days</option>
                </select></label
            ><button class="rounded-md border px-4 py-2">Apply</button>
        </form>
        <div class="mt-6 overflow-x-auto rounded-xl border">
            <table class="w-full text-left text-sm">
                <thead class="bg-muted">
                    <tr>
                        <th class="p-4">Member</th>
                        <th class="p-4">Plan</th>
                        <th class="p-4">Status</th>
                        <th class="p-4">Expiry</th>
                        <th class="p-4">Action</th>
                    </tr>
                </thead>
                <tbody>
                    <tr
                        v-for="membership in memberships.data"
                        :key="membership.id"
                        class="border-t"
                    >
                        <td class="p-4">
                            <p class="font-medium">
                                {{ membership.user.name }}
                            </p>
                            <p class="mt-1 text-xs text-muted-foreground">
                                {{ membership.user.email }}
                            </p>
                            <p class="mt-1 text-xs text-muted-foreground">
                                {{ membership.user.phone }}
                            </p>
                        </td>
                        <td class="p-4">{{ membership.plan.name }}</td>
                        <td class="p-4">
                            <StatusBadge
                                :status="
                                    membership.status === 'revoked'
                                        ? 'revoked'
                                        : new Date(
                                                membership.expires_at,
                                            ).getTime() <= Date.now()
                                          ? 'expired'
                                          : membership.status
                                "
                            />
                        </td>
                        <td class="p-4 whitespace-nowrap">
                            {{ dateTime(membership.expires_at) }}
                        </td>
                        <td class="p-4">
                            <button
                                class="underline"
                                @click="select(membership)"
                            >
                                Adjust expiry
                            </button>
                            <AdminRecordAction
                                module="memberships"
                                :record-id="membership.id"
                                @success="selected = null"
                            />
                        </td>
                    </tr>
                    <tr v-if="!memberships.data.length">
                        <td
                            colspan="5"
                            class="p-12 text-center text-muted-foreground"
                        >
                            No memberships found.
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
        <Pagination :links="memberships.links" />
        <form
            v-if="selected"
            class="mt-8 max-w-xl space-y-5 rounded-xl border p-6"
            @submit.prevent="
                adjustment.put(`/admin/memberships/${selected.id}/expiry`, {
                    preserveScroll: true,
                    onSuccess: () => {
                        selected = null;
                    },
                })
            "
        >
            <h2 class="text-xl font-semibold">
                Adjust {{ selected.user.name }}'s membership
            </h2>
            <p role="alert" class="text-sm text-red-700">
                {{ Object.values(adjustment.errors).join(' ') }}
            </p>
            <label class="grid gap-2 text-sm"
                ><span
                    >New expiry (Malaysia time)
                    <span class="text-red-600" aria-hidden="true">*</span></span
                ><input
                    v-model="adjustment.expires_at"
                    type="datetime-local"
                    required
                    class="rounded-md border bg-transparent p-2.5" /></label
            ><label class="grid gap-2 text-sm"
                ><span
                    >Reason for adjustment
                    <span class="text-red-600" aria-hidden="true">*</span></span
                ><textarea
                    v-model="adjustment.reason"
                    required
                    minlength="10"
                    maxlength="1000"
                    rows="3"
                    class="rounded-md border bg-transparent p-3"
                />
            </label>
            <p class="text-xs text-muted-foreground">
                The old and new values, your administrator account and this
                reason will be permanently recorded.
            </p>
            <button
                :disabled="adjustment.processing"
                class="rounded-md bg-emerald-800 px-5 py-2.5 text-white disabled:opacity-50"
            >
                Save adjustment</button
            ><button
                type="button"
                class="ml-4 text-sm underline"
                @click="selected = null"
            >
                Cancel
            </button>
        </form>
    </div>
</template>
