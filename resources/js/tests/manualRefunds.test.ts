import { afterEach, describe, expect, it, vi } from 'vite-plus/test';
import { createApp, h, reactive, type App } from 'vue';
import ManualRefundLedger from '@/components/ManualRefundLedger.vue';
import type { Order, Payment } from '@/types/commerce';
const state = vi.hoisted(() => ({ post: vi.fn() }));
vi.mock('@inertiajs/vue3', () => ({
    useForm: (initial: Record<string, unknown>) =>
        reactive({
            ...initial,
            errors: {},
            processing: false,
            post: state.post,
            reset: vi.fn(),
        }),
}));
let app: App | undefined;
afterEach(() => {
    app?.unmount();
    document.body.innerHTML = '';
    state.post.mockClear();
    vi.restoreAllMocks();
});
const payment: Payment = {
    id: 7,
    order_id: 3,
    reference: 'QA-VERIFIED',
    environment: 'sandbox',
    amount_sen: 10000,
    status: 'paid',
    bill_code: 'test-only',
    transaction_reference: 'TEST-TX',
    requires_review: true,
    review_reason: 'late_payment',
    paid_at: '2026-11-14T00:00:00Z',
    checked_at: '2026-11-14T00:00:00Z',
};
const order: Order = {
    id: 3,
    reference: 'QA-ORDER',
    currency: 'MYR',
    customer_name: 'QA',
    customer_email: 'qa@example.com',
    customer_phone: '+60123456789',
    shipping_address: null,
    subtotal_sen: 10000,
    shipping_fee_sen: 0,
    total_sen: 10000,
    payment_status: 'paid',
    fulfillment_status: null,
    notes: null,
    cancellation_reason: null,
    items: [],
    payments: [payment],
    expires_at: '2026-11-14T00:00:00Z',
    created_at: '2026-11-14T00:00:00Z',
};
function mount(value = order) {
    const root = document.createElement('div');
    document.body.append(root);
    app = createApp({
        render: () =>
            h(ManualRefundLedger, { order: value, refundKey: 'qa-key' }),
    });
    app.mount(root);
    return root;
}
describe('manual refund ledger', () => {
    it('requires external completion acknowledgement and explains operational separation', () => {
        const root = mount();
        expect(
            root
                .querySelector('input[type="checkbox"]')
                ?.hasAttribute('required'),
        ).toBe(true);
        expect(root.textContent).toContain('does not transfer money');
        expect(root.textContent).toContain('does not clear their review flag');
        expect(root.querySelector('option[value="7"]')?.textContent).toContain(
            '100.00',
        );
    });
    it('keeps amount as decimal text and applies a valid external-reference pattern', () => {
        const root = mount();
        const amount = root.querySelector<HTMLInputElement>(
            'input[inputmode="decimal"]',
        )!;
        expect(amount.type).toBe('text');
        const external = root.querySelector<HTMLInputElement>(
            'input[minlength="3"]',
        )!;
        external.value = 'BANK-QA/123';
        expect(external.checkValidity()).toBe(true);
        external.value = '<script>bad</script>';
        expect(external.checkValidity()).toBe(false);
    });
    it('posts only after the explicit confirmation dialog', () => {
        const root = mount();
        vi.spyOn(window, 'confirm').mockReturnValue(true);
        root.querySelector('form')!.dispatchEvent(
            new Event('submit', { bubbles: true, cancelable: true }),
        );
        expect(state.post).toHaveBeenCalledWith(
            '/admin/orders/3/manual-refunds',
            expect.objectContaining({ preserveScroll: true }),
        );
    });
    it('does not submit when the dialog is dismissed', () => {
        const root = mount();
        vi.spyOn(window, 'confirm').mockReturnValue(false);
        root.querySelector('form')!.dispatchEvent(
            new Event('submit', { bubbles: true, cancelable: true }),
        );
        expect(state.post).not.toHaveBeenCalled();
    });
    it('hides the form for unverified or non-MYR payments', () => {
        const root = mount({ ...order, currency: 'USD' });
        expect(root.querySelector('form')).toBeNull();
        expect(root.textContent).toContain('No verified paid MYR payment');
    });
    it('shows immutable sandbox audit metadata and removes fully recorded payment options', () => {
        const root = mount({
            ...order,
            manual_refunds: [
                {
                    id: 1,
                    order_id: 3,
                    payment_id: 7,
                    recorded_by: 4,
                    reference: 'RFD-QA',
                    environment: 'sandbox',
                    external_reference: 'BANK-QA',
                    method: 'bank_transfer',
                    amount_sen: 10000,
                    currency: 'MYR',
                    refunded_at: '2026-11-15T00:00:00Z',
                    reason: '<script>Audit only</script>',
                    created_at: '2026-11-15T01:00:00Z',
                },
            ],
        });
        expect(root.querySelector('form')).toBeNull();
        expect(root.textContent).toContain(
            'Sandbox record — not a live refund',
        );
        expect(root.textContent).toContain('Recorded by #4');
        expect(root.textContent).toContain('<script>Audit only</script>');
        expect(root.querySelector('script')).toBeNull();
        expect(root.querySelector('button')).toBeNull();
    });
});
