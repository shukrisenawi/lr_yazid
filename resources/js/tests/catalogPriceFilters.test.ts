import { afterEach, describe, expect, it, vi } from 'vite-plus/test';
import { createApp, h, nextTick, type App } from 'vue';
import Products from '@/pages/public/Products.vue';

const { get } = vi.hoisted(() => ({ get: vi.fn() }));
vi.mock('@inertiajs/vue3', async () => {
    const { reactive, defineComponent } = await import('vue');
    return {
        Head: defineComponent({ render: () => null }),
        useForm: (data: Record<string, unknown>) =>
            reactive({
                ...data,
                errors: {},
                processing: false,
                get(this: Record<string, unknown>) {
                    get({ min: this.min, max: this.max });
                },
            }),
    };
});

let app: App;
afterEach(() => {
    app?.unmount();
    vi.clearAllMocks();
});

describe('catalog RM price filters', () => {
    it('shows existing sen filters as RM and submits decimal entries as sen', async () => {
        const root = document.createElement('div');
        app = createApp({
            render: () =>
                h(Products, {
                    products: { data: [], total: 0, links: [] },
                    categories: [],
                    filters: { min: '1050', max: 9900 },
                }),
        });
        app.mount(root);
        expect(root.textContent).toContain('Min (RM)');
        expect(root.textContent).toContain('Max (RM)');
        expect(root.textContent).not.toContain('(sen)');
        const inputs =
            root.querySelectorAll<HTMLInputElement>('input[type=number]');
        expect(inputs[0].value).toBe('10.50');
        expect(inputs[1].value).toBe('99.00');
        inputs[0].value = '0.29';
        inputs[0].dispatchEvent(new Event('change'));
        inputs[1].value = '';
        inputs[1].dispatchEvent(new Event('change'));
        await nextTick();
        root.querySelector('form')!.dispatchEvent(
            new Event('submit', { cancelable: true }),
        );
        expect(get).toHaveBeenCalledWith({ min: 29, max: null });
    });
});
