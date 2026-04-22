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
    if (
        evt.data.type === 'content' ||
        (props.allowedTypes && !props.allowedTypes.includes(evt.data.type))
    ) {
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
                class="pointer-events-none absolute inset-0 z-10 transition-[box-shadow]"
                :class="
                    selectedBlockId === block.id
                        ? 'ring-2 ring-primary ring-inset'
                        : 'ring-1 ring-transparent ring-inset group-hover:ring-primary/30'
                "
            />

            <!-- Floating controls -->
            <div
                class="absolute top-1 left-1/2 z-20 flex -translate-x-1/2 items-center gap-1 rounded border bg-background/95 px-2 py-0.5 text-xs opacity-0 shadow-sm transition-opacity group-hover:opacity-100"
            >
                <GripVertical
                    class="drag-handle-slot h-3.5 w-3.5 cursor-grab text-muted-foreground"
                />
                <span class="font-medium text-muted-foreground">{{
                    blockLabel(block)
                }}</span>
                <div class="mx-1 h-3 w-px bg-border" />
                <button
                    class="rounded p-0.5 text-muted-foreground transition-colors hover:text-destructive"
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
            class="flex h-16 items-center justify-center rounded border border-dashed border-muted-foreground/30 text-xs text-muted-foreground"
        >
            Drop {{ allowedTypes ? allowedTypes.join(' or ') : 'blocks' }} here
        </div>
    </VueDraggable>
</template>
