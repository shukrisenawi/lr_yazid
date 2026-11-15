import { createInertiaApp } from '@inertiajs/vue3';
import { initializeTheme } from '@/composables/useAppearance';
import { defineAsyncComponent } from 'vue';
import PublicLayout from '@/layouts/PublicLayout.vue';
import { initializeFlashToast } from '@/lib/flashToast';
import { websiteTitle } from '@/lib/websiteTitle';

const AppLayout = defineAsyncComponent(() => import('@/layouts/AppLayout.vue'));
const AuthLayout = defineAsyncComponent(
    () => import('@/layouts/AuthLayout.vue'),
);
const SettingsLayout = defineAsyncComponent(
    () => import('@/layouts/settings/Layout.vue'),
);
const CustomerLayout = defineAsyncComponent(
    () => import('@/layouts/CustomerLayout.vue'),
);
const AdminLayout = defineAsyncComponent(
    () => import('@/layouts/AdminLayout.vue'),
);
const VIPLayout = defineAsyncComponent(() => import('@/layouts/VIPLayout.vue'));
const InvoiceLayout = defineAsyncComponent(
    () => import('@/layouts/InvoiceLayout.vue'),
);

const appName = import.meta.env.VITE_APP_NAME || 'Csiti.my';

void createInertiaApp({
    title: (title, page) => websiteTitle(title, page.props.website, appName),
    layout: (name) => {
        switch (true) {
            case name.startsWith('invoice/'):
                return InvoiceLayout;
            case name.startsWith('public/'):
                return PublicLayout;
            case name.startsWith('customer/'):
                return CustomerLayout;
            case name.startsWith('admin/'):
                return AdminLayout;
            case name.startsWith('vip/'):
                return VIPLayout;
            case name.startsWith('auth/'):
                return AuthLayout;
            case name.startsWith('settings/'):
                return [AppLayout, SettingsLayout];
            default:
                return AppLayout;
        }
    },
    withApp: (app) => {
        app.directive('focus', {
            mounted: (el: HTMLElement, shouldFocus) => {
                if (shouldFocus.value !== false) {
                    el.focus();
                }
            },
        });
    },
    progress: {
        color: '#4B5563',
    },
});

// This will set light / dark mode on page load...
initializeTheme();

// This will listen for flash toast data from the server...
initializeFlashToast();
