<script setup lang="ts">
import { computed } from 'vue';
import InlineEdit from '@/admin/Pages/partials/InlineEdit.vue';

const props = defineProps<{ data: Record<string, unknown> }>();
const emit = defineEmits<{ 'update:data': [Record<string, unknown>] }>();

const alignClass = computed(() =>
    ({ left: 'justify-start', center: 'justify-center', right: 'justify-end' }[(props.data.align as string) ?? 'center'] ?? 'justify-center'),
);

const variantClass = computed(() =>
    ({
        primary: 'bg-primary text-primary-foreground hover:bg-primary/90',
        secondary: 'bg-secondary text-secondary-foreground hover:bg-secondary/80',
        outline: 'border border-input bg-background hover:bg-accent hover:text-accent-foreground',
        ghost: 'hover:bg-accent hover:text-accent-foreground',
    }[(props.data.variant as string) ?? 'primary'] ?? 'bg-primary text-primary-foreground hover:bg-primary/90'),
);

const sizeClass = computed(() =>
    ({ sm: 'px-4 py-1.5 text-sm', md: 'px-6 py-2.5 text-base', lg: 'px-8 py-3.5 text-lg' }[(props.data.size as string) ?? 'md'] ?? 'px-6 py-2.5 text-base'),
);
</script>

<template>
    <div class="flex py-6 px-4" :class="alignClass">
        <InlineEdit
            tag="span"
            :model-value="(data.text as string) ?? ''"
            placeholder="Button text…"
            :class="['inline-flex cursor-pointer items-center rounded-lg font-medium transition-colors', variantClass, sizeClass]"
            @update:model-value="emit('update:data', { ...data, text: $event })"
        />
    </div>
</template>
