import { afterEach, describe, expect, it, vi } from 'vite-plus/test';
import { createApp, h, nextTick, reactive, type App, type VNode } from 'vue';
import NotificationBell from '@/components/NotificationBell.vue';

const state = vi.hoisted(() => ({ poll: vi.fn() }));
const page = reactive({
    component: 'customer/Dashboard',
    props: { unreadNotificationCount: 0 },
});

vi.mock('@inertiajs/vue3', () => ({
    usePage: () => page,
    usePoll: state.poll,
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

let app: App | undefined;
afterEach(() => {
    app?.unmount();
    document.body.innerHTML = '';
    state.poll.mockClear();
    page.props.unreadNotificationCount = 0;
    page.component = 'customer/Dashboard';
});

function mount() {
    const root = document.createElement('div');
    document.body.append(root);
    app = createApp(NotificationBell);
    app.mount(root);
    return root;
}

describe('navbar notification bell', () => {
    it('links to notifications without a badge when everything is read', () => {
        const root = mount();
        expect(root.querySelector('a')?.getAttribute('href')).toBe(
            '/notifications',
        );
        expect(root.querySelector('a')?.getAttribute('aria-label')).toBe(
            'Notifications',
        );
        expect(root.querySelector('span')).toBeNull();
    });

    it('updates its badge and accessible unread count reactively', async () => {
        page.props.unreadNotificationCount = 105;
        const root = mount();
        expect(root.textContent).toContain('99+');
        expect(root.querySelector('a')?.getAttribute('aria-label')).toBe(
            'Notifications (105 unread)',
        );
        page.props.unreadNotificationCount = 1;
        await nextTick();
        expect(root.textContent?.trim()).toBe('1');
        page.props.unreadNotificationCount = 0;
        await nextTick();
        expect(root.querySelector('span')).toBeNull();
    });

    it('polls only the badge, including the list when viewing notifications', () => {
        mount();
        expect(state.poll).toHaveBeenCalledWith(30000, expect.any(Function));
        const options = state.poll.mock.calls[0][1];
        expect(options()).toEqual({ only: ['unreadNotificationCount'] });
        page.component = 'customer/Notifications';
        expect(options()).toEqual({
            only: ['unreadNotificationCount', 'notifications'],
        });
    });
});
