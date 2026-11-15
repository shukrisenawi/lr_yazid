import { afterEach, describe, expect, it, vi } from 'vite-plus/test';
import { createApp, h, nextTick, type App, type VNode } from 'vue';
import HomeBanner from '@/components/HomeBanner.vue';
import { websiteTitle } from '@/lib/websiteTitle';
import type { PublicBanner } from '@/types/website';
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
    vi.restoreAllMocks();
});
const banners: PublicBanner[] = [
    {
        id: 1,
        title: '<b>First banner</b>',
        subtitle: 'First supporting text',
        image_url: '/storage/banner1.webp',
        image_alt: 'First products',
        link_label: 'Browse products',
        link_path: '/products',
    },
    {
        id: 2,
        title: 'Second banner',
        subtitle: null,
        image_url: '/storage/banner2.webp',
        image_alt: 'Second products',
        link_label: null,
        link_path: null,
    },
];
function mount(items: PublicBanner[]) {
    const root = document.createElement('div');
    document.body.append(root);
    app = createApp({ render: () => h(HomeBanner, { banners: items }) });
    app.mount(root);
    return root;
}
describe('homepage banners', () => {
    it('renders the initial banner with escaped copy and an internal destination', () => {
        const root = mount(banners);
        expect(root.querySelector('h1')?.textContent).toBe(
            '<b>First banner</b>',
        );
        expect(root.querySelector('h1 b')).toBeNull();
        expect(root.querySelector('a')?.getAttribute('href')).toBe('/products');
        expect(root.querySelector('img')?.getAttribute('alt')).toBe(
            'First products',
        );
    });
    it('switches banners only through manual controls and exposes the selected state', async () => {
        const interval = vi.spyOn(window, 'setInterval');
        const root = mount(banners);
        const buttons = root.querySelectorAll('button');
        expect(buttons[0].getAttribute('aria-pressed')).toBe('true');
        buttons[1].click();
        await nextTick();
        expect(root.querySelector('h1')?.textContent).toBe('Second banner');
        expect(buttons[1].getAttribute('aria-pressed')).toBe('true');
        expect(root.querySelector('a')).toBeNull();
        expect(interval).not.toHaveBeenCalled();
    });
    it('does not render carousel controls for a single banner or content for an empty list', () => {
        const root = mount([banners[0]]);
        expect(root.querySelector('button')).toBeNull();
        app?.unmount();
        app = undefined;
        const empty = mount([]);
        expect(empty.querySelector('section')).toBeNull();
    });
    it('resets the selected banner when the available publication list changes', async () => {
        const root = document.createElement('div');
        document.body.append(root);
        const { ref } = await import('vue');
        const items = ref(banners);
        app = createApp({
            render: () => h(HomeBanner, { banners: items.value }),
        });
        app.mount(root);
        root.querySelectorAll('button')[1].click();
        await nextTick();
        items.value = [banners[0]];
        await nextTick();
        expect(root.querySelector('h1')?.textContent).toBe(
            '<b>First banner</b>',
        );
    });
});
describe('website document titles', () => {
    it('uses the configured name with safe fallback for absent or invalid settings', () => {
        expect(
            websiteTitle('Home', { website_name: 'New Store' }, 'Default'),
        ).toBe('Home - New Store');
        expect(websiteTitle('', { website_name: 'New Store' }, 'Default')).toBe(
            'New Store',
        );
        for (const invalid of [
            undefined,
            null,
            {},
            { website_name: 123 },
            { website_name: '' },
        ]) {
            expect(websiteTitle('Home', invalid, 'Default')).toBe(
                'Home - Default',
            );
        }
    });
});
