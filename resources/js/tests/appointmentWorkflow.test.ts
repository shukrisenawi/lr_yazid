import { afterEach, describe, expect, it, vi } from 'vite-plus/test';
import { createApp, h, reactive, nextTick, type App, type VNode } from 'vue';
import AppointmentCalendar from '@/components/AppointmentCalendar.vue';
import Book from '@/pages/vip/appointments/Book.vue';
import Show from '@/pages/customer/appointments/Show.vue';
import {
    calendarDays,
    malaysiaDate,
    shiftMonth,
} from '@/lib/appointmentCalendar';
import type {
    AvailableAppointmentSlot,
    Appointment,
} from '@/types/appointment';

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
    router: { get: vi.fn() },
    useForm: (data: Record<string, unknown>) => {
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
    calls.forms.length = 0;
    vi.clearAllMocks();
});
function mount(render: () => VNode) {
    const root = document.createElement('div');
    document.body.append(root);
    app = createApp({ render });
    app.mount(root);
    return root;
}
const slots: AvailableAppointmentSlot[] = [
    {
        id: 1,
        starts_at: '2026-11-20T09:00:00+08:00',
        ends_at: '2026-11-20T09:30:00+08:00',
        location: 'KL',
        remaining: 1,
        capacity: 1,
        available: true,
        reason: null,
    },
    {
        id: 2,
        starts_at: '2026-11-20T10:00:00+08:00',
        ends_at: '2026-11-20T10:30:00+08:00',
        location: 'KL',
        remaining: 0,
        capacity: 1,
        available: false,
        reason: 'Fully booked',
    },
];
const appointment: Appointment = {
    id: 3,
    reference: 'APT-TEST',
    status: 'approved',
    purpose: 'Discuss benefits',
    notes: null,
    scheduled_starts_at: slots[0].starts_at,
    scheduled_ends_at: slots[0].ends_at,
    service_name: 'Consultation',
    location: 'KL',
    appointment_service_id: 1,
    appointment_slot_id: 1,
    admin_remarks: null,
    cancellation_reason: null,
    created_at: slots[0].starts_at,
    reschedule_requests: [
        {
            id: 4,
            status: 'pending',
            origin: 'customer',
            reason: 'Please change the date',
            review_remarks: null,
            target_starts_at: '2026-11-21T09:00:00+08:00',
            target_ends_at: '2026-11-21T09:30:00+08:00',
            target_location: 'KL',
        },
    ],
};
describe('appointment workflow', () => {
    it('builds a Monday-first leap-month calendar', () => {
        const days = calendarDays('2028-02');
        expect(days).toHaveLength(42);
        expect(days).toContain('2028-02-29');
        expect(new Date(`${days[0]}T00:00:00Z`).getUTCDay()).toBe(1);
    });
    it('uses Malaysia dates around UTC midnight boundaries', () => {
        expect(malaysiaDate('2026-11-15T18:00:00Z')).toBe('2026-11-16');
    });
    it('navigates correctly across year boundaries', () => {
        expect(shiftMonth('2026-12', 1)).toBe('2027-01');
        expect(shiftMonth('2026-01', -1)).toBe('2025-12');
    });
    it('does not allow selection of unavailable slots', () => {
        const selected = vi.fn();
        const root = mount(() =>
            h(AppointmentCalendar, {
                month: '2026-11',
                slots,
                modelValue: null,
                'onUpdate:modelValue': selected,
            }),
        );
        const button = Array.from(root.querySelectorAll('button')).find(
            (button) => button.textContent?.includes('Fully booked'),
        )!;
        expect(button.disabled).toBe(true);
        button.click();
        expect(selected).not.toHaveBeenCalled();
    });
    it('emits only the selected slot identifier', () => {
        const selected = vi.fn();
        const root = mount(() =>
            h(AppointmentCalendar, {
                month: '2026-11',
                slots,
                modelValue: null,
                'onUpdate:modelValue': selected,
            }),
        );
        Array.from(root.querySelectorAll('button'))
            .find((button) => button.textContent?.includes('places remaining'))!
            .click();
        expect(selected).toHaveBeenCalledWith(1);
    });
    it('navigates months without submitting a booking', () => {
        const change = vi.fn();
        const root = mount(() =>
            h(AppointmentCalendar, {
                month: '2026-11',
                slots,
                modelValue: null,
                onMonth: change,
            }),
        );
        root.querySelector<HTMLButtonElement>(
            'button[aria-label="Next"]',
        )!.click();
        expect(change).toHaveBeenCalledWith('2026-12');
        expect(calls.post).not.toHaveBeenCalled();
    });
    it('requires a selected slot before enabling request submission', () => {
        const root = mount(() =>
            h(Book, {
                services: [{ id: 1, name: 'Consultation', description: '' }],
                serviceId: 1,
                month: '2026-11',
                slots,
                bookingKey: 'test-key',
                reschedule: null,
            }),
        );
        expect(
            root.querySelector<HTMLButtonElement>('form button')!.disabled,
        ).toBe(true);
        expect(root.textContent).toContain('administrator approval');
    });
    it('submits new requests to the pending booking endpoint', async () => {
        const root = mount(() =>
            h(Book, {
                services: [{ id: 1, name: 'Consultation', description: '' }],
                serviceId: 1,
                month: '2026-11',
                slots,
                bookingKey: 'test-key',
                reschedule: null,
            }),
        );
        Array.from(root.querySelectorAll('button'))
            .find((button) => button.textContent?.includes('places remaining'))!
            .click();
        await nextTick();
        root.querySelector('form')!.dispatchEvent(
            new Event('submit', { bubbles: true, cancelable: true }),
        );
        expect(calls.forms[0].slot_id).toBe(1);
        expect(calls.post).toHaveBeenCalledWith('/vip/appointments');
    });
    it('submits rescheduling as a request, not an approval', () => {
        const root = mount(() =>
            h(Book, {
                services: [{ id: 1, name: 'Consultation', description: '' }],
                serviceId: 1,
                month: '2026-11',
                slots,
                bookingKey: 'test-key',
                reschedule: { id: 3, reference: 'APT-TEST' },
            }),
        );
        root.querySelector('form')!.dispatchEvent(
            new Event('submit', { bubbles: true, cancelable: true }),
        );
        expect(calls.post).toHaveBeenCalledWith('/appointments/3/reschedule');
        expect(root.textContent).toContain(
            'original appointment remains unchanged',
        );
    });
    it('shows original approval while a reschedule is pending and prevents a second request', () => {
        const root = mount(() =>
            h(Show, {
                appointment,
                canReschedule: true,
                cancellationNoticeHours: 24,
            }),
        );
        expect(root.textContent).toContain('Approved');
        expect(root.textContent).toContain('Pending Approval');
        expect(root.querySelector('a[href*="reschedule="]')).toBeNull();
    });
});
