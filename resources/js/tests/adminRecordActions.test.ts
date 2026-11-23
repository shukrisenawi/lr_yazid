/// <reference types="node" />
import { readFileSync } from 'node:fs';
import { describe, expect, it } from 'vite-plus/test';

const source = (path: string) => readFileSync(`resources/js/${path}`, 'utf8');

describe('administrator list actions', () => {
    it('uses accessible icon buttons and a confirmation modal without a delete reason', () => {
        const action = source('components/AdminRecordAction.vue');
        expect(action).toContain('<DialogContent');
        expect(action).toMatch(/<DialogTitle\s*>/);
        expect(action).toContain('<DialogDescription>');
        expect(action).toContain('v-if="archive"');
        expect(action).toContain('form.transform(() => ({})).delete(');
        expect(action).toContain('<Trash2');
        expect(action).toContain(':aria-label=');
        const edit = source('components/AdminEditButton.vue');
        expect(edit).toContain('<button');
        expect(edit).toContain('<Pencil');
        expect(edit).toContain('<TooltipContent>');
        expect(edit).not.toContain('<Link');
    });

    it('shows edit and delete actions in each master-data list', () => {
        for (const path of [
            'website/Banners.vue',
            'content/Index.vue',
            'programs/Index.vue',
            'customers/Index.vue',
            'appointments/Requests.vue',
            'products/Index.vue',
            'MembershipPlans.vue',
            'Memberships.vue',
            'appointments/Services.vue',
            'appointments/Slots.vue',
        ]) {
            const page = source(`pages/admin/${path}`);
            expect(page, path).toContain('<AdminEditButton');
            expect(page, path).toContain('<AdminRecordAction');
        }
    });

    it('does not show record delete actions in detail and edit pages', () => {
        for (const path of [
            'website/BannerEdit.vue',
            'content/Edit.vue',
            'programs/Edit.vue',
            'customers/Show.vue',
            'appointments/Review.vue',
            'products/Edit.vue',
        ]) {
            expect(source(`pages/admin/${path}`), path).not.toContain(
                'AdminRecordAction',
            );
        }
        expect(source('pages/admin/products/Edit.vue')).not.toContain(
            'destructive.delete(',
        );
    });
});
