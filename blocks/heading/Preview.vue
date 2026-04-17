<script setup lang="ts">
import { computed } from 'vue';
import InlineEdit from '@/admin/Pages/partials/InlineEdit.vue';

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
</script>

<template>
    <div class="px-6 py-4 max-w-3xl mx-auto w-full">
        <InlineEdit
            :tag="level"
            :model-value="(data.text as string) ?? ''"
            placeholder="Enter heading…"
            :class="[levelClasses[level] ?? levelClasses.h2, alignClass, 'block min-w-[2ch] text-foreground']"
            @update:model-value="emit('update:data', { ...data, text: $event })"
        />
    </div>
</template>
