<script setup lang="ts">
import InlineEdit from '@/admin/Pages/partials/InlineEdit.vue';

defineProps<{ data: Record<string, unknown> }>();
const emit = defineEmits<{ 'update:data': [Record<string, unknown>] }>();
</script>

<template>
    <div class="py-8 px-6 max-w-3xl mx-auto w-full">
        <blockquote class="border-l-4 border-primary pl-6">
            <InlineEdit
                tag="p"
                :model-value="(data.text as string) ?? ''"
                placeholder="Enter quote text…"
                class="block min-h-[1.5em] text-xl font-medium italic text-foreground"
                @update:model-value="emit('update:data', { ...data, text: $event })"
            />
            <footer class="mt-3 flex flex-wrap items-baseline gap-1">
                <InlineEdit
                    tag="span"
                    :model-value="(data.author as string) ?? ''"
                    placeholder="Author name"
                    class="inline text-sm font-semibold text-foreground"
                    @update:model-value="emit('update:data', { ...data, author: $event })"
                />
                <span class="text-muted-foreground text-sm">—</span>
                <InlineEdit
                    tag="span"
                    :model-value="(data.role as string) ?? ''"
                    placeholder="Role or title"
                    class="inline text-sm text-muted-foreground"
                    @update:model-value="emit('update:data', { ...data, role: $event })"
                />
            </footer>
        </blockquote>
    </div>
</template>
