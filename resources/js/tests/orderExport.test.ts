import { describe, expect, it } from 'vite-plus/test';
import { orderExportUrl } from '@/lib/orderExport';
describe('order export filters', () => {
    it('omits empty nullable filters returned by Laravel', () => {
        expect(
            orderExportUrl({
                search: 'QA',
                payment: 'paid',
                fulfillment: null,
                from: '',
                to: undefined,
            }),
        ).toBe('/admin/orders/export?search=QA&payment=paid');
    });
    it('encodes values without introducing extra query parameters', () => {
        const url = new URL(
            orderExportUrl({
                search: 'QA & test',
                from: '2026-11-15',
                to: '2026-11-16',
            }),
            'https://example.com',
        );
        expect(url.searchParams.get('search')).toBe('QA & test');
        expect(url.searchParams.get('from')).toBe('2026-11-15');
        expect([...url.searchParams.keys()]).toHaveLength(3);
    });
});
