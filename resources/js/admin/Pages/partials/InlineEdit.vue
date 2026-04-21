<script setup lang="ts">
import { onMounted, ref, watch } from 'vue';

const props = withDefaults(
    defineProps<{
        tag?: string;
        modelValue: string;
        placeholder?: string;
    }>(),
    { tag: 'span' },
);

const emit = defineEmits<{ 'update:modelValue': [string] }>();

const el = ref<HTMLElement>();
let focused = false;

onMounted(() => {
    if (el.value) el.value.innerText = props.modelValue ?? '';
});

// Only sync from outside when not actively typing
watch(
    () => props.modelValue,
    (val) => {
        if (el.value && !focused) el.value.innerText = val ?? '';
    },
);

function onFocus() {
    focused = true;
}

function onBlur(e: FocusEvent) {
    focused = false;
    emit('update:modelValue', (e.target as HTMLElement).innerText.trim());
}

function onKeydown(e: KeyboardEvent) {
    // Keep single-line elements single-line
    if (e.key === 'Enter' && props.tag !== 'div' && props.tag !== 'p') {
        e.preventDefault();
    }
}

function onInput(e: Event) {
    emit('update:modelValue', (e.target as HTMLElement).innerText);
}
</script>

<template>
    <component
        :is="tag"
        ref="el"
        contenteditable="true"
        :data-placeholder="placeholder"
        class="cursor-text outline-none empty:before:text-white/30 empty:before:content-[attr(data-placeholder)]"
        @focus="onFocus"
        @blur="onBlur"
        @keydown="onKeydown"
        @input="onInput"
    />
</template>
