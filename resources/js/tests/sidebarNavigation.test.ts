import { describe, expect, it } from 'vite-plus/test';
import { toUrl } from '@/lib/utils';
import {
    accountNavigation,
    activeNavigationHref,
    adminNavigation,
    adminNavigationGroups,
} from '@/lib/sidebarNavigation';

describe('grouped sidebar navigation', () => {
    it('preserves every admin destination without duplicates', () => {
        const items = [
            ...adminNavigation,
            ...adminNavigationGroups(false)
                .filter((group) => group.id !== 'account')
                .flatMap((group) => group.items),
        ];
        const paths = items.map((item) => item.href);
        expect(paths).toHaveLength(19);
        expect(new Set(paths).size).toBe(paths.length);
        for (const path of [
            '/admin/dashboard',
            '/admin/reports',
            '/admin/customers',
            '/admin/orders',
            '/admin/products',
            '/admin/categories',
            '/admin/payments',
            '/admin/shipping',
            '/admin/payment-settings',
            '/admin/commerce-notices',
            '/admin/memberships',
            '/admin/membership-plans',
            '/admin/programs',
            '/admin/appointments',
            '/admin/appointments/calendar',
            '/admin/appointment-services',
            '/admin/website-settings',
            '/admin/banners',
            '/admin/content-pages',
        ]) {
            expect(paths).toContain(path);
        }
        expect(new Set(items.map((item) => item.icon)).size).toBe(items.length);
    });

    it('limits admin navigation to three links and five collapsible groups', () => {
        expect(adminNavigation).toHaveLength(3);
        expect(adminNavigationGroups(false)).toHaveLength(5);
    });

    it('keeps VIP-only account links restricted to active members', () => {
        expect(
            accountNavigation(false).some((item) =>
                toUrl(item.href).startsWith('/vip'),
            ),
        ).toBe(false);
        expect(accountNavigation(true).map((item) => item.href)).toContain(
            '/vip/appointments/book',
        );
        expect(
            new Set(accountNavigation(true).map((item) => item.icon)).size,
        ).toBe(accountNavigation(true).length);
    });

    it('matches nested pages and ignores query strings without prefix collisions', () => {
        const items = adminNavigationGroups(false).flatMap(
            (group) => group.items,
        );
        expect(
            activeNavigationHref(
                items,
                '/admin/appointments/calendar?month=2026-11',
            ),
        ).toBe('/admin/appointments/calendar');
        expect(activeNavigationHref(items, '/admin/appointments/12')).toBe(
            '/admin/appointments',
        );
        expect(activeNavigationHref(items, '/admin/orders/25#invoice')).toBe(
            '/admin/orders',
        );
        expect(
            activeNavigationHref(items, '/admin/orders-archived'),
        ).toBeUndefined();
    });
});
