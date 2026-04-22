<script setup lang="ts">
import { computed } from 'vue';
import InlineEdit from '@/admin/Pages/partials/InlineEdit.vue';
import {
    blockWidthClass,
    subtleTextToneClass,
    subtleTextToneStyle,
    textToneClass,
    textToneStyle,
} from '@/lib/blockStyles';

const props = defineProps<{ data: Record<string, unknown> }>();
const emit = defineEmits<{ 'update:data': [Record<string, unknown>] }>();

const containerClass = computed(() => blockWidthClass(props.data.width));
const textClass = computed(() => textToneClass(props.data.text_color));
const metaClass = computed(() => subtleTextToneClass(props.data.text_color));
const textStyle = computed(() => textToneStyle(props.data.text_color));
const metaStyle = computed(() => subtleTextToneStyle(props.data.text_color));
</script>

<template>
    <div class="mx-auto w-full px-6 py-8" :class="containerClass">
        <blockquote class="border-l-4 border-primary pl-6">
            <InlineEdit
                tag="p"
                :model-value="(data.text as string) ?? ''"
                placeholder="Enter quote text…"
                :class="['block min-h-[1.5em] text-xl font-medium italic', textClass]"
                :style="textStyle"
                @update:model-value="emit('update:data', { ...data, text: $event })"
            />
            <footer class="mt-3 flex flex-wrap items-baseline gap-1">
                <InlineEdit
                    tag="span"
                    :model-value="(data.author as string) ?? ''"
                    placeholder="Author name"
                    :class="['inline text-sm font-semibold', textClass]"
                    :style="textStyle"
                    @update:model-value="emit('update:data', { ...data, author: $event })"
                />
                <span :class="['text-sm', metaClass]" :style="metaStyle">—</span>
                <InlineEdit
                    tag="span"
                    :model-value="(data.role as string) ?? ''"
                    placeholder="Role or title"
                    :class="['inline text-sm', metaClass]"
                    :style="metaStyle"
                    @update:model-value="emit('update:data', { ...data, role: $event })"
                />
            </footer>
        </blockquote>
    </div>
</template>
