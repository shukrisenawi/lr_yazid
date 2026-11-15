import { afterEach, describe, expect, it, vi } from 'vite-plus/test';
import { createApp, h, type App, type VNode } from 'vue';
import Show from '@/pages/invoice/Show.vue';
import type { Invoice } from '@/types/invoice';
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
}));
let app: App | undefined;
afterEach(() => {
    app?.unmount();
    document.body.innerHTML = '';
    vi.restoreAllMocks();
});
const invoice: Invoice = {
    id: 1,
    reference: 'INV-0000000001',
    issued_at: '2026-11-15T01:00:00Z',
    snapshot: {
        seller: {
            seller_name: 'QA issuer',
            seller_address: 'QA address',
            seller_email: 'issuer@example.com',
            registration_number: null,
            footer_note: null,
        },
        buyer: {
            customer_name: 'Original customer',
            customer_email: 'customer@example.com',
            customer_phone: '+60123456789',
            shipping_address: null,
        },
        order_reference: 'ORDER-TEST',
        payment_reference: 'PAY-TEST',
        paid_at: '2026-11-15T00:00:00Z',
        currency: 'MYR',
        subtotal_sen: 1000,
        shipping_fee_sen: 100,
        total_sen: 1100,
        items: [
            {
                product_name: '<script>unsafe</script>',
                variant_name: null,
                sku: 'QA',
                quantity: 1,
                unit_price_sen: 1000,
                line_total_sen: 1000,
            },
        ],
    },
};
function mount() {
    const root = document.createElement('div');
    document.body.append(root);
    app = createApp({
        render: () => h(Show, { invoice, backUrl: '/orders/1' }),
    });
    app.mount(root);
    return root;
}
describe('immutable invoice document', () => {
    it('renders saved seller, buyer, exact totals and compliance disclaimer', () => {
        const root = mount();
        expect(root.textContent).toContain('Original customer');
        expect(root.textContent).toContain('QA issuer');
        expect(root.textContent).toContain('11.00');
        expect(root.textContent).toContain('not a tax invoice');
    });
    it('escapes saved descriptions instead of rendering executable markup', () => {
        const root = mount();
        expect(root.querySelector('script')).toBeNull();
        expect(root.textContent).toContain('<script>unsafe</script>');
    });
    it('uses browser print without navigating or issuing another invoice', () => {
        const print = vi.spyOn(window, 'print').mockImplementation(() => {});
        const root = mount();
        root.querySelector('button')!.click();
        expect(print).toHaveBeenCalledTimes(1);
        expect(root.querySelector('a')?.getAttribute('href')).toBe('/orders/1');
    });
});
