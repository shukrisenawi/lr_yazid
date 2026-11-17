<script setup lang="ts">
defineProps<{ modelValue: number | null }>();
const emit = defineEmits<{ 'update:modelValue': [value: number | null] }>();
function update(event: Event) {
    const value = (event.target as HTMLInputElement).value;
    emit(
        'update:modelValue',
        value === '' ? null : Math.round(Number(value) * 100),
    );
}
</script>

<template>
    <input
        type="number"
        step="0.01"
        :value="modelValue === null ? '' : (modelValue / 100).toFixed(2)"
        @change="update"
    />
</template>
