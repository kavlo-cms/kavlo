<script setup lang="ts">
import { Image as ImageIcon } from 'lucide-vue-next';
import { computed, ref } from 'vue';
import MediaPicker, { type MediaItem } from '@/components/MediaPicker.vue';

const props = defineProps<{ data: Record<string, unknown> }>();
const emit = defineEmits<{ 'update:data': [data: Record<string, unknown>] }>();

const pickerOpen = ref(false);

const containerClass = computed(() =>
    ({
        full: 'w-full',
        wide: 'max-w-4xl mx-auto',
        medium: 'max-w-2xl mx-auto',
        small: 'max-w-sm mx-auto',
    }[(props.data.width as string) ?? 'full'] ?? 'w-full'),
);

function onMediaSelect(item: MediaItem) {
    emit('update:data', { ...props.data, src: item.url, alt: item.alt || item.name });
}
</script>

<template>
    <div class="py-4 px-4">
        <figure :class="containerClass">
            <div v-if="data.src" class="group relative overflow-hidden rounded-lg">
                <img
                    :src="(data.src as string)"
                    :alt="(data.alt as string) ?? ''"
                    class="h-auto w-full object-cover"
                />
                <button
                    class="absolute inset-0 flex items-center justify-center bg-black/40 opacity-0 transition-opacity group-hover:opacity-100"
                    @click="pickerOpen = true"
                >
                    <span class="rounded-md bg-white/90 px-3 py-1.5 text-xs font-medium text-gray-900">Change image</span>
                </button>
            </div>
            <button
                v-else
                class="flex w-full flex-col items-center justify-center gap-2 rounded-lg border-2 border-dashed border-muted-foreground/30 bg-muted/20 py-16 transition-colors hover:border-primary/50 hover:bg-primary/5"
                @click="pickerOpen = true"
            >
                <ImageIcon class="h-10 w-10 text-muted-foreground/40" />
                <p class="text-sm text-muted-foreground">Click to choose from Media Library</p>
            </button>
            <figcaption v-if="data.caption" class="mt-2 text-center text-sm text-muted-foreground">
                {{ data.caption }}
            </figcaption>
        </figure>

        <MediaPicker
            :open="pickerOpen"
            @update:open="pickerOpen = $event"
            @select="onMediaSelect"
        />
    </div>
</template>

