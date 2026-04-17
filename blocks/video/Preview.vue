<script setup lang="ts">
import { computed } from 'vue';
import { Video as VideoIcon } from 'lucide-vue-next';

const props = defineProps<{ data: Record<string, unknown> }>();

const embedUrl = computed(() => {
    const url = (props.data.url as string) ?? '';
    if (!url) return null;
    const yt = url.match(/(?:youtube\.com\/watch\?v=|youtu\.be\/)([a-zA-Z0-9_-]{11})/);
    if (yt) return `https://www.youtube.com/embed/${yt[1]}`;
    const vm = url.match(/vimeo\.com\/(\d+)/);
    if (vm) return `https://player.vimeo.com/video/${vm[1]}`;
    return null;
});
</script>

<template>
    <div class="py-4 px-4 max-w-4xl mx-auto w-full">
        <div v-if="embedUrl" class="aspect-video overflow-hidden rounded-lg">
            <iframe
                :src="embedUrl"
                class="h-full w-full"
                frameborder="0"
                allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture"
                allowfullscreen
            />
        </div>
        <div
            v-else
            class="flex flex-col items-center justify-center gap-2 rounded-lg border-2 border-dashed border-muted-foreground/30 bg-muted/20 py-16"
        >
            <VideoIcon class="h-10 w-10 text-muted-foreground/40" />
            <p class="text-sm text-muted-foreground">No video — add YouTube or Vimeo URL in Block settings</p>
        </div>
        <p v-if="data.caption" class="mt-2 text-center text-sm text-muted-foreground">{{ data.caption }}</p>
    </div>
</template>
