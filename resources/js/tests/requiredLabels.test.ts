import { describe, expect, it } from 'vite-plus/test';

const forms = import.meta.glob<string>(
    ['../pages/**/*.vue', '../components/*.vue'],
    { query: '?raw', import: 'default', eager: true },
);

function template(source: string) {
    return new DOMParser().parseFromString(
        source
            .slice(
                source.indexOf('<template>') + '<template>'.length,
                source.lastIndexOf('</template>'),
            )
            .replace(/<\/?template\b[^>]*>/g, '')
            .replace(/<textarea\b([^>]*?)\/>/g, '<textarea$1></textarea>')
            .replace(/<(Input|PasswordInput|RinggitInput)\b/g, '<input'),
        'text/html',
    );
}

describe('required field labels', () => {
    it('marks every labelled required input in red after its label text', () => {
        let checked = 0;
        for (const [path, source] of Object.entries(forms)) {
            const document = template(source);
            for (const control of document.querySelectorAll(
                'input, textarea, select, Input, PasswordInput, RinggitInput',
            )) {
                if (
                    !control.hasAttribute('required') &&
                    !control.hasAttribute(':required')
                )
                    continue;
                // Shipping address radios share the section heading.
                if (control.getAttribute('name') === 'address') {
                    expect(
                        document.querySelector('h2 .text-red-600'),
                        path,
                    ).not.toBeNull();
                    continue;
                }
                const label =
                    control.closest('label') ??
                    Array.from(document.querySelectorAll('label')).find(
                        (label) => label.htmlFor === control.id && control.id,
                    );
                expect(
                    label,
                    `${path}: required control has a label`,
                ).toBeTruthy();
                const marker = label?.querySelector('.text-red-600');
                expect(marker?.textContent?.trim(), path).toBe('*');
                expect(marker?.getAttribute('aria-hidden'), path).toBe('true');
                expect(
                    label?.textContent?.trim().indexOf('*'),
                    path,
                ).toBeGreaterThan(0);
                checked++;
            }
        }
        expect(checked).toBeGreaterThan(100);
    });

    it('keeps conditional markers in sync with conditional requirements', () => {
        for (const source of Object.values(forms)) {
            const document = template(source);
            for (const label of document.querySelectorAll('label')) {
                const control = Array.from(label.querySelectorAll('*')).find(
                    (element) => element.hasAttribute(':required'),
                );
                if (!control) continue;
                expect(
                    label.querySelector('.text-red-600')?.getAttribute('v-if'),
                ).toBe(control.getAttribute(':required')?.replace(/^!!/, ''));
            }
        }
    });

    it('leaves optional address and order notes unmarked', () => {
        for (const [path, field] of [
            ['../components/AddressForm.vue', 'form.line2'],
            ['../pages/public/Checkout.vue', 'form.notes'],
            ['../pages/admin/products/Edit.vue', 'form.sale_price_sen'],
        ]) {
            const document = template(forms[path]);
            const control = Array.from(document.querySelectorAll('*')).find(
                (element) => element.getAttribute('v-model') === field,
            );
            expect(
                control?.closest('label')?.querySelector('.text-red-600'),
            ).toBeNull();
        }
    });
});
