import { afterEach, describe, expect, it, vi } from 'vite-plus/test';
import { createApp, type App } from 'vue';
import BrandLogo from '@/components/BrandLogo.vue';

const state = vi.hoisted(() => ({ logoUrl: null as string | null }));
vi.mock('@inertiajs/vue3', () => ({
    usePage: () => ({
        props: {
            name: 'Store',
            website: { website_name: 'csiti.my', logo_url: state.logoUrl },
        },
    }),
}));

let app: App | undefined;
afterEach(() => {
    app?.unmount();
    document.body.innerHTML = '';
    state.logoUrl = null;
});

function mount(compact = false) {
    const root = document.createElement('div');
    document.body.append(root);
    app = createApp(BrandLogo, { compact });
    app.mount(root);
    return root.querySelector('img')!;
}

describe('system branding', () => {
    it('uses the supplied full logo by default', () => {
        const image = mount();
        expect(image.getAttribute('src')).toBe('/images/brand/logo-full.png');
        expect(image.alt).toBe('csiti.my');
        expect(image.getAttribute('width')).toBe('1626');
    });

    it('preserves administrator logo overrides', () => {
        state.logoUrl = '/storage/website/custom.webp';
        expect(mount().getAttribute('src')).toBe(state.logoUrl);
    });

    it('uses the small logo in collapsed navigation', () => {
        expect(mount(true).getAttribute('src')).toBe(
            '/images/brand/logo-mark.png',
        );
    });
});
