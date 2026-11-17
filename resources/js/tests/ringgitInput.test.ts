import { describe, expect, it } from 'vite-plus/test';
import { createApp, h, nextTick, ref } from 'vue';
import RinggitInput from '@/components/RinggitInput.vue';

describe('RM price input', () => {
    it('displays RM and converts decimal prices back to integer sen', async () => {
        const amount = ref<number | null>(19900);
        const root = document.createElement('div');
        const app = createApp({
            render: () =>
                h(RinggitInput, {
                    modelValue: amount.value,
                    'onUpdate:modelValue': (value) => (amount.value = value),
                }),
        });
        app.mount(root);
        try {
            const input = root.querySelector('input')!;
            expect(input.value).toBe('199.00');
            expect(input.step).toBe('0.01');
            input.value = '10.50';
            input.dispatchEvent(new Event('change'));
            await nextTick();
            expect(amount.value).toBe(1050);
            input.value = '0.29';
            input.dispatchEvent(new Event('change'));
            await nextTick();
            expect(amount.value).toBe(29);
            input.value = '';
            input.dispatchEvent(new Event('change'));
            await nextTick();
            expect(amount.value).toBeNull();
            amount.value = 9900;
            await nextTick();
            expect(input.value).toBe('99.00');
        } finally {
            app.unmount();
        }
    });
});
