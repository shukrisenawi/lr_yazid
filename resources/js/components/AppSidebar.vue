<script setup lang="ts">
import { Link, usePage } from '@inertiajs/vue3';
import { House } from '@lucide/vue';
import { computed } from 'vue';
import AppLogo from '@/components/AppLogo.vue';
import NavFooter from '@/components/NavFooter.vue';
import NavGrouped from '@/components/NavGrouped.vue';
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
import {
    accountNavigation,
    adminNavigation,
    adminNavigationGroups,
} from '@/lib/sidebarNavigation';
import type { NavItem } from '@/types';

const page = usePage();
const isAdmin = computed(() => page.props.auth.user.role === 'admin');
const accountItems = computed(() => accountNavigation(page.props.auth.isVip));
const adminGroups = computed(() =>
    adminNavigationGroups(page.props.auth.isVip),
);
const footerNavItems: NavItem[] = [
    { title: 'Back to website', href: '/', icon: House },
];
</script>
<template>
    <Sidebar collapsible="icon" variant="sidebar" class="dashboard-sidebar">
        <SidebarHeader>
            <SidebarMenu>
                <SidebarMenuItem>
                    <SidebarMenuButton size="lg" as-child>
                        <Link
                            :href="isAdmin ? '/admin/dashboard' : '/dashboard'"
                            ><AppLogo
                        /></Link>
                    </SidebarMenuButton>
                </SidebarMenuItem>
            </SidebarMenu>
        </SidebarHeader>
        <SidebarContent>
            <template v-if="isAdmin">
                <NavMain :items="adminNavigation" label="Administration" />
                <NavGrouped :groups="adminGroups" />
            </template>
            <NavMain v-else :items="accountItems" label="My account" />
        </SidebarContent>
        <SidebarFooter>
            <NavFooter :items="footerNavItems" />
            <NavUser />
        </SidebarFooter>
    </Sidebar>
    <slot />
</template>
