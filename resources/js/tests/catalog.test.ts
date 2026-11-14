import { afterEach, describe, expect, it, vi } from 'vite-plus/test';
import { createApp, h, nextTick, ref, type App } from 'vue';
import VariantAttributes from '@/components/VariantAttributes.vue';
import ProductCard from '@/components/ProductCard.vue';
import Pagination from '@/components/Pagination.vue';
import { money, type Product } from '@/types/catalog';

vi.mock('@inertiajs/vue3', async () => {
    const { defineComponent, h, reactive } = await import('vue');
    return {
        useForm: (data: Record<string, unknown>) =>
            reactive({
                ...data,
                errors: {},
                processing: false,
                post: vi.fn(),
                clearErrors: vi.fn(),
            }),
        router: { visit: vi.fn() },
        Link: defineComponent({
            props: ['href'],
            setup:
                (props, { slots }) =>
                () =>
                    h('a', { href: props.href }, slots.default?.()),
        }),
    };
});
let app: App | undefined;
afterEach(() => {
    app?.unmount();
    document.body.innerHTML = '';
});
function mount(render: () => ReturnType<typeof h>) {
    const root = document.createElement('div');
    document.body.append(root);
    app = createApp({ render });
    app.mount(root);
    return root;
}

describe('catalog components', () => {
    it('formats prices from integer sen', () => {
        expect(money(8900)).toContain('89.00');
        expect(money(1)).toContain('0.01');
    });
    it('adds, edits and removes structured attributes', async () => {
        const attributes = ref<{ name: string; value: string }[]>([]);
        const root = mount(() =>
            h(VariantAttributes, {
                modelValue: attributes.value,
                'onUpdate:modelValue': (value) => {
                    attributes.value = value;
                },
            }),
        );
        root.querySelector('button')!.click();
        await nextTick();
        const inputs = root.querySelectorAll('input');
        inputs[0].value = 'Color';
        inputs[0].dispatchEvent(new Event('input', { bubbles: true }));
        await nextTick();
        inputs[1].value = 'Black';
        inputs[1].dispatchEvent(new Event('input', { bubbles: true }));
        await nextTick();
        expect(attributes.value).toEqual([{ name: 'Color', value: 'Black' }]);
        root.querySelector('button')!.click();
        await nextTick();
        expect(attributes.value).toHaveLength(0);
    });
    it('renders product details, sale price and out-of-stock state', () => {
        const product: Product = {
            id: 1,
            sku: 'BOTTLE',
            category_id: null,
            type: 'physical',
            short_description: '',
            description: '',
            sale_price_sen: null,
            sale_starts_at: null,
            sale_ends_at: null,
            weight_grams: 100,
            status: 'active',
            is_featured: false,
            seo_title: '',
            meta_description: '',
            name: 'Bottle',
            slug: 'bottle',
            images: [],
            price_sen: 8900,
            current_price_sen: 7900,
            stock: 0,
        };
        const root = mount(() => h(ProductCard, { product }));
        expect(root.querySelector('a')?.getAttribute('href')).toBe(
            '/products/bottle',
        );
        expect(root.textContent).toContain('79.00');
        expect(root.querySelector('del')?.textContent).toContain('89.00');
        expect(root.textContent).toContain('Out of stock');
    });
    it('renders disabled pagination without unsafe HTML', () => {
        const links = [
            { url: null, label: '&laquo; Previous', active: false },
            { url: '/products?page=1', label: '1', active: true },
            { url: '/products?page=2', label: '2', active: false },
            { url: '/products?page=2', label: 'Next &raquo;', active: false },
        ];
        const root = mount(() => h(Pagination, { links }));
        expect(root.querySelectorAll('a')).toHaveLength(3);
        expect(root.querySelector('[aria-current="page"]')?.textContent).toBe(
            '1',
        );
        expect(root.textContent).toContain('‹ Previous');
    });
});
