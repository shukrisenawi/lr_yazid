<script setup lang="ts">
import { Link, usePage } from '@inertiajs/vue3';
import {
    Bell,
    CalendarDays,
    ChartNoAxesCombined,
    CreditCard,
    House,
    LayoutGrid,
    MapPin,
    Package,
    Settings,
    ShieldCheck,
    ShoppingBag,
    ShoppingCart,
    Users,
} from '@lucide/vue';
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
import { contentCopy } from '@/lib/contentCopy';
import { websiteCopy } from '@/lib/websiteCopy';
import { reportCopy } from '@/lib/reportCopy';
import { paymentSettingsCopy } from '@/lib/paymentSettingsCopy';
import { commerceNoticeCopy } from '@/lib/commerceNoticeCopy';
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
    { title: 'My Appointments', href: '/appointments', icon: LayoutGrid },
    ...(page.props.auth.isVip
        ? [
              {
                  title: 'Book Appointment',
                  href: '/vip/appointments/book',
                  icon: ShieldCheck,
              },
          ]
        : []),
    ...(page.props.auth.isVip
        ? [{ title: 'VIP Programs', href: '/vip/programs', icon: ShieldCheck }]
        : []),
    { title: 'Notifications', href: '/notifications', icon: LayoutGrid },
    { title: 'Shopping cart', href: '/cart', icon: House },
    { title: 'My addresses', href: '/addresses', icon: House },
    ...(page.props.auth.user.role === 'admin'
        ? [
              {
                  title: commerceNoticeCopy.title,
                  href: '/admin/commerce-notices',
                  icon: LayoutGrid,
              },
              {
                  title: reportCopy.title,
                  href: '/admin/reports',
                  icon: LayoutGrid,
              },
              {
                  title: websiteCopy.settings,
                  href: '/admin/website-settings',
                  icon: LayoutGrid,
              },
              {
                  title: websiteCopy.banners,
                  href: '/admin/banners',
                  icon: LayoutGrid,
              },
              {
                  title: contentCopy.title,
                  href: '/admin/content-pages',
                  icon: LayoutGrid,
              },
              {
                  title: 'Manage Orders',
                  href: '/admin/orders',
                  icon: LayoutGrid,
              },
              {
                  title: 'Customers',
                  href: '/admin/customers',
                  icon: LayoutGrid,
              },
              {
                  title: 'Appointment Requests',
                  href: '/admin/appointments',
                  icon: LayoutGrid,
              },
              {
                  title: 'Appointment Calendar',
                  href: '/admin/appointments/calendar',
                  icon: LayoutGrid,
              },
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
                  title: paymentSettingsCopy.title,
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

const navigationGroups = computed(() => {
    const items = mainNavItems.value.map((item) => ({
        ...item,
        icon: item.href.toString().includes('appointment')
            ? CalendarDays
            : item.href.toString().includes('payment')
              ? CreditCard
              : item.href.toString().includes('report')
                ? ChartNoAxesCombined
                : item.href.toString().includes('customer')
                  ? Users
                  : item.href.toString().includes('order')
                    ? ShoppingBag
                    : item.href.toString().includes('product')
                      ? Package
                      : item.href.toString().includes('notification')
                        ? Bell
                        : item.href.toString().includes('cart')
                          ? ShoppingCart
                          : item.href.toString().includes('address')
                            ? MapPin
                            : item.href.toString().includes('settings')
                              ? Settings
                              : item.icon,
    }));
    const admin = items.filter((item) =>
        item.href.toString().startsWith('/admin'),
    );
    const account = items.filter(
        (item) => !item.href.toString().startsWith('/admin'),
    );
    return page.props.auth.user.role === 'admin'
        ? [
              {
                  label: 'Administration',
                  items: [
                      ...admin.filter(
                          (item) => item.href === '/admin/dashboard',
                      ),
                      ...admin.filter(
                          (item) => item.href !== '/admin/dashboard',
                      ),
                  ],
              },
              { label: 'My account', items: account },
          ]
        : [{ label: 'My account', items: account }];
});
</script>

<template>
    <Sidebar collapsible="icon" variant="sidebar" class="dashboard-sidebar">
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
            <NavMain
                v-for="group in navigationGroups"
                :key="group.label"
                :items="group.items"
                :label="group.label"
            />
        </SidebarContent>

        <SidebarFooter>
            <NavFooter :items="footerNavItems" />
            <NavUser />
        </SidebarFooter>
    </Sidebar>
    <slot />
</template>
