import { afterEach, describe, expect, it, vi } from 'vite-plus/test';
import { createApp, h, type App, type VNode } from 'vue';
import AppSidebar from '@/components/AppSidebar.vue';
const state = vi.hoisted(() => ({ role: 'customer', isVip: false }));
vi.mock('@inertiajs/vue3', () => ({
    usePage: () => ({
        props: { auth: { user: { role: state.role }, isVip: state.isVip } },
    }),
    Link: {
        props: ['href'],
        setup:
            (
                props: { href: string },
                { slots }: { slots: { default: () => VNode[] } },
            ) =>
            () =>
                h('a', { href: props.href }, slots.default()),
    },
}));
vi.mock('@/components/AppLogo.vue', () => ({
    default: { render: () => null },
}));
vi.mock('@/components/NavUser.vue', () => ({
    default: { render: () => null },
}));
vi.mock('@/components/NavFooter.vue', () => ({
    default: { render: () => null },
}));
vi.mock('@/components/NavMain.vue', () => ({
    default: {
        props: ['items'],
        setup: (props: { items: { title: string; href: string }[] }) => () =>
            h(
                'nav',
                props.items.map((item) =>
                    h(
                        'a',
                        {
                            href:
                                typeof item.href === 'string' ? item.href : '',
                        },
                        item.title,
                    ),
                ),
            ),
    },
}));
vi.mock('@/components/ui/sidebar', () => {
    const container = {
        setup:
            (
                _props: unknown,
                { slots }: { slots: { default?: () => VNode[] } },
            ) =>
            () =>
                h('div', slots.default?.()),
    };
    return Object.fromEntries(
        [
            'Sidebar',
            'SidebarContent',
            'SidebarFooter',
            'SidebarHeader',
            'SidebarMenu',
            'SidebarMenuButton',
            'SidebarMenuItem',
        ].map((name) => [name, container]),
    );
});
let app: App | undefined;
afterEach(() => {
    app?.unmount();
    document.body.innerHTML = '';
});
function mount(role: string, isVip: boolean) {
    state.role = role;
    state.isVip = isVip;
    const root = document.createElement('div');
    document.body.append(root);
    app = createApp(AppSidebar);
    app.mount(root);
    return root;
}
describe('website administration navigation', () => {
    it('is visible to administrators without requiring a VIP membership', () => {
        const root = mount('admin', false);
        expect(
            root.querySelector('a[href="/admin/website-settings"]'),
        ).not.toBeNull();
        expect(root.querySelector('a[href="/admin/banners"]')).not.toBeNull();
    });
    it.each([false, true])(
        'is hidden from customers, including VIP customers (%s)',
        (isVip) => {
            const root = mount('customer', isVip);
            expect(
                root.querySelector('a[href="/admin/website-settings"]'),
            ).toBeNull();
            expect(root.querySelector('a[href="/admin/banners"]')).toBeNull();
            expect(
                !!root.querySelector('a[href="/vip/appointments/book"]'),
            ).toBe(isVip);
        },
    );
});
