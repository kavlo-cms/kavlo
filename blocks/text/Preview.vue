<script setup lang="ts">
import InlineEdit from '@/admin/Pages/partials/InlineEdit.vue';

defineProps<{ data: Record<string, unknown> }>();
const emit = defineEmits<{ 'update:data': [Record<string, unknown>] }>();
</script>

<template>
    <div class="py-8 px-6 max-w-3xl mx-auto w-full">
        <InlineEdit
            tag="div"
            :model-value="(data.content as string) ?? ''"
            placeholder="Start typing..."
            class="block text-base leading-relaxed text-foreground min-h-[1.5em] whitespace-pre-wrap"
            @update:model-value="emit('update:data', { ...data, content: $event })"
        />
    </div>
</template>
