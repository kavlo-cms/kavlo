<script setup lang="ts">
import BlockSlot from '@/admin/Pages/partials/BlockSlot.vue';
import InlineEdit from '@/admin/Pages/partials/InlineEdit.vue';
import type { Block } from '@/types/blocks';

const props = defineProps<{ data: Record<string, unknown> }>();
const emit = defineEmits<{ 'update:data': [Record<string, unknown>] }>();

function update(key: string, value: string) {
    emit('update:data', { ...props.data, [key]: value });
}
</script>

<template>
    <section
        class="relative py-24 overflow-hidden bg-slate-950"
        :style="data.background_image ? { backgroundImage: `url(${data.background_image})`, backgroundSize: 'cover', backgroundPosition: 'center' } : {}"
    >
        <div v-if="data.background_image" class="absolute inset-0 bg-slate-950/60" />
        <div class="container mx-auto px-6 relative z-10 text-center">
            <InlineEdit
                tag="h1"
                :model-value="(data.headline as string) ?? ''"
                placeholder="Your Headline Here"
                class="block text-6xl md:text-8xl font-extrabold text-transparent bg-clip-text bg-gradient-to-r from-white to-slate-500 mb-6 min-w-[2ch] [caret-color:white]"
                @update:model-value="update('headline', $event)"
            />
            <InlineEdit
                tag="p"
                :model-value="(data.subheadline as string) ?? ''"
                placeholder="Enter a sub-headline..."
                class="block text-xl text-slate-400 max-w-2xl mx-auto mb-10 min-w-[2ch]"
                @update:model-value="update('subheadline', $event)"
            />
            <BlockSlot
                :blocks="(data.children as Block[]) ?? []"
                @update:blocks="emit('update:data', { ...data, children: $event })"
            />
        </div>
        <div class="absolute top-0 left-1/2 -translate-x-1/2 w-full h-full bg-[radial-gradient(circle_at_center,_var(--tw-gradient-stops))] from-sky-500/10 via-transparent to-transparent blur-3xl pointer-events-none" />
    </section>
</template>
