import { afterEach, describe, expect, it, vi } from 'vite-plus/test';
import { createApp, h, nextTick, reactive, type App, type VNode } from 'vue';
import PaymentSettings from '@/pages/admin/PaymentSettings.vue';
const state = vi.hoisted(() => ({
    form: null as Record<string, unknown> | null,
    submitted: null as Record<string, unknown> | null,
    url: '',
    success: null as (() => void) | null,
}));
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
    useForm: (initial: Record<string, unknown>) => {
        const form = reactive<Record<string, unknown>>({
            ...initial,
            errors: {},
            processing: false,
        });
        Object.assign(form, {
            put: (url: string, options: { onSuccess: () => void }) => {
                state.url = url;
                state.submitted = Object.fromEntries(
                    Object.keys(initial).map((key) => [key, form[key]]),
                );
                state.success = options.onSuccess;
            },
            reset: (...keys: string[]) =>
                keys.forEach((key) => {
                    form[key] = initial[key];
                }),
        });
        state.form = form;
        return form;
    },
}));
let app: App | undefined;
afterEach(() => {
    app?.unmount();
    document.body.innerHTML = '';
    state.submitted = null;
    state.success = null;
});
function mount() {
    const props = reactive({
        settings: {
            enabled: false,
            environment: 'sandbox',
            sandbox_category_code: 'QACATEGORY',
            production_category_code: null,
            version: 7,
        },
        sandboxConfigured: true,
        productionConfigured: false,
        sandboxKeyAvailable: true,
        productionKeyAvailable: false,
        storedInDatabase: true,
        callbackReady: false,
        callbackUrl: 'http://localhost/payments/toyyibpay/callback',
        returnUrl: 'http://localhost/payments/return',
    });
    const root = document.createElement('div');
    document.body.append(root);
    app = createApp({ render: () => h(PaymentSettings, props) });
    app.mount(root);
    return { root, props };
}
describe('ToyyibPay configuration form', () => {
    it('shows configuration status without prefilling stored secret keys', () => {
        const { root } = mount();
        const fields = root.querySelectorAll<HTMLInputElement>(
            'input[type=password]',
        );
        expect(fields).toHaveLength(2);
        expect([...fields].every((field) => field.value === '')).toBe(true);
        expect(root.textContent).toContain('Credentials configured');
        expect(root.textContent).toContain('encrypted and never sent back');
        expect(root.querySelector('a')?.getAttribute('href')).toBe(
            '/admin/website-settings',
        );
    });
    it('submits both environment credentials and version, then clears secret inputs after success', async () => {
        const { root, props } = mount();
        const input = root.querySelector<HTMLInputElement>(
            'input[type=password]',
        )!;
        input.value = 'local-test-replacement-key';
        input.dispatchEvent(new Event('input', { bubbles: true }));
        root.querySelector('form')!.dispatchEvent(
            new Event('submit', { bubbles: true, cancelable: true }),
        );
        expect(state.url).toBe('/admin/payment-settings');
        expect(state.submitted?.sandbox_secret_key).toBe(
            'local-test-replacement-key',
        );
        expect(state.submitted?.version).toBe(7);
        expect(state.submitted?.enabled).toBe(false);
        props.settings.version = 8;
        await nextTick();
        state.success?.();
        await nextTick();
        expect(input.value).toBe('');
        expect(state.form?.version).toBe(8);
    });
    it('warns that production takes real payments without implying credentials are provider-tested', async () => {
        const { root } = mount();
        const select = root.querySelector('select')!;
        select.value = 'production';
        select.dispatchEvent(new Event('change', { bubbles: true }));
        await nextTick();
        expect(root.querySelector('[role=note]')?.textContent).toContain(
            'real payments',
        );
        expect(root.textContent).toContain(
            'not that ToyyibPay has verified them',
        );
    });
    it('shows callback deployment requirements and removal controls only for existing keys', () => {
        const { root } = mount();
        expect(root.textContent).toContain(
            'public HTTPS address is not configured',
        );
        expect(root.textContent).toContain(
            'http://localhost/payments/toyyibpay/callback',
        );
        expect(root.textContent).toContain('Remove saved Sandbox Secret Key');
        expect(root.textContent).not.toContain(
            'Remove saved Production Secret Key',
        );
        expect(root.textContent).toContain(
            'Existing attempts retain their encrypted credential snapshot',
        );
    });
});
