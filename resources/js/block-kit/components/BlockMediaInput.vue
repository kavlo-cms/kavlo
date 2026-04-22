<script setup lang="ts">
import MediaPicker from '@/components/MediaPicker.vue';
import type { MediaItem } from '@/components/MediaPicker.vue';

withDefaults(
    defineProps<{
        modelValue?: string | null;
    }>(),
    {
        modelValue: '',
    },
);

const emit = defineEmits<{
    'update:modelValue': [value: string];
    select: [item: MediaItem];
}>();

const open = defineModel<boolean>('open', { default: false });

function onSelect(item: MediaItem) {
    emit('update:modelValue', item.url);
    emit('select', item);
}
</script>

<template>
    <div class="flex flex-col gap-1.5">
        <div
            v-if="modelValue"
            class="group relative overflow-hidden rounded-lg border"
        >
            <img :src="modelValue" class="h-28 w-full object-cover" alt="" />
            <button
                type="button"
                class="absolute inset-0 flex items-center justify-center bg-black/40 opacity-0 transition-opacity group-hover:opacity-100"
                @click="open = true"
            >
                <span
                    class="rounded bg-white/90 px-2 py-1 text-xs font-medium text-gray-900"
                    >Change</span
                >
            </button>
        </div>
        <button
            v-else
            type="button"
            class="flex h-20 w-full flex-col items-center justify-center gap-1 rounded-lg border-2 border-dashed border-muted-foreground/30 text-muted-foreground transition-colors hover:border-primary/50 hover:bg-primary/5"
            @click="open = true"
        >
            <span class="text-xs">Choose from Library</span>
        </button>

        <MediaPicker v-model:open="open" @select="onSelect" />
    </div>
</template>
