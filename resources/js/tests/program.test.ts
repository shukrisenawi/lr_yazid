import { afterEach, describe, expect, it, vi } from 'vite-plus/test';
import { createApp, h, reactive, nextTick, type App, type VNode } from 'vue';
import Edit from '@/pages/admin/programs/Edit.vue';
import Show from '@/pages/vip/programs/Show.vue';
import {
    localDateTime,
    registrationOpen,
    type VipProgram,
} from '@/types/program';

const calls = vi.hoisted(() => ({
    post: vi.fn(),
    forms: [] as Record<string, unknown>[],
}));
vi.mock('@/components/RichTextEditor.vue', () => ({
    default: { render: () => null },
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
    useForm: (data: Record<string, unknown> = {}) => {
        const form = reactive({
            ...data,
            errors: {},
            processing: false,
            post: calls.post,
        });
        calls.forms.push(form);
        return form;
    },
}));
let app: App | undefined;
afterEach(() => {
    app?.unmount();
    document.body.innerHTML = '';
    vi.useRealTimers();
    vi.clearAllMocks();
    calls.forms.length = 0;
});
const program: VipProgram = {
    id: 1,
    title: 'Member Workshop',
    slug: 'member-workshop',
    short_description: 'A VIP workshop.',
    description: '<h2>Welcome</h2>',
    starts_at: '2026-11-25T02:00:00Z',
    ends_at: '2026-11-25T04:00:00Z',
    registration_opens_at: '2026-11-01T00:00:00Z',
    registration_closes_at: '2026-11-24T00:00:00Z',
    location: 'Kuala Lumpur',
    maps_url: null,
    capacity: 1,
    registered_count: 0,
    registration_enabled: true,
    status: 'published',
    images: [],
};
function mount(overrides: Partial<VipProgram> = {}, eligible = true) {
    vi.useFakeTimers();
    vi.setSystemTime(new Date('2026-11-15T00:00:00Z'));
    const root = document.createElement('div');
    document.body.append(root);
    app = createApp({
        render: () =>
            h(Show, {
                program: { ...program, ...overrides },
                registration: null,
                eligible,
            }),
    });
    app.mount(root);
    return root;
}
describe('VIP program registration', () => {
    it('switches from POST to PUT after create redirects to the same edit component', async () => {
        const state = reactive<{ program: VipProgram | null }>({
            program: null,
        });
        const root = document.createElement('div');
        document.body.append(root);
        app = createApp({ render: () => h(Edit, { program: state.program }) });
        app.mount(root);
        expect(calls.forms[0]._method).toBe('post');
        state.program = program;
        await nextTick();
        root.querySelector('form')!.dispatchEvent(
            new Event('submit', { bubbles: true, cancelable: true }),
        );
        expect(calls.forms[0]._method).toBe('put');
        expect(calls.post).toHaveBeenCalledWith(
            '/admin/programs/1',
            expect.any(Object),
        );
    });
    it('treats registration closing timestamp as exclusive', () => {
        expect(
            registrationOpen(
                program,
                new Date(program.registration_closes_at).getTime(),
            ),
        ).toBe(false);
        expect(
            registrationOpen(
                program,
                new Date(program.registration_closes_at).getTime() - 1,
            ),
        ).toBe(true);
    });
    it('formats admin dates in Malaysia time without browser timezone dependence', () => {
        expect(localDateTime('2026-11-25T02:00:00Z')).toBe(
            '2026-11-25T10:00:00',
        );
    });
    it('submits a registration without browser-supplied price or capacity', () => {
        const root = mount();
        root.querySelector('form')!.dispatchEvent(
            new Event('submit', { bubbles: true, cancelable: true }),
        );
        expect(calls.post).toHaveBeenCalledWith('/vip/programs/1/register');
    });
    it('offers renewal instead of registration when membership will expire', () => {
        const root = mount({}, false);
        expect(root.querySelector('form')).toBeNull();
        expect(root.querySelector('a[href="/membership"]')).not.toBeNull();
    });
    it('does not offer registration for a full program', () => {
        const root = mount({ registered_count: 1 });
        expect(root.querySelector('form')).toBeNull();
        expect(root.textContent).toContain('fully booked');
    });
    it('does not offer registration when disabled', () => {
        const root = mount({ registration_enabled: false });
        expect(root.querySelector('form')).toBeNull();
        expect(root.textContent).toContain('Registration is closed');
    });
});
