import { afterEach, describe, expect, it, vi } from 'vite-plus/test';
import { createApp, defineComponent, h, type App } from 'vue';
import Dashboard from '@/pages/customer/Dashboard.vue';

const state = vi.hoisted(() => ({ isVip: false }));
vi.mock('@inertiajs/vue3', () => ({
    Head: defineComponent({ setup: () => () => null }),
    Link: defineComponent({
        props: ['href'],
        setup:
            (props, { slots }) =>
            () =>
                h('a', { href: props.href }, slots.default?.()),
    }),
    usePage: () => ({
        props: {
            auth: { user: { name: 'Test Customer' }, isVip: state.isVip },
            copy: {
                welcome: 'Welcome to your account',
                account_description: 'Manage your account.',
                profile: 'Profile',
                security: 'Security',
            },
        },
    }),
}));

let app: App | undefined;
afterEach(() => {
    app?.unmount();
    document.body.innerHTML = '';
    state.isVip = false;
});

function mount() {
    const root = document.createElement('div');
    document.body.append(root);
    app = createApp(Dashboard);
    app.mount(root);
    return root;
}

describe('customer dashboard', () => {
    it('keeps account and commerce destinations accessible', () => {
        const root = mount();
        expect(root.textContent).toContain('Test Customer');
        for (const path of [
            '/orders',
            '/my-programs',
            '/appointments',
            '/cart',
            '/addresses',
            '/settings/profile',
            '/settings/security',
            '/products',
        ]) {
            expect(root.querySelector(`a[href="${path}"]`)).not.toBeNull();
        }
        expect(root.textContent).not.toContain('undefined');
    });

    it('offers membership plans without claiming VIP access', () => {
        const root = mount();
        expect(root.querySelector('a[href="/membership"]')).not.toBeNull();
        expect(root.querySelector('a[href="/vip/dashboard"]')).toBeNull();
    });

    it('links active VIP members to their VIP dashboard', () => {
        state.isVip = true;
        const root = mount();
        expect(root.textContent).toContain('VIP member');
        expect(root.querySelector('a[href="/vip/dashboard"]')).not.toBeNull();
    });
});
