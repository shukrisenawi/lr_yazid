import { afterEach, describe, expect, it, vi } from 'vite-plus/test';
import { createApp, h, nextTick, type App, type VNode } from 'vue';
import ReportMonthly from '@/components/ReportMonthly.vue';
import ReportOverview from '@/components/ReportOverview.vue';
import { barPercent, reportExportUrl } from '@/lib/reporting';
import type { AdminReport } from '@/types/reports';
vi.mock('@inertiajs/vue3', () => ({
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
}));
let app: App | undefined;
afterEach(() => {
    app?.unmount();
    document.body.innerHTML = '';
});
function fixture(): AdminReport {
    return {
        filters: {
            from: '2026-11-01',
            to: '2026-11-30',
            environment: 'sandbox',
        },
        as_of: '2026-11-15T04:00:00Z',
        summary: {
            collected_sen: 12345,
            membership_sen: 10000,
            paid_orders: 1,
        },
        current: { customers: 3 },
        monthly: [
            {
                month: '2026-11',
                collected_sen: 12345,
                manual_refund_sen: 0,
                membership_sen: 10000,
                paid_orders: 1,
                orders_created: 2,
                vip_activations: 1,
                vip_renewals: 0,
                appointment_requests: 3,
            },
        ],
        appointments: { approved: 2, pending: 1 },
        labels: {
            collected_sen: 'Collected sales',
            membership_sen: 'Membership items',
            paid_orders: 'Paid orders',
            customers: 'Customers',
            orders_created: 'Orders created',
            vip_activations: 'VIP activation customers',
            vip_renewals: 'VIP renewal purchases',
            appointment_requests: 'Appointment requests submitted',
        },
        statusLabels: { approved: 'Approved', pending: 'Pending approval' },
    };
}
describe('administration reports', () => {
    it('exports the applied date range and environment without nullable filter artifacts', () => {
        const url = new URL(
            reportExportUrl(fixture().filters),
            'https://example.com',
        );
        expect(url.pathname).toBe('/admin/reports/export');
        expect(url.searchParams.get('environment')).toBe('sandbox');
        expect(url.searchParams.get('from')).toBe('2026-11-01');
        expect(url.searchParams.get('to')).toBe('2026-11-30');
    });
    it('scales financial bars without dividing by zero or exceeding the chart', () => {
        expect(barPercent(0, 0)).toBe(0);
        expect(barPercent(10, 20)).toBe(50);
        expect(barPercent(30, 20)).toBe(100);
        expect(barPercent(-1, 20)).toBe(0);
    });
    it('renders exact MYR figures in an accessible chart and monthly table', () => {
        const root = document.createElement('div');
        document.body.append(root);
        app = createApp({
            render: () => h(ReportMonthly, { report: fixture() }),
        });
        app.mount(root);
        expect(root.querySelector('table')?.textContent).toContain('123.45');
        expect(
            root.querySelector('[role="img"]')?.getAttribute('aria-label'),
        ).toContain('100.00');
        expect(root.querySelector('th[scope="row"]')?.textContent).toBe(
            '2026-11',
        );
        expect(root.textContent).toContain('Pending approval');
        expect(root.innerHTML).not.toContain('NaN');
    });
    it('clearly labels sandbox results and separates current operational figures', () => {
        const root = document.createElement('div');
        document.body.append(root);
        app = createApp({
            render: () => h(ReportOverview, { report: fixture() }),
        });
        app.mount(root);
        expect(root.querySelector('[role="note"]')?.textContent).toContain(
            'test data, not live sales',
        );
        expect(root.textContent).toContain('not historical counts');
        expect(root.querySelector('a[href="/admin/payments"]')).not.toBeNull();
    });
    it('shows real zero states instead of sample revenues', () => {
        const report = fixture();
        report.summary = { collected_sen: 0, paid_orders: 0 };
        const root = document.createElement('div');
        document.body.append(root);
        app = createApp({ render: () => h(ReportOverview, { report }) });
        app.mount(root);
        expect(root.textContent).toContain('No verified paid sales');
        expect(root.textContent).toContain('0.00');
    });
    it('switches the activity chart to the selected real monthly series', async () => {
        const root = document.createElement('div');
        document.body.append(root);
        app = createApp({
            render: () => h(ReportMonthly, { report: fixture() }),
        });
        app.mount(root);
        const buttons = root.querySelectorAll<HTMLButtonElement>(
            '[role="group"] button',
        );
        expect(buttons[0].getAttribute('aria-pressed')).toBe('true');
        buttons[3].click();
        await nextTick();
        expect(buttons[3].getAttribute('aria-pressed')).toBe('true');
        expect(
            root
                .querySelector(
                    'section[aria-label="Monthly activity trends"] [role="img"]',
                )
                ?.getAttribute('aria-label'),
        ).toContain('Appointment requests submitted 3');
    });
});
