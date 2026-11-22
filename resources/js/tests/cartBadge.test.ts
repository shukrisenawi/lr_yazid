import { afterEach, describe, expect, it, vi } from 'vite-plus/test';
import { createApp, nextTick, reactive, type App } from 'vue';
import CartBadge from '@/components/CartBadge.vue';

const page = reactive({ props: { cartCount: 0 } });
vi.mock('@inertiajs/vue3', () => ({ usePage: () => page }));

let app: App | undefined;
afterEach(() => {
    app?.unmount();
    document.body.innerHTML = '';
    page.props.cartCount = 0;
});

function mount() {
    const root = document.createElement('div');
    document.body.append(root);
    app = createApp(CartBadge);
    app.mount(root);
    return root;
}

describe('shopping cart badge', () => {
    it('hides the badge for an empty cart', () => {
        expect(mount().querySelector('span')).toBeNull();
    });

    it('reactively displays the count and hides when the cart is emptied', async () => {
        page.props.cartCount = 2;
        const root = mount();
        expect(root.textContent?.trim()).toBe('2');
        expect(root.querySelector('span')?.getAttribute('aria-label')).toBe(
            '2 items in cart',
        );
        page.props.cartCount = 105;
        await nextTick();
        expect(root.textContent?.trim()).toBe('99+');
        expect(root.querySelector('span')?.getAttribute('aria-label')).toBe(
            '105 items in cart',
        );
        page.props.cartCount = 0;
        await nextTick();
        expect(root.querySelector('span')).toBeNull();
    });
});
