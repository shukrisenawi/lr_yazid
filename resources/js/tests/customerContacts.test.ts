import { afterEach, describe, expect, it, vi } from 'vite-plus/test';
import { createApp, h, nextTick, reactive, type App } from 'vue';
import CustomerContactEditor from '@/components/CustomerContactEditor.vue';
import type { CustomerContactChange } from '@/types/customerContact';
const state = vi.hoisted(() => ({
    put: vi.fn(),
    forms: [] as Record<string, unknown>[],
}));
vi.mock('@inertiajs/vue3', () => ({
    useForm: (initial: Record<string, unknown>) => {
        const form = reactive({
            ...initial,
            errors: {},
            processing: false,
            put: state.put,
        });
        state.forms.push(form);
        return form;
    },
}));
vi.mock('@/components/Pagination.vue', () => ({
    default: { render: () => null },
}));
let app: App | undefined;
afterEach(() => {
    app?.unmount();
    document.body.innerHTML = '';
    state.forms.length = 0;
    state.put.mockClear();
    vi.restoreAllMocks();
});
function mount(history: CustomerContactChange[] = []) {
    const root = document.createElement('div');
    document.body.append(root);
    const customer = reactive({
        id: 3,
        name: 'Original Customer',
        phone: '+60123456789',
        profile_version: 1,
    });
    app = createApp({
        render: () =>
            h(CustomerContactEditor, {
                customer,
                history: { data: history, total: history.length, links: [] },
            }),
    });
    app.mount(root);
    return { root, customer };
}
describe('administrator customer contact corrections', () => {
    it('limits the form to name, phone and required audit reason', () => {
        const { root } = mount();
        expect(root.querySelectorAll('input')).toHaveLength(2);
        expect(root.querySelector('input[type="email"]')).toBeNull();
        expect(root.querySelector('input[type="password"]')).toBeNull();
        expect(root.querySelector('select')).toBeNull();
        expect(root.querySelector('textarea')?.minLength).toBe(5);
        expect(root.querySelector('textarea')?.required).toBe(true);
        expect(root.textContent).toContain(
            'Existing orders, invoices, payment records and booking snapshots remain unchanged',
        );
    });
    it('sends a correction only after explicit confirmation', () => {
        const { root } = mount();
        vi.spyOn(window, 'confirm').mockReturnValue(true);
        root.querySelector('form')!.dispatchEvent(
            new Event('submit', { bubbles: true, cancelable: true }),
        );
        expect(state.put).toHaveBeenCalledWith(
            '/admin/customers/3',
            expect.objectContaining({ preserveScroll: true }),
        );
    });
    it('does not submit when confirmation is dismissed', () => {
        const { root } = mount();
        vi.spyOn(window, 'confirm').mockReturnValue(false);
        root.querySelector('form')!.dispatchEvent(
            new Event('submit', { bubbles: true, cancelable: true }),
        );
        expect(state.put).not.toHaveBeenCalled();
    });
    it('does not advance a stale form version merely because new props arrive', async () => {
        const { root, customer } = mount();
        customer.profile_version = 2;
        customer.name = 'Changed elsewhere';
        state.forms[0].errors = {
            profile_version: 'Reload the latest details.',
        };
        await nextTick();
        expect(state.forms[0].profile_version).toBe(1);
        expect(root.querySelector('button')?.disabled).toBe(true);
        expect(root.querySelector('a')?.getAttribute('href')).toBe(
            '/admin/customers/3',
        );
        expect(root.querySelector('[role="alert"]')?.textContent).toContain(
            'Reload the latest details.',
        );
    });
    it('synchronizes normalized server values and version only after successful save', async () => {
        const { root, customer } = mount();
        vi.spyOn(window, 'confirm').mockReturnValue(true);
        root.querySelector('form')!.dispatchEvent(
            new Event('submit', { bubbles: true, cancelable: true }),
        );
        customer.profile_version = 2;
        customer.name = 'Saved Name';
        customer.phone = '+60198765432';
        state.forms[0].reason = 'Audit reason';
        const options = state.put.mock.calls[0][1] as { onSuccess: () => void };
        options.onSuccess();
        await nextTick();
        expect(state.forms[0].profile_version).toBe(2);
        expect(state.forms[0].name).toBe('Saved Name');
        expect(state.forms[0].phone).toBe('+60198765432');
        expect(state.forms[0].reason).toBe('');
    });
    it('renders escaped old/new contact snapshots without mutation controls in the audit', () => {
        const { root } = mount([
            {
                id: 1,
                admin_id: 7,
                profile_version: 2,
                previous_values: { name: 'Before', phone: null },
                new_values: {
                    name: '<script>After</script>',
                    phone: '+60198765432',
                },
                reason: '<script>Admin audit only</script>',
                created_at: '2026-11-15T00:00:00Z',
            },
        ]);
        expect(root.textContent).toContain('Administrator #7');
        expect(root.textContent).toContain('<script>After</script>');
        expect(root.textContent).toContain('<script>Admin audit only</script>');
        expect(root.querySelector('script')).toBeNull();
        expect(
            root.querySelector(
                '[aria-labelledby="contact-history-title"] button',
            ),
        ).toBeNull();
    });
});
