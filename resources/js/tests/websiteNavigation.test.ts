import { afterEach, describe, expect, it, vi } from 'vite-plus/test';
import { createApp, h, nextTick, type App, type VNode } from 'vue';
import AppSidebar from '@/components/AppSidebar.vue';
const state = vi.hoisted(() => ({
    role: 'customer',
    isVip: false,
    url: '/admin/dashboard',
}));
vi.mock('@inertiajs/vue3', () => ({
    usePage: () => ({
        url: state.url,
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
vi.mock('@/components/ui/sidebar', async () => {
    const { ref } = await import('vue');
    const container = {
        setup:
            (
                _props: unknown,
                { slots }: { slots: { default?: () => VNode[] } },
            ) =>
            () =>
                h('div', slots.default?.()),
    };
    return {
        ...Object.fromEntries(
            [
                'Sidebar',
                'SidebarContent',
                'SidebarFooter',
                'SidebarHeader',
                'SidebarMenu',
                'SidebarMenuButton',
                'SidebarMenuItem',
                'SidebarGroup',
                'SidebarMenuSub',
                'SidebarMenuSubItem',
                'SidebarMenuSubButton',
            ].map((name) => [name, container]),
        ),
        useSidebar: () => ({
            state: ref('expanded'),
            isMobile: ref(false),
            setOpen: vi.fn(),
            setOpenMobile: vi.fn(),
        }),
    };
});
let app: App | undefined;
afterEach(() => {
    app?.unmount();
    document.body.innerHTML = '';
    state.url = '/admin/dashboard';
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
        expect(root.querySelector('a[href="/admin/reports"]')).not.toBeNull();
        expect(
            root.querySelector('a[href="/admin/commerce-notices"]'),
        ).not.toBeNull();
        expect(
            root.querySelector('a[href="/admin/payment-settings"]')
                ?.textContent,
        ).toContain('ToyyibPay settings');
    });
    it('opens one menu group at a time and allows it to collapse', async () => {
        const root = mount('admin', false);
        const commerce = root.querySelector<HTMLElement>(
            '[aria-controls="sidebar-group-commerce"]',
        )!;
        const website = root.querySelector<HTMLElement>(
            '[aria-controls="sidebar-group-website"]',
        )!;
        expect(commerce.getAttribute('aria-expanded')).toBe('false');
        commerce.click();
        await nextTick();
        expect(commerce.getAttribute('aria-expanded')).toBe('true');
        website.click();
        await nextTick();
        expect(commerce.getAttribute('aria-expanded')).toBe('false');
        expect(website.getAttribute('aria-expanded')).toBe('true');
        website.click();
        await nextTick();
        expect(website.getAttribute('aria-expanded')).toBe('false');
    });
    it('automatically opens the active group on nested routes', () => {
        state.url = '/admin/appointments/calendar?month=2026-11';
        const root = mount('admin', false);
        expect(
            root
                .querySelector('[aria-controls="sidebar-group-appointments"]')
                ?.getAttribute('aria-expanded'),
        ).toBe('true');
        expect(
            root
                .querySelector('a[href="/admin/appointments/calendar"]')
                ?.getAttribute('aria-current'),
        ).toBe('page');
        expect(
            root
                .querySelector('a[href="/admin/appointments"]')
                ?.hasAttribute('aria-current'),
        ).toBe(false);
    });
    it.each([false, true])(
        'is hidden from customers, including VIP customers (%s)',
        (isVip) => {
            const root = mount('customer', isVip);
            expect(
                root.querySelector('a[href="/admin/website-settings"]'),
            ).toBeNull();
            expect(root.querySelector('a[href="/admin/banners"]')).toBeNull();
            expect(root.querySelector('a[href="/admin/reports"]')).toBeNull();
            expect(
                root.querySelector('a[href="/admin/commerce-notices"]'),
            ).toBeNull();
            expect(
                !!root.querySelector('a[href="/vip/appointments/book"]'),
            ).toBe(isVip);
        },
    );
});
