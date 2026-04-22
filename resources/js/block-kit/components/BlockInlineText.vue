<script setup lang="ts">
import InlineEdit from '@/admin/Pages/partials/InlineEdit.vue';

withDefaults(
    defineProps<{
        tag?: string;
        modelValue: string;
        placeholder?: string;
    }>(),
    { tag: 'span', placeholder: undefined },
);

const emit = defineEmits<{ 'update:modelValue': [value: string] }>();
</script>

<template>
    <InlineEdit
        :tag="tag"
        :model-value="modelValue"
        :placeholder="placeholder"
        @update:model-value="emit('update:modelValue', $event)"
    />
</template>
