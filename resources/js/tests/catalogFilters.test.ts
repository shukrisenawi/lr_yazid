import { afterEach, describe, expect, it, vi } from 'vite-plus/test';
import { createApp, h, reactive, type App } from 'vue';
import Products from '@/pages/public/Products.vue';
const state = vi.hoisted(() => ({
    get: vi.fn(),
    forms: [] as Record<string, unknown>[],
}));
vi.mock('@inertiajs/vue3', () => ({
    Head: { render: () => null },
    useForm: (initial: Record<string, unknown>) => {
        const form = reactive({
            ...initial,
            errors: {},
            processing: false,
            get: state.get,
        });
        state.forms.push(form);
        return form;
    },
}));
vi.mock('@/components/ProductCard.vue', () => ({
    default: { render: () => null },
}));
vi.mock('@/components/Pagination.vue', () => ({
    default: { render: () => null },
}));
let app: App | undefined;
afterEach(() => {
    app?.unmount();
    document.body.innerHTML = '';
    state.forms.length = 0;
    state.get.mockClear();
});
function mount(filters: object) {
    const root = document.createElement('div');
    document.body.append(root);
    app = createApp({
        render: () =>
            h(Products, {
                products: { data: [], total: 0, links: [] },
                categories: [],
                filters,
            }),
    });
    app.mount(root);
    return root;
}
describe('catalog filter initialization', () => {
    it('does not treat Array.prototype.sort as a filter when legacy empty props are an array', () => {
        const root = mount([]);
        expect(state.forms[0].sort).toBe('newest');
        expect(root.querySelectorAll('select')[1].value).toBe('newest');
        root.querySelector('form')!.dispatchEvent(
            new Event('submit', { bubbles: true, cancelable: true }),
        );
        expect(state.get).toHaveBeenCalledWith('/products', {
            preserveState: true,
        });
    });
    it('uses the default sort with explicit null filters', () => {
        mount({
            search: null,
            category: null,
            sort: null,
            min: null,
            max: null,
        });
        expect(state.forms[0]).toMatchObject({
            search: '',
            category: '',
            sort: 'newest',
            min: '',
            max: '',
        });
    });
    it('keeps an applied explicit sort and zero price boundary', () => {
        mount({ search: 'Bottle', sort: 'price_asc', min: 0, max: 10000 });
        expect(state.forms[0]).toMatchObject({
            search: 'Bottle',
            sort: 'price_asc',
            min: 0,
            max: 10000,
        });
    });
});
