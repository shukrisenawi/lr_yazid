<script setup lang="ts">
import { Head, Link, usePage } from '@inertiajs/vue3';
import {
    ArrowRight,
    CalendarDays,
    MapPin,
    Package,
    ShieldCheck,
    ShoppingBag,
    ShoppingCart,
    UserRound,
} from '@lucide/vue';
import { computed } from 'vue';

const page = usePage();
const copy = computed(() => page.props.copy as Record<string, string>);
const shortcuts = [
    {
        title: 'My orders',
        description: 'Track purchases and view your invoices.',
        href: '/orders',
        icon: ShoppingBag,
    },
    {
        title: 'My Programs',
        description: 'Find your registrations and program details.',
        href: '/my-programs',
        icon: Package,
    },
    {
        title: 'My Appointments',
        description: 'Review upcoming bookings and requests.',
        href: '/appointments',
        icon: CalendarDays,
    },
];
</script>
<template>
    <Head title="My Account" />
    <div class="dashboard-page">
        <div class="dashboard-page-heading">
            <h1>MY ACCOUNT</h1>
            <span class="text-xs text-muted-foreground"
                >Csiti.my / Dashboard</span
            >
        </div>
        <div class="grid gap-6 xl:grid-cols-[2fr_1fr]">
            <section class="dashboard-panel dashboard-welcome">
                <div class="flex items-start justify-between gap-4">
                    <div>
                        <p class="text-sm text-muted-foreground">
                            Welcome back, {{ page.props.auth.user.name }}
                        </p>
                        <h2 class="mt-3 text-2xl font-semibold text-primary">
                            {{ copy.welcome }}
                        </h2>
                        <p
                            class="mt-3 max-w-xl text-sm leading-6 text-muted-foreground"
                        >
                            {{ copy.account_description }}
                        </p>
                    </div>
                    <span class="dashboard-tile-icon hidden sm:flex"
                        ><UserRound class="size-7"
                    /></span>
                </div>
                <div class="mt-6 flex flex-wrap gap-3">
                    <Link href="/products" class="dashboard-action"
                        >Explore products <ArrowRight class="size-4"
                    /></Link>
                    <Link
                        href="/orders"
                        class="inline-flex items-center px-3 py-2 text-sm font-medium text-primary"
                        >View my orders</Link
                    >
                </div>
            </section>
            <section class="dashboard-panel flex flex-col justify-between">
                <div class="flex items-center justify-between gap-4">
                    <span
                        class="text-xs font-medium text-muted-foreground uppercase"
                        >Membership</span
                    >
                    <span class="dashboard-tile-icon"
                        ><ShieldCheck class="size-6"
                    /></span>
                </div>
                <h2 class="mt-4 text-2xl font-semibold">
                    {{
                        page.props.auth.isVip ? 'VIP member' : 'Your membership'
                    }}
                </h2>
                <p class="mt-2 text-sm text-muted-foreground">
                    {{
                        page.props.auth.isVip
                            ? 'Your VIP space is ready to explore.'
                            : 'Explore membership plans and benefits.'
                    }}
                </p>
                <Link
                    :href="
                        page.props.auth.isVip ? '/vip/dashboard' : '/membership'
                    "
                    class="mt-5 inline-flex items-center gap-2 text-sm font-medium text-primary"
                    >View membership <ArrowRight class="size-4"
                /></Link>
            </section>
        </div>
        <div class="mt-6 grid gap-6 md:grid-cols-3">
            <Link
                v-for="item in shortcuts"
                :key="item.href"
                :href="item.href"
                class="dashboard-panel group"
            >
                <div class="flex items-center justify-between">
                    <span class="dashboard-tile-icon"
                        ><component :is="item.icon" class="size-6"
                    /></span>
                    <ArrowRight
                        class="size-4 text-muted-foreground group-hover:text-primary"
                    />
                </div>
                <h2 class="mt-5 font-semibold">{{ item.title }}</h2>
                <p class="mt-2 text-sm leading-6 text-muted-foreground">
                    {{ item.description }}
                </p>
            </Link>
        </div>
        <div class="mt-6 grid gap-6 xl:grid-cols-[2fr_1fr]">
            <section class="dashboard-panel">
                <h2 class="border-b pb-4 font-semibold">Account settings</h2>
                <Link href="/settings/profile" class="dashboard-quick-link">
                    <span class="dashboard-tile-icon"
                        ><UserRound class="size-5"
                    /></span>
                    <span class="flex-1"
                        ><span class="block font-medium">{{
                            copy.profile
                        }}</span
                        ><span class="mt-1 block text-sm text-muted-foreground"
                            >Update your name, email and personal
                            information.</span
                        ></span
                    >
                    <ArrowRight class="size-4 shrink-0 text-muted-foreground" />
                </Link>
                <Link href="/settings/security" class="dashboard-quick-link">
                    <span class="dashboard-tile-icon"
                        ><ShieldCheck class="size-5"
                    /></span>
                    <span class="flex-1"
                        ><span class="block font-medium">{{
                            copy.security
                        }}</span
                        ><span class="mt-1 block text-sm text-muted-foreground"
                            >Manage your password and two-factor
                            authentication.</span
                        ></span
                    >
                    <ArrowRight class="size-4 shrink-0 text-muted-foreground" />
                </Link>
            </section>
            <section class="dashboard-panel">
                <h2 class="border-b pb-4 font-semibold">Quick access</h2>
                <Link href="/cart" class="dashboard-quick-link"
                    ><ShoppingCart class="size-5 text-primary" /><span
                        class="flex-1"
                        >Shopping cart</span
                    ><ArrowRight class="size-4"
                /></Link>
                <Link href="/addresses" class="dashboard-quick-link"
                    ><MapPin class="size-5 text-primary" /><span class="flex-1"
                        >My addresses</span
                    ><ArrowRight class="size-4"
                /></Link>
                <p class="mt-5 text-xs leading-6 text-muted-foreground">
                    Keep your account details up to date for a smoother checkout
                    and booking experience.
                </p>
            </section>
        </div>
    </div>
</template>
