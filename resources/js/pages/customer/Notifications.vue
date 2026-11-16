<script setup lang="ts">
import { Head, Link, useForm, usePage } from '@inertiajs/vue3';
import Pagination from '@/components/Pagination.vue';
import type { Paginated } from '@/types/catalog';
import { dateTime } from '@/types/commerce';
defineProps<{
    notifications: Paginated<{
        id: string;
        data: { title: string; message: string; url: string };
        read_at: string | null;
        created_at: string;
    }>;
}>();
const form = useForm({});
const page = usePage();
</script>
<template>
    <Head title="Notifications" />
    <div class="mx-auto w-full max-w-4xl p-6 md:p-10">
        <div class="flex flex-wrap justify-between gap-4">
            <h1 class="text-3xl font-semibold">Notifications</h1>
            <button
                v-if="page.props.unreadNotificationCount > 0"
                :disabled="form.processing"
                class="text-sm underline"
                @click="form.post('/notifications/read-all')"
            >
                Mark all as read
            </button>
        </div>
        <div class="mt-8 space-y-4">
            <article
                v-for="notification in notifications.data"
                :key="notification.id"
                class="rounded-xl border p-5"
            >
                <p class="text-xs text-muted-foreground">
                    {{ dateTime(notification.created_at) }} ·
                    {{ notification.read_at ? 'Read' : 'Unread' }}
                </p>
                <h2 class="mt-3 font-semibold">
                    {{ notification.data.title }}
                </h2>
                <p class="mt-2 text-sm text-muted-foreground">
                    {{ notification.data.message }}
                </p>
                <div class="mt-4 flex gap-5 text-sm">
                    <Link :href="notification.data.url" class="underline"
                        >View details</Link
                    ><button
                        v-if="!notification.read_at"
                        :disabled="form.processing"
                        class="underline"
                        @click="
                            form.post(
                                `/notifications/${notification.id}/read`,
                                { preserveScroll: true },
                            )
                        "
                    >
                        Mark as read
                    </button>
                </div>
            </article>
            <p
                v-if="!notifications.data.length"
                class="rounded-xl border border-dashed p-10 text-center text-muted-foreground"
            >
                You have no notifications yet.
            </p>
        </div>
        <Pagination :links="notifications.links" />
    </div>
</template>
