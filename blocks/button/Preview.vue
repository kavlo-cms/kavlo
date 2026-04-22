<script setup lang="ts">
import { computed } from 'vue';
import InlineEdit from '@/admin/Pages/partials/InlineEdit.vue';
import {
    buttonGradientStyle,
    buttonRadiusClass,
    buttonVariantClass,
    buttonVariantStyle,
    buttonWidthClass,
} from '@/lib/blockStyles';

const props = defineProps<{ data: Record<string, unknown> }>();
const emit = defineEmits<{ 'update:data': [Record<string, unknown>] }>();

const alignClass = computed(() =>
    ({ left: 'justify-start', center: 'justify-center', right: 'justify-end' }[(props.data.align as string) ?? 'center'] ?? 'justify-center'),
);

const variantClass = computed(() =>
    buttonVariantClass(props.data.variant, props.data.tone),
);
const gradientStyle = computed(() => buttonGradientStyle(props.data.gradient));
const variantStyle = computed(() =>
    gradientStyle.value ??
        buttonVariantStyle(props.data.variant, props.data.tone),
);

const sizeClass = computed(() =>
    ({ sm: 'px-4 py-1.5 text-sm', md: 'px-6 py-2.5 text-base', lg: 'px-8 py-3.5 text-lg' }[(props.data.size as string) ?? 'md'] ?? 'px-6 py-2.5 text-base'),
);

const radiusClass = computed(() => buttonRadiusClass(props.data.radius));
const widthClass = computed(() => buttonWidthClass(props.data.width));
</script>

<template>
    <div class="flex py-6 px-4" :class="alignClass">
        <InlineEdit
            tag="span"
            :model-value="(data.text as string) ?? ''"
            placeholder="Button text…"
            :class="[
                'inline-flex cursor-pointer items-center font-medium transition-colors',
                variantClass,
                sizeClass,
                radiusClass,
                widthClass,
            ]"
            :style="variantStyle"
            @update:model-value="emit('update:data', { ...data, text: $event })"
        />
    </div>
</template>
