import { afterEach, describe, expect, it, vi } from 'vite-plus/test';
import { createApp, h, nextTick, type App } from 'vue';
import AddToCartForm from '@/components/AddToCartForm.vue';
import OrderSummary from '@/components/OrderSummary.vue';
import StatusBadge from '@/components/StatusBadge.vue';

const calls = vi.hoisted(() => ({ post: vi.fn(), visit: vi.fn() }));
vi.mock('@inertiajs/vue3', async () => {
    const { reactive } = await import('vue');
    return {
        router: { visit: calls.visit },
        useForm: (data: Record<string, unknown>) =>
            reactive({
                ...data,
                errors: {},
                processing: false,
                clearErrors: vi.fn(),
                post: (url: string, options: { onSuccess: () => void }) => {
                    calls.post(url, { ...data });
                    options.onSuccess();
                },
            }),
    };
});
let app: App | undefined;
afterEach(() => {
    app?.unmount();
    document.body.innerHTML = '';
    vi.clearAllMocks();
});
function mount(render: () => ReturnType<typeof h>) {
    const root = document.createElement('div');
    document.body.append(root);
    app = createApp({ render });
    app.mount(root);
    return root;
}
describe('shopping components', () => {
    it('prevents adding out-of-stock products', () => {
        const root = mount(() =>
            h(AddToCartForm, { productId: 1, available: 0 }),
        );
        expect(root.querySelector('button')?.disabled).toBe(true);
        expect(root.textContent).toContain('Out of stock');
    });
    it('requires a selected variant', () => {
        const root = mount(() =>
            h(AddToCartForm, {
                productId: 1,
                available: 3,
                requiresVariant: true,
            }),
        );
        expect(root.querySelector('button')?.disabled).toBe(true);
    });
    it('posts only product identifiers and quantity and shows success', async () => {
        const root = mount(() =>
            h(AddToCartForm, { productId: 2, variantId: 3, available: 5 }),
        );
        root.querySelector('form')!.dispatchEvent(
            new Event('submit', { bubbles: true, cancelable: true }),
        );
        await nextTick();
        expect(calls.post).toHaveBeenCalledWith('/cart/items', {
            product_id: 2,
            product_variant_id: 3,
            quantity: 1,
        });
        expect(root.querySelector('[role="status"]')?.textContent).toContain(
            'Added to your cart',
        );
    });
    it('buy now navigates only after adding the item successfully', async () => {
        const root = mount(() =>
            h(AddToCartForm, { productId: 2, available: 5 }),
        );
        root.querySelectorAll('button')[1].click();
        await nextTick();
        expect(calls.post).toHaveBeenCalled();
        expect(calls.visit).toHaveBeenCalledWith('/checkout');
    });
    it('renders server-supplied totals and free shipping', () => {
        const root = mount(() =>
            h(OrderSummary, { subtotal: 8900, shipping: 0, total: 8900 }),
        );
        expect(root.textContent).toContain('Free');
        expect(root.textContent).toContain('89.00');
    });
    it('renders readable status labels', () => {
        const root = mount(() =>
            h(StatusBadge, { status: 'pending_approval' }),
        );
        expect(root.textContent).toBe('pending approval');
    });
});
