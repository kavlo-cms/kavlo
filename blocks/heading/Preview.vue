<script setup lang="ts">
import { computed } from 'vue';
import InlineEdit from '@/admin/Pages/partials/InlineEdit.vue';
import {
    blockWidthClass,
    gradientTextStyle,
    textToneClass,
    textToneStyle,
} from '@/lib/blockStyles';

const props = defineProps<{ data: Record<string, unknown> }>();
const emit = defineEmits<{ 'update:data': [Record<string, unknown>] }>();

const level = computed(() => (props.data.level as string) ?? 'h2');

const levelClasses: Record<string, string> = {
    h1: 'text-5xl font-extrabold tracking-tight',
    h2: 'text-4xl font-bold tracking-tight',
    h3: 'text-3xl font-semibold',
    h4: 'text-2xl font-semibold',
};

const alignClass = computed(() =>
    ({ left: 'text-left', center: 'text-center', right: 'text-right' }[(props.data.align as string) ?? 'left'] ?? 'text-left'),
);

const containerClass = computed(() => blockWidthClass(props.data.width));
const gradientStyle = computed(() => gradientTextStyle(props.data.text_gradient));
const toneClass = computed(() =>
    gradientStyle.value ? '' : textToneClass(props.data.text_color),
);
const toneStyle = computed(
    () => gradientStyle.value ?? textToneStyle(props.data.text_color),
);
</script>

<template>
    <div class="mx-auto w-full px-6 py-4" :class="containerClass">
        <InlineEdit
            :tag="level"
            :model-value="(data.text as string) ?? ''"
            placeholder="Enter heading…"
            :class="[
                levelClasses[level] ?? levelClasses.h2,
                alignClass,
                toneClass,
                'block min-w-[2ch]',
            ]"
            :style="toneStyle"
            @update:model-value="emit('update:data', { ...data, text: $event })"
        />
    </div>
</template>
