<script setup lang="ts">
import { ChevronDown, ChevronUp, GripVertical, Plus, Trash2 } from 'lucide-vue-next';
import { ref } from 'vue';
import { VueDraggable } from 'vue-draggable-plus';
import { useBlockSchemas } from '@/composables/useBlockSchemas';
import type { AvailableBlock } from '@/composables/useBlockSchemas';
import type { Block } from '@/types/blocks';
import { getBlockPreview } from '../config/blockPreviews';
type Device = 'desktop' | 'tablet' | 'mobile';

interface ThemeConfig {
    canvas?: { class?: string; font?: string | null };
}

const props = defineProps<{
    blocks: Block[];
    title: string;
    availableBlocks: AvailableBlock[];
    selectedBlockId: string | null;
    device: Device;
    themeConfig: ThemeConfig;
    entityLabel?: string;
    titlePlaceholder?: string;
    allowFileDrop?: boolean;
}>();

const emit = defineEmits<{
    'update:blocks': [blocks: Block[]];
    'update:blockData': [id: string, data: Record<string, unknown>];
    'update:title': [title: string];
    select: [id: string | null];
    remove: [id: string];
    insert: [index: number, type: string];
    'move-up': [id: string];
    'move-down': [id: string];
}>();

const { getSchema } = useBlockSchemas(() => props.availableBlocks);

const inserterOpenAt = ref<number | null>(null);
const canvasDragOver = ref(false);
const canvasUploading = ref(false);

const deviceMaxWidths: Record<Device, string | null> = {
    desktop: null,
    tablet: '768px',
    mobile: '390px',
};

function blockLabel(block: Block): string {
    return getSchema(block.type)?.label ?? block.type;
}

function openInserter(index: number) {
    inserterOpenAt.value = inserterOpenAt.value === index ? null : index;
}

function addBlock(index: number, type: string) {
    inserterOpenAt.value = null;
    emit('insert', index, type);
}

// ── Canvas drag-to-upload (files from desktop) ────────────────────────────────
function onCanvasDragOver(e: DragEvent) {
    if (props.allowFileDrop === false) {
        return;
    }

    if (e.dataTransfer?.types.includes('Files')) {
        e.preventDefault();
        canvasDragOver.value = true;
    }
}
function onCanvasDragLeave(e: DragEvent) {
    if (props.allowFileDrop === false) {
        return;
    }

    // Only clear when leaving the outer container
    if (!(e.currentTarget as HTMLElement).contains(e.relatedTarget as Node)) {
        canvasDragOver.value = false;
    }
}
async function onCanvasDrop(e: DragEvent) {
    if (props.allowFileDrop === false) {
        return;
    }

    if (!e.dataTransfer?.files.length) {
        return;
    }

    // Only handle file drops (not block drags from palette)
    const files = Array.from(e.dataTransfer.files).filter((f) => f.type.startsWith('image/'));

    if (!files.length) {
        canvasDragOver.value = false;

        return;
    }

    e.preventDefault();
    canvasDragOver.value = false;
    canvasUploading.value = true;

    function getCsrfToken(): string {
        return (document.cookie.match(/XSRF-TOKEN=([^;]+)/) ?? [])[1]
            ? decodeURIComponent((document.cookie.match(/XSRF-TOKEN=([^;]+)/) ?? [])[1])
            : (document.querySelector('meta[name="csrf-token"]') as HTMLMetaElement)?.content ?? '';
    }

    try {
        for (const file of files) {
            const fd = new FormData();
            fd.append('file', file);
            fd.append('folder', 'uploads');
            const res = await fetch('/admin/media/upload', {
                method: 'POST',
                headers: { 'X-XSRF-TOKEN': getCsrfToken() },
                body: fd,
            });

            if (res.ok) {
                const media = await res.json();
                // Insert an image block at the end with the uploaded media
                const newBlock: Block = {
                    id: crypto.randomUUID(),
                    type: 'image',
                    data: { src: media.url, alt: media.alt || media.name },
                    order: props.blocks.length,
                };
                emit('update:blocks', [...props.blocks, newBlock]);
            }
        }
    } finally {
        canvasUploading.value = false;
    }
}
</script>

