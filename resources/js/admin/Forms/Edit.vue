<script setup lang="ts">
import { router, useForm } from '@inertiajs/vue3';
import { Link } from '@inertiajs/vue3';
import { useLocalStorage } from '@vueuse/core';
import {
    ArrowLeft,
    ClipboardList,
    Loader2,
    PanelRight,
    Trash2,
} from 'lucide-vue-next';
import { computed, provide, ref, watch } from 'vue';
import { Button } from '@/components/ui/button';
import type { AvailableBlock } from '@/composables/useBlockSchemas';
import BuilderLayout from '@/layouts/BuilderLayout.vue';
import admin from '@/routes/admin';
import type { Block } from '@/types/blocks';
import BlockCanvas from '../Pages/partials/BlockCanvas.vue';
import BlockPalette from '../Pages/partials/BlockPalette.vue';
import FormSettingsPanel from './partials/FormSettingsPanel.vue';

interface FormActionField {
    key: string;
    label: string;
    type: 'text' | 'textarea' | 'email' | 'url' | 'select' | 'toggle';
    placeholder?: string;
    options?: { value: string; label: string }[];
}

interface FormAction {
    key: string;
    label: string;
    description?: string;
    source?: string;
    fields?: FormActionField[];
}

type FormActionConfigValue = string | number | boolean | null;
type FormActionConfig = Record<string, FormActionConfigValue>;
type FormEditorPayload = Omit<FormEditData, 'id' | 'form_submissions_count'>;

interface FormEditData {
    id?: number;
    name: string;
    slug: string;
    description: string;
    blocks: Block[];
    submission_action: string;
    action_config: FormActionConfig;
    form_submissions_count?: number;
}

const props = defineProps<{
    form: FormEditData | null;
    availableBlocks: AvailableBlock[];
    availableActions: FormAction[];
}>();

const isEditing = computed(() => !!props.form?.id);
const selectedBlockId = ref<string | null>(null);
const sidebarOpen = ref(true);
const leftWidth = useLocalStorage('form-builder-left-width', 208);
const rightWidth = useLocalStorage('form-builder-right-width', 320);

const form = useForm<FormEditorPayload>({
    name: props.form?.name ?? '',
    slug: props.form?.slug ?? '',
    description: props.form?.description ?? '',
    blocks: (props.form?.blocks ?? []) as Block[],
    submission_action: props.form?.submission_action ?? 'core.store-submission',
    action_config: (props.form?.action_config ?? {}) as FormActionConfig,
});

