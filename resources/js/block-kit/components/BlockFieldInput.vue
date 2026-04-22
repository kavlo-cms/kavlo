<script setup lang="ts">
import { Input } from '@/components/ui/input';
import { Switch } from '@/components/ui/switch';
import { Textarea } from '@/components/ui/textarea';
import type { MediaItem } from '@/components/MediaPicker.vue';
import type { BlockField, BlockPageOption } from '../schema';
import BlockColorInput from './BlockColorInput.vue';
import BlockGradientInput from './BlockGradientInput.vue';
import BlockMediaInput from './BlockMediaInput.vue';
import BlockPageLinkInput from './BlockPageLinkInput.vue';

withDefaults(
    defineProps<{
        id?: string;
        field: BlockField;
        modelValue?: unknown;
        pages?: BlockPageOption[];
    }>(),
    {
        id: undefined,
        modelValue: undefined,
        pages: () => [],
    },
);

const emit = defineEmits<{
    'update:modelValue': [value: unknown];
    'media-select': [item: MediaItem];
}>();
</script>

<template>
    <div
        v-if="field.type === 'toggle'"
        class="flex items-center gap-2 pt-0.5"
    >
        <Switch
            :id="id"
            :model-value="Boolean(modelValue)"
            @update:model-value="emit('update:modelValue', $event)"
        />
        <span class="text-xs text-muted-foreground">
            {{ modelValue ? 'On' : 'Off' }}
        </span>
    </div>

    <select
        v-else-if="field.type === 'select'"
        :id="id"
        :value="String(modelValue ?? field.options?.[0]?.value ?? '')"
        class="flex h-9 w-full rounded-md border border-input bg-transparent px-3 py-1 text-sm shadow-sm transition-colors focus:ring-1 focus:ring-ring focus:outline-none"
        @change="
            emit(
                'update:modelValue',
                ($event.target as HTMLSelectElement).value,
            )
        "
    >
        <option
            v-for="option in field.options ?? []"
            :key="option.value"
            :value="option.value"
        >
            {{ option.label }}
        </option>
    </select>

    <BlockColorInput
        v-else-if="field.type === 'color'"
        :id="id"
        :field-key="field.key"
        :model-value="modelValue"
        :default-value="
            typeof field.defaultValue === 'string'
                ? field.defaultValue
                : '#000000'
        "
        :presets="field.presets ?? []"
        @update:model-value="emit('update:modelValue', $event)"
    />

    <BlockGradientInput
        v-else-if="field.type === 'gradient'"
        :model-value="modelValue"
        :default-value="field.defaultValue"
        :presets="field.presets ?? []"
        @update:model-value="emit('update:modelValue', $event)"
    />

    <Textarea
        v-else-if="field.type === 'textarea'"
        :id="id"
        :model-value="String(modelValue ?? '')"
        :placeholder="field.placeholder"
        rows="3"
        @update:model-value="emit('update:modelValue', String($event))"
    />

    <BlockMediaInput
        v-else-if="field.type === 'media'"
        :model-value="String(modelValue ?? '')"
        @update:model-value="emit('update:modelValue', $event)"
        @select="emit('media-select', $event)"
    />

    <BlockPageLinkInput
        v-else-if="field.type === 'page-link'"
        :id="id"
        :model-value="String(modelValue ?? '')"
        :pages="pages"
        @update:model-value="emit('update:modelValue', $event)"
    />

    <Input
        v-else
        :id="id"
        :type="
            field.type === 'url'
                ? 'url'
                : field.type === 'number'
                  ? 'number'
                  : 'text'
        "
        :model-value="String(modelValue ?? '')"
        :placeholder="field.placeholder"
        @update:model-value="emit('update:modelValue', String($event))"
    />
</template>
