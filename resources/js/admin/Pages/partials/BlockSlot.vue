<script setup lang="ts">
import { GripVertical, Trash2 } from 'lucide-vue-next';
import { inject } from 'vue';
import type { Ref } from 'vue';
import { VueDraggable } from 'vue-draggable-plus';
import type { Block } from '@/types/blocks';
import { getBlockPreview } from '../config/blockPreviews';
import { blockSchemas } from '../config/blockSchemas';

interface BuilderCtx {
    selectedBlockId: Ref<string | null>;
    selectBlock: (id: string | null) => void;
    updateBlockData: (id: string, data: Record<string, unknown>) => void;
    removeBlock: (id: string) => void;
}

const props = defineProps<{
    blocks: Block[];
    allowedTypes?: string[];
}>();

const emit = defineEmits<{ 'update:blocks': [Block[]] }>();

const { selectedBlockId, selectBlock, updateBlockData, removeBlock } =
    inject<BuilderCtx>('builderCtx')!;

function blockLabel(block: Block): string {
    return blockSchemas[block.type]?.label ?? block.type;
}

function onAdd(evt: { data: Block }) {
    // Enforce allowedTypes rule on drop
    if (props.allowedTypes && !props.allowedTypes.includes(evt.data.type)) {
        // Remove the just-dropped item from this slot
        const updated = props.blocks.filter((b) => b.id !== evt.data.id);
        emit('update:blocks', updated);
    }
}
</script>

<template>
    <VueDraggable
        :model-value="blocks"
        @update:model-value="emit('update:blocks', $event)"
        @add="onAdd"
        :group="{ name: 'page-blocks', pull: false, put: true }"
        :animation="150"
        handle=".drag-handle-slot"
        ghost-class="opacity-30"
        class="min-h-16 w-full"
    >
        <div
            v-for="block in blocks"
            :key="block.id"
            class="group relative"
            @click.stop="selectBlock(block.id)"
        >
            <!-- Inline preview (pointer events allowed for contenteditable) -->
            <component
                :is="getBlockPreview(block.type)"
                :type="block.type"
                :data="block.data"
                @update:data="updateBlockData(block.id, $event)"
            />

            <!-- Selection ring -->
            <div
                class="absolute inset-0 pointer-events-none z-10 transition-[box-shadow]"
                :class="selectedBlockId === block.id
                    ? 'ring-2 ring-inset ring-primary'
                    : 'ring-1 ring-inset ring-transparent group-hover:ring-primary/30'"
            />

            <!-- Floating controls -->
            <div class="absolute left-1/2 top-1 z-20 -translate-x-1/2 flex items-center gap-1 rounded border bg-background/95 px-2 py-0.5 shadow-sm text-xs opacity-0 group-hover:opacity-100 transition-opacity">
                <GripVertical class="drag-handle-slot h-3.5 w-3.5 cursor-grab text-muted-foreground" />
                <span class="font-medium text-muted-foreground">{{ blockLabel(block) }}</span>
                <div class="mx-1 h-3 w-px bg-border" />
                <button
                    class="rounded p-0.5 text-muted-foreground hover:text-destructive transition-colors"
                    title="Remove"
                    @click.stop="removeBlock(block.id)"
                >
                    <Trash2 class="h-3 w-3" />
                </button>
            </div>
        </div>

        <!-- Empty drop target hint -->
        <div
            v-if="blocks.length === 0"
            class="flex items-center justify-center h-16 rounded border border-dashed border-muted-foreground/30 text-xs text-muted-foreground"
        >
            Drop {{ allowedTypes ? allowedTypes.join(' or ') : 'blocks' }} here
        </div>
    </VueDraggable>
</template>
