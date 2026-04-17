<script setup lang="ts">
import { computed } from 'vue';
import BlockSlot from '@/admin/Pages/partials/BlockSlot.vue';
import type { Block } from '@/types/blocks';

const props = defineProps<{ data: Record<string, unknown> }>();
const emit = defineEmits<{ 'update:data': [Record<string, unknown>] }>();

const count = computed(() => Math.max(2, Math.min(4, Number(props.data.count ?? 2))));
const gapClass = computed(() => ({ sm: 'gap-2', md: 'gap-6', lg: 'gap-12' }[(props.data.gap as string) ?? 'md'] ?? 'gap-6'));

function getCol(i: number): Block[] {
    return (props.data[`col_${i}`] as Block[]) ?? [];
}
function updateCol(i: number, blocks: Block[]) {
    emit('update:data', { ...props.data, [`col_${i}`]: blocks });
}
</script>

<template>
    <div class="py-6 px-4">
        <div class="flex" :class="gapClass">
            <div v-for="i in count" :key="i" class="min-w-0 flex-1">
                <BlockSlot :blocks="getCol(i - 1)" @update:blocks="updateCol(i - 1, $event)" />
            </div>
        </div>
    </div>
</template>
