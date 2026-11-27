import { afterEach, describe, expect, it, vi } from 'vite-plus/test';
import { createApp, h, type App, type VNode } from 'vue';
import Cart from '@/pages/public/Cart.vue';
import type { CartLine, CartSummary } from '@/types/commerce';

vi.mock('@inertiajs/vue3', () => ({
    Head: () => null,
    Link: (_: unknown, { slots }: { slots: { default: () => VNode[] } }) =>
        h('a', slots.default()),
    useForm: () => ({ processing: false }),
}));
vi.mock('@/components/CartLine.vue', () => ({
    default: {
        props: ['line'],
        setup: (props: { line: CartLine }) => () => h('div', props.line.name),
    },
}));

let app: App | undefined;
afterEach(() => {
    app?.unmount();
    document.body.innerHTML = '';
});

function mount(types: CartLine['type'][], shippingEnabled = true) {
    const cart: CartSummary = {
        items: types.map((type, index) => ({
            id: index + 1,
            type,
            name: `${type} item`,
        })) as CartLine[],
        subtotal_sen: 1000,
        shipping_fee_sen: types.includes('physical') ? 500 : 0,
        total_sen: types.includes('physical') ? 1500 : 1000,
        valid: true,
        shipping_enabled: shippingEnabled,
        requires_shipping: types.includes('physical'),
    };
    const root = document.createElement('div');
    document.body.append(root);
    app = createApp(Cart, { cart });
    app.mount(root);
    return root;
}

describe('cart item groups', () => {
    it('separates physical products from non-products', () => {
        const root = mount(['membership', 'physical']);
        expect(root.querySelector('[aria-label="Product"]')?.textContent)
            .toContain('physical item');
        expect(root.querySelector('[aria-label="Product"]')?.textContent)
            .not.toContain('membership item');
        expect(root.querySelector('[aria-label="Non-product"]')?.textContent)
            .toContain('membership item');
        expect(root.querySelector('[aria-label="Non-product"]')?.textContent)
            .toContain('No shipping required');
    });

    it('hides empty groups and shipping warnings for non-products', () => {
        const root = mount(['membership'], false);
        expect(root.querySelector('[aria-label="Product"]')).toBeNull();
        expect(root.querySelector('[role="alert"]')).toBeNull();
        expect(root.textContent).toContain('Proceed to checkout');
    });

    it('still warns physical-product buyers when shipping is disabled', () => {
        const root = mount(['physical'], false);
        expect(root.querySelector('[aria-label="Non-product"]')).toBeNull();
        expect(root.querySelector('[role="alert"]')?.textContent)
            .toContain('Shipping is temporarily unavailable.');
    });
});
