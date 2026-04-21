<script setup lang="ts">
import { computed } from 'vue';
import BlockSlot from '@/admin/Pages/partials/BlockSlot.vue';
import InlineEdit from '@/admin/Pages/partials/InlineEdit.vue';
import type { Block } from '@/types/blocks';

const props = defineProps<{ data: Record<string, unknown> }>();
const emit = defineEmits<{ 'update:data': [Record<string, unknown>] }>();

function update(key: string, value: string) {
    emit('update:data', { ...props.data, [key]: value });
}

const widthMode = computed(
    () => (props.data.width_mode as string) ?? 'full-page-constrained',
);

const sectionClass = computed(() => {
    const base = 'relative overflow-hidden bg-slate-950 py-24';

    if (widthMode.value === 'full-content-width') {
        return `${base} mx-auto max-w-screen-xl`;
    }

    return `${base} left-1/2 right-1/2 w-screen max-w-none -translate-x-1/2`;
});

const contentClass = computed(() => {
    const base = 'relative z-10 text-center';

    if (widthMode.value === 'full-page-constrained') {
        return `${base} mx-auto max-w-screen-xl px-6`;
    }

    if (widthMode.value === 'full-page-unconstrained') {
        return `${base} w-full px-6`;
    }

    return base;
});
</script>

<template>
    <section
        :class="sectionClass"
        :style="
            data.background_image
                ? {
                      backgroundImage: `url(${data.background_image})`,
                      backgroundSize: 'cover',
                      backgroundPosition: 'center',
                  }
                : {}
        "
    >
        <div
            v-if="data.background_image"
            class="absolute inset-0 bg-slate-950/60"
        />
        <div :class="contentClass">
            <InlineEdit
                tag="h1"
                :model-value="(data.headline as string) ?? ''"
                placeholder="Your Headline Here"
                class="mb-6 block min-w-[2ch] bg-gradient-to-r from-white to-slate-500 bg-clip-text text-6xl font-extrabold text-transparent [caret-color:white] md:text-8xl"
                @update:model-value="update('headline', $event)"
            />
            <InlineEdit
                tag="p"
                :model-value="(data.subheadline as string) ?? ''"
                placeholder="Enter a sub-headline..."
                class="mx-auto mb-10 block max-w-2xl min-w-[2ch] text-xl text-slate-400"
                @update:model-value="update('subheadline', $event)"
            />
            <BlockSlot
                :blocks="(data.children as Block[]) ?? []"
                @update:blocks="
                    emit('update:data', { ...data, children: $event })
                "
            />
        </div>
        <div
            class="pointer-events-none absolute top-0 left-1/2 h-full w-full -translate-x-1/2 bg-[radial-gradient(circle_at_center,_var(--tw-gradient-stops))] from-sky-500/10 via-transparent to-transparent blur-3xl"
        />
    </section>
</template>
