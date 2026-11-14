import { afterEach, describe, expect, it, vi } from 'vite-plus/test';
import { createApp, h, reactive, nextTick, type App, type VNode } from 'vue';
import Slots from '@/pages/admin/appointments/Slots.vue';
import type { AppointmentService, AppointmentSlot } from '@/types/appointment';

const calls = vi.hoisted(() => ({
    post: vi.fn(),
    forms: [] as Record<string, unknown>[],
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
    useForm: (data: Record<string, unknown>) => {
        const form = reactive({
            ...data,
            errors: {},
            processing: false,
            post: calls.post,
            get: vi.fn(),
            put: vi.fn(),
            delete: vi.fn(),
            reset: vi.fn(),
            clearErrors: vi.fn(),
        });
        calls.forms.push(form);
        return form;
    },
}));
let app: App | undefined;
afterEach(() => {
    app?.unmount();
    document.body.innerHTML = '';
    vi.clearAllMocks();
    calls.forms.length = 0;
});
const service: AppointmentService = {
    id: 1,
    name: 'Consultation',
    description: '',
    location: 'Kuala Lumpur',
    duration_minutes: 30,
    default_capacity: 1,
    is_active: true,
    minimum_notice_hours: 24,
    maximum_advance_days: 90,
    cancellation_notice_hours: 24,
    maximum_active_bookings: 3,
    slots_count: 0,
};
function mount(rows: AppointmentSlot[] = []) {
    const root = document.createElement('div');
    document.body.append(root);
    app = createApp({
        render: () =>
            h(Slots, {
                service,
                slots: {
                    data: rows,
                    links: [],
                    total: rows.length,
                },
                blockedDates: [],
                schedules: [],
                generation: null,
                filters: {},
            }),
    });
    app.mount(root);
    return root;
}
describe('appointment scheduling', () => {
    it('previews without requesting persistence', async () => {
        const root = mount();
        root.querySelectorAll('nav button')[1].dispatchEvent(
            new Event('click'),
        );
        await nextTick();
        root.querySelector('form')!.dispatchEvent(
            new Event('submit', { bubbles: true, cancelable: true }),
        );
        expect(calls.forms[2].preview).toBe(true);
        expect(calls.post).toHaveBeenCalledWith(
            '/admin/appointment-services/1/generate-slots',
            expect.any(Object),
        );
    });
    it('uses an explicit generation action distinct from preview', async () => {
        const root = mount();
        root.querySelectorAll('nav button')[1].dispatchEvent(
            new Event('click'),
        );
        await nextTick();
        const button = Array.from(root.querySelectorAll('form button')).find(
            (button) => button.textContent === 'Generate slots',
        )!;
        button.dispatchEvent(new Event('click'));
        expect(calls.forms[2].preview).toBe(false);
    });
    it('converts edited slot timestamps to Malaysia local inputs', async () => {
        const root = mount([
            {
                id: 2,
                starts_at: '2026-11-16T01:00:00Z',
                ends_at: '2026-11-16T01:30:00Z',
                capacity: 1,
                location: 'KL',
                notes: null,
                status: 'available',
                appointments_count: 0,
            },
        ]);
        Array.from(root.querySelectorAll('td button'))
            .find((button) => button.textContent === 'Edit')!
            .dispatchEvent(new Event('click'));
        await nextTick();
        expect(calls.forms[1].starts_at).toBe('2026-11-16T09:00:00');
    });
    it('does not offer deletion for slots with appointment history', () => {
        const root = mount([
            {
                id: 2,
                starts_at: '2026-11-16T01:00:00Z',
                ends_at: '2026-11-16T01:30:00Z',
                capacity: 1,
                location: 'KL',
                notes: null,
                status: 'available',
                appointments_count: 1,
            },
        ]);
        expect(root.textContent).not.toContain('Delete unused slot');
    });
});
