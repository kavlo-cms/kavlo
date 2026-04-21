<script setup lang="ts">
import { router, useForm } from '@inertiajs/vue3';
import { Link } from '@inertiajs/vue3';
import { useLocalStorage } from '@vueuse/core';
import { ArrowLeft, Loader2, Mail, PanelRight, Trash2 } from 'lucide-vue-next';
import { computed, provide, ref, watch } from 'vue';
import { Button } from '@/components/ui/button';
import type { AvailableBlock } from '@/composables/useBlockSchemas';
import BuilderLayout from '@/layouts/BuilderLayout.vue';
import type { Block } from '@/types/blocks';
import BlockCanvas from '../Pages/partials/BlockCanvas.vue';
import BlockPalette from '../Pages/partials/BlockPalette.vue';
import TemplateSettingsPanel from './partials/TemplateSettingsPanel.vue';

interface VariableDefinition {
    key: string;
    label: string;
    example?: string;
}

interface TemplateContext {
    key: string;
    label: string;
    description?: string;
    source?: string;
    variables?: VariableDefinition[];
}

interface TemplateData {
    id?: number;
    name: string;
    slug: string;
    description: string;
    context_key: string;
    subject: string;
    blocks: Block[];
}

const props = defineProps<{
    template: TemplateData | null;
    availableBlocks: AvailableBlock[];
    availableContexts: TemplateContext[];
}>();

const isEditing = computed(() => !!props.template?.id);
const selectedBlockId = ref<string | null>(null);
const sidebarOpen = ref(true);
const leftWidth = useLocalStorage('email-template-builder-left-width', 208);
const rightWidth = useLocalStorage('email-template-builder-right-width', 340);

const form = useForm({
    name: props.template?.name ?? '',
    slug: props.template?.slug ?? '',
    description: props.template?.description ?? '',
    context_key: props.template?.context_key ?? 'generic',
    subject: props.template?.subject ?? '',
    blocks: (props.template?.blocks ?? []) as Block[],
});

const slugTouched = ref(isEditing.value);

function slugify(value: string) {
    return value
        .toLowerCase()
        .trim()
        .replace(/[^a-z0-9\s-]/g, '')
        .replace(/\s+/g, '-')
        .replace(/-+/g, '-');
}

watch(() => form.name, (value) => {
    if (!slugTouched.value) {
        form.slug = slugify(value);
    }
});

function startResize(side: 'left' | 'right', e: MouseEvent) {
    const startX = e.clientX;
    const startWidth = side === 'left' ? leftWidth.value : rightWidth.value;

    document.body.style.cursor = 'col-resize';
    document.body.style.userSelect = 'none';

    function onMove(event: MouseEvent) {
        const delta = side === 'left' ? event.clientX - startX : startX - event.clientX;
        const newWidth = Math.max(180, Math.min(520, startWidth + delta));

        if (side === 'left') {
            leftWidth.value = newWidth;
        } else {
            rightWidth.value = newWidth;
        }
    }

    function onUp() {
        document.body.style.cursor = '';
        document.body.style.userSelect = '';
        document.removeEventListener('mousemove', onMove);
        document.removeEventListener('mouseup', onUp);
    }

    document.addEventListener('mousemove', onMove);
    document.addEventListener('mouseup', onUp);
}

function updateBlocksById(blocks: Block[], id: string, newData: Record<string, unknown>): Block[] {
    return blocks.map((block) => {
        if (block.id === id) {
            return { ...block, data: newData };
        }

        if (block.type !== 'columns' || typeof block.data !== 'object' || block.data === null) {
            return block;
        }

        const data = { ...block.data } as Record<string, unknown>;
        let changed = false;

        for (const key of Object.keys(data)) {
            if (!/^col_\d+$/.test(key) || !Array.isArray(data[key])) {
                continue;
            }

            const next = updateBlocksById(data[key] as Block[], id, newData);

            if (next !== data[key]) {
                data[key] = next;
                changed = true;
            }
        }

        return changed ? { ...block, data } : block;
    });
}

function removeBlocksById(blocks: Block[], id: string): Block[] {
    return blocks
        .filter((block) => block.id !== id)
        .map((block) => {
            if (block.type !== 'columns' || typeof block.data !== 'object' || block.data === null) {
                return block;
            }

            const data = { ...block.data } as Record<string, unknown>;
            let changed = false;

            for (const key of Object.keys(data)) {
                if (!/^col_\d+$/.test(key) || !Array.isArray(data[key])) {
                    continue;
                }

                const next = removeBlocksById(data[key] as Block[], id);

                if (next !== data[key]) {
                    data[key] = next;
                    changed = true;
                }
            }

            return changed ? { ...block, data } : block;
        });
}

function findBlockById(blocks: Block[], id: string): Block | null {
    for (const block of blocks) {
        if (block.id === id) {
            return block;
        }

        if (block.type !== 'columns' || typeof block.data !== 'object' || block.data === null) {
            continue;
        }

        for (const [key, value] of Object.entries(block.data as Record<string, unknown>)) {
            if (!/^col_\d+$/.test(key) || !Array.isArray(value)) {
                continue;
            }

            const found = findBlockById(value as Block[], id);

            if (found) {
                return found;
            }
        }
    }

    return null;
}

function removeBlock(id: string) {
    if (selectedBlockId.value === id) {
        selectedBlockId.value = null;
    }

    form.blocks = removeBlocksById(form.blocks, id);
}

function updateBlockData(id: string, data: Record<string, unknown>) {
    form.blocks = updateBlocksById(form.blocks, id, data);
}

