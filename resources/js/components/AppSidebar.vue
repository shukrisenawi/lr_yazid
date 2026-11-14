<script setup lang="ts">
import { Link, usePage } from '@inertiajs/vue3';
import { House, LayoutGrid, ShieldCheck } from '@lucide/vue';
import { computed } from 'vue';
import AppLogo from '@/components/AppLogo.vue';
import NavFooter from '@/components/NavFooter.vue';
import NavMain from '@/components/NavMain.vue';
import NavUser from '@/components/NavUser.vue';
import {
    Sidebar,
    SidebarContent,
    SidebarFooter,
    SidebarHeader,
    SidebarMenu,
    SidebarMenuButton,
    SidebarMenuItem,
} from '@/components/ui/sidebar';
import { dashboard } from '@/routes';
import type { NavItem } from '@/types';

const page = usePage();
const mainNavItems = computed<NavItem[]>(() => [
    {
        title: 'Dashboard',
        href: dashboard(),
        icon: LayoutGrid,
    },
    { title: 'My orders', href: '/orders', icon: LayoutGrid },
    { title: 'My membership', href: '/membership', icon: ShieldCheck },
    { title: 'My Programs', href: '/my-programs', icon: LayoutGrid },
    ...(page.props.auth.isVip
        ? [{ title: 'VIP Programs', href: '/vip/programs', icon: ShieldCheck }]
        : []),
    { title: 'Notifications', href: '/notifications', icon: LayoutGrid },
    { title: 'Shopping cart', href: '/cart', icon: House },
    { title: 'My addresses', href: '/addresses', icon: House },
    ...(page.props.auth.user.role === 'admin'
        ? [
              {
                  title: 'Appointment services',
                  href: '/admin/appointment-services',
                  icon: LayoutGrid,
              },
              {
                  title: 'Manage VIP Programs',
                  href: '/admin/programs',
                  icon: LayoutGrid,
              },
              { title: 'Payments', href: '/admin/payments', icon: LayoutGrid },
              {
                  title: 'VIP memberships',
                  href: '/admin/memberships',
                  icon: ShieldCheck,
              },
              {
                  title: 'Membership plans',
                  href: '/admin/membership-plans',
                  icon: ShieldCheck,
              },
              {
                  title: 'Payment settings',
                  href: '/admin/payment-settings',
                  icon: ShieldCheck,
              },
              {
                  title: 'Administration',
                  href: '/admin/dashboard',
                  icon: ShieldCheck,
              },
              { title: 'Products', href: '/admin/products', icon: LayoutGrid },
              {
                  title: 'Shipping settings',
                  href: '/admin/shipping',
                  icon: LayoutGrid,
              },
              {
                  title: 'Categories',
                  href: '/admin/categories',
                  icon: LayoutGrid,
              },
          ]
        : []),
]);

const footerNavItems: NavItem[] = [
    {
        title: 'Back to website',
        href: '/',
        icon: House,
    },
];
</script>

<template>
    <Sidebar collapsible="icon" variant="inset">
        <SidebarHeader>
            <SidebarMenu>
                <SidebarMenuItem>
                    <SidebarMenuButton size="lg" as-child>
                        <Link :href="dashboard()">
                            <AppLogo />
                        </Link>
                    </SidebarMenuButton>
                </SidebarMenuItem>
            </SidebarMenu>
        </SidebarHeader>

        <SidebarContent>
            <NavMain :items="mainNavItems" />
        </SidebarContent>

        <SidebarFooter>
            <NavFooter :items="footerNavItems" />
            <NavUser />
        </SidebarFooter>
    </Sidebar>
    <slot />
</template>
