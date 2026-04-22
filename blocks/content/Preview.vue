<script setup lang="ts">
import { computed } from 'vue';

const props = defineProps<{
    data: Record<string, unknown>;
    pageContent?: string;
}>();

const contentHtml = computed(() => props.pageContent?.trim() ?? '');
</script>

<template>
    <div class="border-b bg-background px-8 py-6">
        <div
            v-if="contentHtml"
            class="prose prose-sm max-w-none text-foreground"
            v-html="contentHtml"
        />
        <div
            v-else
            class="rounded-md border border-dashed px-4 py-5 text-sm text-muted-foreground"
        >
            Content editor is empty. Switch to the content editor to add content
            for this block.
        </div>
    </div>
</template>
