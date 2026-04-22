<script setup lang="ts">
import { computed } from 'vue';
import ColorPicker from '@/components/ColorPicker.vue';
import { resolveBlockColorInputValue } from '@/lib/blockStyles';
import type { BlockPreset } from '../schema';

const props = withDefaults(
    defineProps<{
        id?: string;
        fieldKey?: string;
        modelValue?: unknown;
        defaultValue?: string;
        presets?: BlockPreset[];
    }>(),
    {
        id: undefined,
        fieldKey: 'color',
        modelValue: undefined,
        defaultValue: '#000000',
        presets: () => [],
    },
);

const emit = defineEmits<{
    'update:modelValue': [value: string];
}>();

const resolvedValue = computed(() =>
    resolveBlockColorInputValue(
        props.fieldKey,
        props.modelValue,
        props.defaultValue,
    ),
);

const colorPresets = computed(() =>
    (props.presets ?? []).filter(
        (preset): preset is { label: string; value: string } =>
            typeof preset.value === 'string',
    ),
);
</script>

<template>
    <ColorPicker
        :id="id"
        :model-value="resolvedValue"
        :default-value="defaultValue"
        :presets="colorPresets"
        @update:model-value="emit('update:modelValue', $event)"
    />
</template>
