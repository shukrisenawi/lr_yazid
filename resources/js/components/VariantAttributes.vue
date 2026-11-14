<script setup lang="ts">
const model = defineModel<{ name: string; value: string }[]>({
    default: () => [],
});
function update(index: number, key: 'name' | 'value', value: string) {
    model.value = model.value.map((item, position) =>
        position === index ? { ...item, [key]: value } : item,
    );
}
</script>
<template>
    <fieldset class="space-y-3">
        <legend class="mb-2 text-sm font-medium">Variant attributes</legend>
        <div
            v-for="(attribute, index) in model"
            :key="index"
            class="grid items-end gap-2 sm:grid-cols-[1fr_1fr_auto]"
        >
            <label class="grid gap-1 text-xs"
                >Attribute name<input
                    :value="attribute.name"
                    required
                    maxlength="50"
                    placeholder="Size or Color"
                    class="min-w-0 rounded border bg-transparent p-2"
                    @input="
                        update(
                            index,
                            'name',
                            ($event.target as HTMLInputElement).value,
                        )
                    "
            /></label>
            <label class="grid gap-1 text-xs"
                >Attribute value<input
                    :value="attribute.value"
                    required
                    maxlength="100"
                    placeholder="Large or Black"
                    class="min-w-0 rounded border bg-transparent p-2"
                    @input="
                        update(
                            index,
                            'value',
                            ($event.target as HTMLInputElement).value,
                        )
                    "
            /></label>
            <button
                type="button"
                class="rounded border p-2 text-xs"
                @click="
                    model = model.filter((_, position) => position !== index)
                "
            >
                Remove attribute
            </button>
        </div>
        <button
            type="button"
            class="text-xs underline disabled:opacity-50"
            :disabled="model.length >= 10"
            @click="model = [...model, { name: '', value: '' }]"
        >
            Add attribute
        </button>
    </fieldset>
</template>