function insertBlock(index: number, type: string) {
    const definition = props.availableBlocks.find((block) => block.type === type);
    const newBlock: Block = {
        id: crypto.randomUUID(),
        type,
        data: { ...(definition?.defaultData ?? {}) },
        order: index,
    };

    const next = [...form.blocks];
    next.splice(index, 0, newBlock);
    form.blocks = next;
    selectedBlockId.value = newBlock.id;
}

function moveBlock(id: string, direction: 'up' | 'down') {
    const index = form.blocks.findIndex((block) => block.id === id);

    if (index === -1) {
        return;
    }

    const next = [...form.blocks];

    if (direction === 'up' && index > 0) {
        [next[index - 1], next[index]] = [next[index], next[index - 1]];
    } else if (direction === 'down' && index < next.length - 1) {
        [next[index + 1], next[index]] = [next[index], next[index + 1]];
    }

    form.blocks = next;
}

const selectedBlock = computed(() => findBlockById(form.blocks, selectedBlockId.value ?? '') ?? null);

provide('builderCtx', {
    selectedBlockId,
    selectBlock: (id: string | null) => {
        selectedBlockId.value = id;
    },
    updateBlockData,
    removeBlock,
});

function updateTemplateField(field: keyof typeof form, value: unknown) {
    (form as unknown as Record<string, unknown>)[field] = value;
}

function save() {
    if (isEditing.value) {
        form.put(`/admin/email-templates/${props.template!.id}`, { preserveScroll: true });
    } else {
        form.post('/admin/email-templates', { preserveScroll: true });
    }
}

function destroyTemplate() {
    if (!props.template || !confirm(`Delete "${props.template.name}"?`)) {
        return;
    }

    router.delete(`/admin/email-templates/${props.template.id}`);
}
</script>

<template>
    <BuilderLayout>
        <template #header>
            <Link
                href="/admin/email-templates"
                class="flex h-8 items-center gap-1.5 rounded-md px-2 text-sm text-muted-foreground transition-colors hover:bg-accent hover:text-foreground"
            >
                <ArrowLeft class="h-3.5 w-3.5" />
                Structure / Email Templates
            </Link>

            <div class="mx-3 h-4 w-px bg-border" />

            <div class="flex min-w-0 flex-1 items-center gap-2">
                <div class="min-w-0 flex-1">
                    <p class="truncate text-sm font-medium">{{ form.name || 'Untitled template' }}</p>
                    <p class="truncate text-xs font-mono text-muted-foreground">{{ form.context_key }}</p>
                </div>
                <p v-if="form.errors.blocks" class="hidden text-xs text-destructive sm:block">{{ form.errors.blocks }}</p>
            </div>

            <Button
                v-if="isEditing"
                size="sm"
                variant="ghost"
                class="h-7 gap-1.5 text-xs text-muted-foreground hover:text-destructive"
                title="Delete template"
                @click="destroyTemplate"
            >
                <Trash2 class="h-3.5 w-3.5" />
            </Button>

            <Button
                size="sm"
                class="h-7 gap-1.5 text-xs"
                :disabled="form.processing"
                @click="save"
            >
                <Loader2 v-if="form.processing" class="h-3.5 w-3.5 animate-spin" />
                Save
            </Button>

            <div class="mx-1" />

            <button
                class="flex h-8 w-8 items-center justify-center rounded-md transition-colors"
                :class="sidebarOpen ? 'bg-accent text-foreground' : 'text-muted-foreground hover:bg-accent hover:text-foreground'"
                title="Toggle settings panel"
                @click="sidebarOpen = !sidebarOpen"
            >
                <PanelRight class="h-4 w-4" />
            </button>
        </template>

        <template #default>
            <div class="flex min-h-0 flex-1 overflow-hidden">
                <div class="hidden h-full shrink-0 md:flex" :style="{ width: `${leftWidth}px` }">
                    <BlockPalette :available-blocks="availableBlocks" />
                </div>

                <div class="hidden w-1 shrink-0 cursor-col-resize bg-border/60 transition hover:bg-primary/40 md:block" @mousedown="startResize('left', $event)" />

                <div class="min-w-0 flex-1 overflow-hidden">
                    <BlockCanvas
                        :blocks="form.blocks"
                        :title="form.subject"
                        :available-blocks="availableBlocks"
                        :selected-block-id="selectedBlockId"
                        device="desktop"
                        :theme-config="{ canvas: { class: 'bg-muted/20' } }"
                        entity-label="email"
                        title-placeholder="Email subject"
                        :allow-file-drop="false"
                        @update:blocks="form.blocks = $event"
                        @update:block-data="updateBlockData"
                        @update:title="updateTemplateField('subject', $event)"
                        @select="selectedBlockId = $event"
                        @remove="removeBlock"
                        @insert="insertBlock"
                        @move-up="moveBlock($event, 'up')"
                        @move-down="moveBlock($event, 'down')"
                    />
                </div>

                <template v-if="sidebarOpen">
                    <div class="hidden w-1 shrink-0 cursor-col-resize bg-border/60 transition hover:bg-primary/40 lg:block" @mousedown="startResize('right', $event)" />

                    <div class="hidden h-full shrink-0 lg:flex" :style="{ width: `${rightWidth}px` }">
                        <TemplateSettingsPanel
                            :form="form"
                            :selected-block="selectedBlock"
                            :available-blocks="availableBlocks"
                            :available-contexts="availableContexts"
                            @update:form="updateTemplateField"
                            @update:block-data="updateBlockData"
                            @touch-slug="slugTouched = true"
                            @deselect="selectedBlockId = null"
                        />
                    </div>
                </template>
            </div>
        </template>
    </BuilderLayout>
</template>
