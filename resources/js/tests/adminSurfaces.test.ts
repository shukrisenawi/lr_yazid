/// <reference types="node" />

import { readFileSync } from 'node:fs';
import { describe, expect, it } from 'vite-plus/test';

const css = readFileSync('resources/css/dashboard.css', 'utf8');
const selector = css.slice(
    css.indexOf('.admin-content section.border'),
    css.indexOf('{', css.indexOf('.admin-content section.border')),
);

function matches(markup: string, admin = true) {
    const root = document.createElement('div');
    root.className = admin ? 'admin-content' : 'storefront';
    root.innerHTML = markup;
    document.body.append(root);
    const count = document.querySelectorAll(selector).length;
    root.remove();
    return count;
}

describe('admin panel surfaces', () => {
    it('fills bordered panels, tables and editor containers', () => {
        for (const tag of [
            'section', 'article', 'aside', 'form', 'figure', 'fieldset',
        ]) {
            expect(
                matches(`<${tag} class="rounded-xl border p-6"></${tag}>`),
                tag,
            ).toBe(1);
        }
        expect(matches('<div class="overflow-x-auto rounded-xl border"></div>')).toBe(1);
        expect(matches('<div class="overflow-hidden rounded-md border"></div>')).toBe(1);
        expect(matches('<dl><div class="border p-4"></div></dl>')).toBe(1);
    });

    it('preserves intentional backgrounds and interactive states', () => {
        expect(matches('<section class="border bg-muted"></section>')).toBe(0);
        expect(matches('<section class="border bg-card"></section>')).toBe(0);
        expect(matches('<button class="rounded border bg-primary">Active</button>')).toBe(0);
        expect(matches('<a class="rounded border">Next</a>')).toBe(0);
        expect(matches('<span class="rounded border">Pending</span>')).toBe(0);
    });

    it('does not change storefront surfaces', () => {
        expect(matches('<section class="rounded-xl border"></section>', false)).toBe(0);
    });
});