function startResize(side: 'left' | 'right', e: MouseEvent) {
    const startX = e.clientX;
    const startWidth = side === 'left' ? leftWidth.value : rightWidth.value;

    document.body.style.cursor = 'col-resize';
    document.body.style.userSelect = 'none';

    function onMove(event: MouseEvent) {
        const delta =
            side === 'left' ? event.clientX - startX : startX - event.clientX;
        const newWidth = Math.max(160, Math.min(520, startWidth + delta));

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

const slugTouched = ref(isEditing.value);

function slugify(value: string) {
    return value
        .toLowerCase()
        .trim()
        .replace(/[^a-z0-9\s-]/g, '')
        .replace(/\s+/g, '-')
        .replace(/-+/g, '-');
}

watch(
    () => form.name,
    (value) => {
        if (!slugTouched.value) {
            form.slug = slugify(value);
        }
    },
);

function updateBlocksById(
    blocks: Block[],
    id: string,
    newData: Record<string, unknown>,
): Block[] {
    return blocks.map((block) => {
        if (block.id === id) {
            return { ...block, data: newData };
        }

        if (
            block.type !== 'columns' ||
            typeof block.data !== 'object' ||
            block.data === null
        ) {
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
            if (
                block.type !== 'columns' ||
                typeof block.data !== 'object' ||
                block.data === null
            ) {
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

        if (
            block.type !== 'columns' ||
            typeof block.data !== 'object' ||
            block.data === null
        ) {
            continue;
        }

        for (const [key, value] of Object.entries(
            block.data as Record<string, unknown>,
        )) {
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
    const definition = props.availableBlocks.find(
        (block) => block.type === type,
    );
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

const selectedBlock = computed(
    () => findBlockById(form.blocks, selectedBlockId.value ?? '') ?? null,
);

provide('builderCtx', {
    selectedBlockId,
    selectBlock: (id: string | null) => {
        selectedBlockId.value = id;
    },
    updateBlockData,
    removeBlock,
});

function updateFormField(field: keyof FormEditorPayload, value: unknown) {
    (form as unknown as FormEditorPayload)[field] = value as never;
}

function save() {
    if (isEditing.value) {
        form.put(admin.forms.update.url(props.form!.id!), {
            preserveScroll: true,
        });
    } else {
        form.post(admin.forms.store.url(), { preserveScroll: true });
    }
}

function deleteForm() {
    if (
        !props.form ||
        !confirm(
            `Delete "${props.form.name}"? All submissions will also be deleted.`,
        )
    ) {
        return;
    }

    const formId = props.form.id;

    if (!formId) {
        return;
    }

    router.delete(admin.forms.destroy.url(formId));
}
</script>

<template>
    <BuilderLayout>
        <template #header>
            <Link
                :href="admin.forms.index.url()"
                class="flex h-8 items-center gap-1.5 rounded-md px-2 text-sm text-muted-foreground transition-colors hover:bg-accent hover:text-foreground"
            >
                <ArrowLeft class="h-3.5 w-3.5" />
                Forms
            </Link>

            <div class="mx-3 h-4 w-px bg-border" />

            <div class="flex min-w-0 flex-1 items-center gap-2">
                <div class="min-w-0 flex-1">
                    <p class="truncate text-sm font-medium">
                        {{ form.name || 'Untitled form' }}
                    </p>
                    <p class="truncate font-mono text-xs text-muted-foreground">
                        /{{ form.slug || 'form-slug' }}
                    </p>
                </div>
                <p
                    v-if="form.errors.blocks"
                    class="hidden text-xs text-destructive sm:block"
                >
                    {{ form.errors.blocks }}
                </p>
            </div>

            <Button
                v-if="isEditing && props.form?.id"
                size="sm"
                variant="outline"
                class="h-7 gap-1.5 text-xs"
                as-child
            >
                <Link :href="admin.forms.submissions.index.url(props.form.id)">
                    <ClipboardList class="h-3.5 w-3.5" />
                    Submissions
                    <span
                        class="rounded-full bg-muted px-1.5 py-0.5 text-[10px] leading-none"
                    >
                        {{ props.form.form_submissions_count ?? 0 }}
                    </span>
                </Link>
            </Button>

            <Button
                v-if="isEditing"
                size="sm"
                variant="ghost"
                class="h-7 gap-1.5 text-xs text-muted-foreground hover:text-destructive"
                title="Delete form"
                @click="deleteForm"
            >
                <Trash2 class="h-3.5 w-3.5" />
            </Button>

            <Button
                size="sm"
                class="h-7 gap-1.5 text-xs"
                :disabled="form.processing"
                @click="save"
            >
                <Loader2
                    v-if="form.processing"
                    class="h-3.5 w-3.5 animate-spin"
                />
                Save
            </Button>

            <div class="mx-1" />

            <button
                class="flex h-8 w-8 items-center justify-center rounded-md transition-colors"
                :class="
                    sidebarOpen
                        ? 'bg-accent text-foreground'
                        : 'text-muted-foreground hover:bg-accent hover:text-foreground'
                "
                title="Toggle settings panel"
                @click="sidebarOpen = !sidebarOpen"
            >
                <PanelRight class="h-4 w-4" />
            </button>

            <div class="mx-1" />
        </template>

        <div
            class="shrink-0 overflow-hidden"
            :style="{ width: leftWidth + 'px' }"
        >
            <BlockPalette :available-blocks="availableBlocks" />
        </div>

        <div
            class="w-1 shrink-0 cursor-col-resize bg-border transition-colors hover:bg-primary/40 active:bg-primary/60"
            @mousedown.prevent="startResize('left', $event)"
        />

        <BlockCanvas
            :blocks="form.blocks"
            :title="form.name"
            :available-blocks="availableBlocks"
            :selected-block-id="selectedBlockId"
            device="desktop"
            :theme-config="{}"
            entity-label="form"
            title-placeholder="Form name"
            :allow-file-drop="false"
            @update:blocks="form.blocks = $event"
            @update:block-data="updateBlockData"
            @update:title="form.name = $event"
            @select="selectedBlockId = $event"
            @remove="removeBlock"
            @insert="insertBlock"
            @move-up="moveBlock($event, 'up')"
            @move-down="moveBlock($event, 'down')"
        />

        <div
            v-if="sidebarOpen"
            class="w-1 shrink-0 cursor-col-resize bg-border transition-colors hover:bg-primary/40 active:bg-primary/60"
            @mousedown.prevent="startResize('right', $event)"
        />

        <div
            v-if="sidebarOpen"
            class="shrink-0 overflow-hidden"
            :style="{ width: rightWidth + 'px' }"
        >
            <FormSettingsPanel
                :form="form"
                :selected-block="selectedBlock"
                :available-blocks="availableBlocks"
                :available-actions="availableActions"
                @update:form="updateFormField"
                @update:block-data="updateBlockData"
                @touch-slug="slugTouched = true"
                @deselect="selectedBlockId = null"
            />
        </div>
    </BuilderLayout>
</template>
