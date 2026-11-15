import { afterEach, describe, expect, it, vi } from 'vite-plus/test';
import { createApp, h, reactive, type App, type VNode } from 'vue';
import OrderShow from '@/pages/admin/orders/Show.vue';
import type { Order } from '@/types/commerce';
const state = vi.hoisted(() => ({ post: vi.fn(), put: vi.fn() }));
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
        reactive({
            ...initial,
            errors: {},
            processing: false,
            post: state.post,
            put: state.put,
        }),
}));
let app: App | undefined;
afterEach(() => {
    app?.unmount();
    document.body.innerHTML = '';
    vi.restoreAllMocks();
    state.post.mockClear();
});
const order: Order = {
    id: 3,
    reference: 'ORDER-CANCEL-QA',
    customer_name: 'QA',
    customer_email: 'qa@example.com',
    customer_phone: '+60123456789',
    shipping_address: null,
    subtotal_sen: 1000,
    shipping_fee_sen: 0,
    total_sen: 1000,
    payment_status: 'pending',
    fulfillment_status: null,
    notes: null,
    expires_at: '2026-11-15T00:00:00Z',
    created_at: '2026-11-15T00:00:00Z',
    cancellation_reason: null,
    items: [],
};
function mount(canCancel = true, value = order) {
    const root = document.createElement('div');
    document.body.append(root);
    app = createApp({
        render: () => h(OrderShow, { order: value, canCancel }),
    });
    app.mount(root);
    return root;
}
describe('administrator order cancellation', () => {
    it('requires a reason and warns that cancellation does not refund or void provider bills', () => {
        const root = mount();
        const reason = root.querySelector('textarea')!;
        expect(reason.required).toBe(true);
        expect(reason.minLength).toBe(5);
        expect(reason.maxLength).toBe(1000);
        expect(root.textContent).toContain('This does not issue a refund.');
        expect(root.textContent).toContain(
            'Existing provider bills are not voided',
        );
    });
    it('posts the cancellation only after explicit confirmation', () => {
        const root = mount();
        vi.spyOn(window, 'confirm').mockReturnValue(true);
        root.querySelector('textarea')!
            .closest('form')!
            .dispatchEvent(
                new Event('submit', { bubbles: true, cancelable: true }),
            );
        expect(state.post).toHaveBeenCalledWith('/admin/orders/3/cancel');
    });
    it('does not mutate an order if confirmation is dismissed', () => {
        const root = mount();
        vi.spyOn(window, 'confirm').mockReturnValue(false);
        root.querySelector('textarea')!
            .closest('form')!
            .dispatchEvent(
                new Event('submit', { bubbles: true, cancelable: true }),
            );
        expect(state.post).not.toHaveBeenCalled();
    });
    it('hides the action for ineligible orders and escapes recorded reasons', () => {
        const root = mount(false, {
            ...order,
            payment_status: 'cancelled',
            cancellation: {
                id: 1,
                actor_id: 7,
                source: 'admin',
                previous_payment_status: 'pending',
                previous_fulfillment_status: null,
                reason: '<script>Not executable</script>',
                created_at: order.created_at,
            },
        });
        expect(root.querySelector('textarea')).toBeNull();
        expect(root.textContent).toContain('Cancellation record');
        expect(root.textContent).toContain('Actor #7');
        expect(root.textContent).toContain('<script>Not executable</script>');
        expect(root.querySelector('script')).toBeNull();
    });
});
