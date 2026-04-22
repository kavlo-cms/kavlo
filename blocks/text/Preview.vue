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

const containerClass = computed(() => blockWidthClass(props.data.width));
const gradientStyle = computed(() => gradientTextStyle(props.data.text_gradient));
const textClass = computed(() =>
    gradientStyle.value ? '' : textToneClass(props.data.text_color),
);
const textStyle = computed(
    () => gradientStyle.value ?? textToneStyle(props.data.text_color),
);
</script>

<template>
    <div class="mx-auto w-full px-6 py-8" :class="containerClass">
        <InlineEdit
            tag="div"
            :model-value="(data.content as string) ?? ''"
            placeholder="Start typing..."
            :class="[
                'block min-h-[1.5em] whitespace-pre-wrap text-base leading-relaxed',
                textClass,
            ]"
            :style="textStyle"
            @update:model-value="emit('update:data', { ...data, content: $event })"
        />
    </div>
</template>