<template>
    <!-- Outer scrollable viewport -->
    <div
        class="relative flex min-w-0 flex-1 overflow-y-auto transition-colors duration-300"
        :class="device !== 'desktop' ? 'bg-muted/20 py-8' : (themeConfig?.canvas?.class ?? 'bg-background')"
        @click="emit('select', null); inserterOpenAt = null"
        @dragover="onCanvasDragOver"
        @dragleave="onCanvasDragLeave"
        @drop="onCanvasDrop"
    >
        <!-- File drop overlay -->
        <div
            v-if="canvasDragOver || canvasUploading"
            class="pointer-events-none absolute inset-0 z-50 flex flex-col items-center justify-center gap-3 bg-primary/10 ring-2 ring-inset ring-primary"
        >
            <div class="rounded-xl bg-background/90 px-6 py-4 text-center shadow-lg">
                <p class="text-sm font-semibold text-primary">
                    {{ canvasUploading ? 'Uploading…' : 'Drop image to upload & insert' }}
                </p>
            </div>
        </div>
        <!-- Backdrop to close inserter dropdown -->
        <div
            v-if="inserterOpenAt !== null"
            class="fixed inset-0 z-40"
            @click.stop="inserterOpenAt = null"
        />

        <!-- Device-framed page canvas -->
        <div
            class="mx-auto w-full transition-all duration-300"
            :class="[
                device !== 'desktop' ? 'rounded-xl shadow-2xl ring-1 ring-border' : '',
                themeConfig?.canvas?.class ?? 'bg-background',
            ]"
            :style="deviceMaxWidths[device] ? { maxWidth: deviceMaxWidths[device] } : {}"
        >
            <!-- Inline page title -->
            <div class="mx-auto w-full max-w-2xl px-8 pb-4 pt-10" @click.stop>
                <input
                    type="text"
                    :value="title"
                    :placeholder="titlePlaceholder ?? 'Add title'"
                    class="w-full border-none bg-transparent text-3xl font-bold placeholder:text-current/30 focus:outline-none focus:ring-0"
                    @input="emit('update:title', ($event.target as HTMLInputElement).value)"
                />
            </div>

            <!-- Empty state message -->
            <div
                v-if="blocks.length === 0"
                class="flex flex-col items-center justify-center gap-2 py-14"
                @click.stop
            >
                <p class="text-sm text-muted-foreground">Your {{ entityLabel ?? 'page' }} has no blocks yet</p>
                <p class="text-xs text-muted-foreground/60">Drag a block from the sidebar or click Add block</p>
                <div class="relative mt-2">
                    <button
                        class="flex items-center gap-2 rounded-md border bg-background px-4 py-2 text-sm shadow-sm transition-colors hover:bg-accent"
                        @click.stop="openInserter(0)"
                    >
                        <Plus class="h-4 w-4" />
                        Add block
                    </button>
                    <div
                        v-if="inserterOpenAt === 0"
                        class="absolute left-1/2 top-full z-50 mt-1 min-w-[10rem] -translate-x-1/2 rounded-lg border bg-background p-1 shadow-lg"
                        @click.stop
                    >
                        <button
                            v-for="b in availableBlocks"
                            :key="b.type"
                            class="flex w-full items-center gap-2 rounded px-3 py-1.5 text-sm transition-colors hover:bg-accent"
                            @click.stop="addBlock(0, b.type)"
                        >
                            {{ b.label }}
                        </button>
                    </div>
                </div>
            </div>

            <!-- Inserter before first block -->
            <div v-if="blocks.length > 0" class="relative flex items-center px-4 py-1" @click.stop>
                <div class="group/ins flex w-full items-center gap-1">
                    <div class="h-px flex-1 bg-transparent transition-colors group-hover/ins:bg-primary/40" />
                    <button
                        class="flex h-5 w-5 items-center justify-center rounded-full border border-transparent bg-transparent text-transparent transition-all group-hover/ins:border-primary group-hover/ins:bg-primary/10 group-hover/ins:text-primary"
                        title="Insert block here"
                        @click.stop="openInserter(0)"
                    >
                        <Plus class="h-3 w-3" />
                    </button>
                    <div class="h-px flex-1 bg-transparent transition-colors group-hover/ins:bg-primary/40" />
                </div>
                <div
                    v-if="inserterOpenAt === 0"
                    class="absolute left-1/2 top-full z-50 mt-1 min-w-[10rem] -translate-x-1/2 rounded-lg border bg-background p-1 shadow-lg"
                    @click.stop
                >
                    <button
                        v-for="b in availableBlocks"
                        :key="b.type"
                        class="flex w-full items-center gap-2 rounded px-3 py-1.5 text-sm transition-colors hover:bg-accent"
                        @click.stop="addBlock(0, b.type)"
                    >
                        {{ b.label }}
                    </button>
                </div>
            </div>

            <!-- Block list — always rendered so it accepts drops even when empty -->
            <VueDraggable
                :model-value="blocks"
                :group="{ name: 'page-blocks', pull: false, put: true }"
                :animation="150"
                handle=".drag-handle"
                ghost-class="opacity-30"
                class="transition-all duration-200"
                :class="blocks.length === 0
                    ? 'mx-8 mb-8 min-h-40 rounded-xl border-2 border-dashed border-primary/20 flex items-center justify-center text-xs text-muted-foreground/40 select-none'
                    : ''"
                @update:model-value="emit('update:blocks', $event)"
            >
                <template v-if="blocks.length === 0">
                    <span class="pointer-events-none">Drop here</span>
                </template>
                    <div
                        v-for="(block, index) in blocks"
                        :key="block.id"
                    >
                        <!-- Block wrapper -->
                        <div
                            class="group relative cursor-default"
                            :class="selectedBlockId === block.id
                                ? 'ring-2 ring-inset ring-primary'
                                : 'ring-1 ring-inset ring-transparent hover:ring-primary/30'"
                            @click.stop="emit('select', block.id); inserterOpenAt = null"
                        >
                            <component
                                :is="getBlockPreview(block.type)"
                                :type="block.type"
                                :data="block.data"
                                @update:data="emit('update:blockData', block.id, $event)"
                            />

                            <!-- Floating toolbar -->
                            <div
                                class="absolute left-1/2 top-2 z-20 -translate-x-1/2 flex items-center gap-0.5 rounded-md border bg-background/95 px-1.5 py-1 shadow transition-opacity"
                                :class="selectedBlockId === block.id
                                    ? 'opacity-100 pointer-events-auto'
                                    : 'opacity-0 pointer-events-none group-hover:opacity-100 group-hover:pointer-events-auto'"
                            >
                                <GripVertical class="drag-handle h-4 w-4 cursor-grab text-muted-foreground" />
                                <span class="px-1 text-xs font-medium text-muted-foreground">{{ blockLabel(block) }}</span>
                                <div class="mx-0.5 h-3 w-px bg-border" />
                                <button
                                    class="rounded p-0.5 text-muted-foreground transition-colors hover:text-foreground disabled:cursor-not-allowed disabled:opacity-30"
                                    title="Move up"
                                    :disabled="index === 0"
                                    @click.stop="emit('move-up', block.id)"
                                >
                                    <ChevronUp class="h-3.5 w-3.5" />
                                </button>
                                <button
                                    class="rounded p-0.5 text-muted-foreground transition-colors hover:text-foreground disabled:cursor-not-allowed disabled:opacity-30"
                                    title="Move down"
                                    :disabled="index === blocks.length - 1"
                                    @click.stop="emit('move-down', block.id)"
                                >
                                    <ChevronDown class="h-3.5 w-3.5" />
                                </button>
                                <div class="mx-0.5 h-3 w-px bg-border" />
                                <button
                                    class="rounded p-0.5 text-muted-foreground transition-colors hover:text-destructive"
                                    title="Remove block"
                                    @click.stop="emit('remove', block.id)"
                                >
                                    <Trash2 class="h-3.5 w-3.5" />
                                </button>
                            </div>
                        </div>

                        <!-- Inserter after this block -->
                        <div class="relative flex items-center px-4 py-1" @click.stop>
                            <div class="group/ins flex w-full items-center gap-1">
                                <div class="h-px flex-1 bg-transparent transition-colors group-hover/ins:bg-primary/40" />
                                <button
                                    class="flex h-5 w-5 items-center justify-center rounded-full border border-transparent bg-transparent text-transparent transition-all group-hover/ins:border-primary group-hover/ins:bg-primary/10 group-hover/ins:text-primary"
                                    title="Insert block here"
                                    @click.stop="openInserter(index + 1)"
                                >
                                    <Plus class="h-3 w-3" />
                                </button>
                                <div class="h-px flex-1 bg-transparent transition-colors group-hover/ins:bg-primary/40" />
                            </div>
                            <div
                                v-if="inserterOpenAt === index + 1"
                                class="absolute left-1/2 top-full z-50 mt-1 min-w-[10rem] -translate-x-1/2 rounded-lg border bg-background p-1 shadow-lg"
                                @click.stop
                            >
                                <button
                                    v-for="b in availableBlocks"
                                    :key="b.type"
                                    class="flex w-full items-center gap-2 rounded px-3 py-1.5 text-sm transition-colors hover:bg-accent"
                                    @click.stop="addBlock(index + 1, b.type)"
                                >
                                    {{ b.label }}
                                </button>
                            </div>
                        </div>
                    </div>
                </VueDraggable>
        </div>
    </div>
</template>
