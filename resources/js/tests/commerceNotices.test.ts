import { afterEach, describe, expect, it, vi } from 'vite-plus/test';
import { createApp, h, reactive, type App, type VNode } from 'vue';
import CommerceNotices from '@/pages/admin/CommerceNotices.vue';
import { channelState } from '@/lib/commerceNoticeCopy';
import type { CommerceDelivery } from '@/types/commerceNotice';
const state = vi.hoisted(() => ({ get: vi.fn() }));
vi.mock('@inertiajs/vue3', () => ({
    Head: { render: () => null },
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
    useForm: (initial: Record<string, unknown>) =>
        reactive({ ...initial, processing: false, get: state.get }),
}));
vi.mock('@/components/Pagination.vue', () => ({
    default: { render: () => null },
}));
let app: App | undefined;
afterEach(() => {
    app?.unmount();
    document.body.innerHTML = '';
    state.get.mockClear();
});
const delivery: CommerceDelivery = {
    id: 1,
    kind: 'order_placed',
    queued_at: '2026-11-15T00:00:00Z',
    database_sent_at: '2026-11-15T00:01:00Z',
    mail_sent_at: null,
    database_suppressed_at: null,
    mail_suppressed_at: '2026-11-15T00:02:00Z',
    database_suppression_reason: null,
    mail_suppression_reason: 'email_changed',
    user: {
        id: 1,
        name: '<script>Customer</script>',
        email: 'customer@example.com',
    },
    order: { id: 2, reference: 'ORDER-QA' },
    payment: null,
};
function mount(data: CommerceDelivery[] = [delivery]) {
    const root = document.createElement('div');
    document.body.append(root);
    app = createApp({
        render: () =>
            h(CommerceNotices, {
                deliveries: { data, total: data.length, links: [] },
                filters: {},
                kindLabels: { order_placed: 'Order placed' },
                reasonLabels: { email_changed: 'Recipient email changed' },
            }),
    });
    Object.assign(app.config.globalProperties, {
        $page: { props: { errors: {} } },
    });
    app.mount(root);
    return root;
}
describe('commerce notice tracking', () => {
    it('distinguishes processed, suppressed and awaiting channel states', () => {
        expect(channelState('sent', null)).toBe('done');
        expect(channelState(null, 'suppressed')).toBe('skipped');
        expect(channelState(null, null)).toBe('pending');
        expect(channelState('sent', 'suppressed')).toBe('done');
    });
    it('renders audit metadata with escaped customer names and safe order links', () => {
        const root = mount();
        expect(root.textContent).toContain('Recipient email changed');
        expect(root.textContent).toContain('does not prove external delivery');
        expect(root.querySelector('script')).toBeNull();
        expect(root.textContent).toContain('<script>Customer</script>');
        expect(root.querySelector('a')?.getAttribute('href')).toBe(
            '/admin/orders/2',
        );
    });
    it('provides a real empty state without resend or financial mutation controls', () => {
        const root = mount([]);
        expect(root.textContent).toContain(
            'No commerce notices match these filters.',
        );
        expect(root.querySelector('table')).toBeNull();
        expect(root.querySelectorAll('button')).toHaveLength(1);
    });
    it('submits filters to the read-only directory endpoint', () => {
        const root = mount();
        root.querySelector('form')!.dispatchEvent(
            new Event('submit', { bubbles: true, cancelable: true }),
        );
        expect(state.get).toHaveBeenCalledWith('/admin/commerce-notices');
    });
});
