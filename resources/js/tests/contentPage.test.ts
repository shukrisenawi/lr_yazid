import { afterEach, describe, expect, it, vi } from 'vite-plus/test';
import { createApp, h, type App, type VNode } from 'vue';
import ContentPage from '@/pages/public/ContentPage.vue';
vi.mock('@inertiajs/vue3', () => ({
    Head: {
        props: ['title'],
        setup:
            (
                props: { title: string },
                { slots }: { slots: { default?: () => VNode[] } },
            ) =>
            () =>
                h('div', { 'data-head-title': props.title }, slots.default?.()),
    },
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
function mount(seo_title: string | null = 'About our business') {
    const root = document.createElement('div');
    document.body.append(root);
    app = createApp({
        render: () =>
            h(ContentPage, {
                contentPage: {
                    title: '<em>About Us</em>',
                    slug: 'about-us',
                    content:
                        '<p>Saved <strong>business information</strong>.</p>',
                    seo_title,
                    meta_description: 'Real information for customers.',
                    updated_at: '2026-11-15T00:00:00Z',
                },
            }),
    });
    app.mount(root);
    return root;
}
describe('public CMS page', () => {
    it('renders sanitized rich content and escapes the page heading', () => {
        const root = mount();
        expect(root.querySelector('h1')?.textContent).toBe('<em>About Us</em>');
        expect(root.querySelector('h1 em')).toBeNull();
        expect(root.querySelector('article strong')?.textContent).toBe(
            'business information',
        );
    });
    it('uses configured SEO title and description', () => {
        const root = mount();
        expect(
            root
                .querySelector('[data-head-title]')
                ?.getAttribute('data-head-title'),
        ).toBe('About our business');
        expect(
            root
                .querySelector('meta[name="description"]')
                ?.getAttribute('content'),
        ).toBe('Real information for customers.');
    });
    it('falls back to page title and provides a working home link', () => {
        const root = mount(null);
        expect(
            root
                .querySelector('[data-head-title]')
                ?.getAttribute('data-head-title'),
        ).toBe('<em>About Us</em>');
        expect(root.querySelector('a')?.getAttribute('href')).toBe('/');
        expect(root.textContent).toContain('Last updated');
    });
});
