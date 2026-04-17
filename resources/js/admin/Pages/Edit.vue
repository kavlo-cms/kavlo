<script setup lang="ts">
import { useForm, router } from '@inertiajs/vue3';
import { Link } from '@inertiajs/vue3';
import { useLocalStorage, watchDebounced } from '@vueuse/core';
import { ArrowLeft, Eye, EyeOff, Loader2, Monitor, PanelRight, Smartphone, Tablet, Trash2 } from 'lucide-vue-next';
import { computed, provide, ref, watch } from 'vue';
import { Button } from '@/components/ui/button';
import { type AvailableBlock } from '@/composables/useBlockSchemas';
import BuilderLayout from '@/layouts/BuilderLayout.vue';
import admin from '@/routes/admin';
import type { Block } from '@/types/blocks';
import BlockCanvas from './partials/BlockCanvas.vue';
import BlockPalette from './partials/BlockPalette.vue';
import SettingsPanel from './partials/SettingsPanel.vue';

interface PageData {
    id: number;
    title: string;
    slug: string;
    type: string;
    content: string | null;
    is_published: boolean;
    is_homepage: boolean;
    parent_id: number | null;
    blocks: Block[] | null;
    metadata: Record<string, string> | null;
    meta_title: string | null;
    meta_description: string | null;
    og_image: string | null;
    publish_at: string | null;
    unpublish_at: string | null;
}

interface PageType {
    type: string;
    label: string;
    view: string;
}

interface ThemeConfig {
    name?: string;
    slug?: string;
    canvas?: {
        class?: string;
        font?: string | null;
    };
}

const props = defineProps<{
    page: PageData;
    pages: { id: number; title: string }[];
    availableBlocks: AvailableBlock[];
    previewUrl: string;
    themeConfig: ThemeConfig;
    pageTypes: PageType[];
}>();

type Device = 'desktop' | 'tablet' | 'mobile';

const selectedBlockId = ref<string | null>(null);
const sidebarOpen = ref(true);
const device = ref<Device>('desktop');
const previewMode = ref(false);
const previewHtml = ref('');
const previewLoading = ref(false);

const leftWidth  = useLocalStorage('builder-left-width',  208);
const rightWidth = useLocalStorage('builder-right-width', 320);

function startResize(side: 'left' | 'right', e: MouseEvent) {
    const startX     = e.clientX;
    const startWidth = side === 'left' ? leftWidth.value : rightWidth.value;

    document.body.style.cursor    = 'col-resize';
    document.body.style.userSelect = 'none';

    function onMove(e: MouseEvent) {
        const delta    = side === 'left' ? e.clientX - startX : startX - e.clientX;
        const newWidth = Math.max(160, Math.min(520, startWidth + delta));
        if (side === 'left') leftWidth.value  = newWidth;
        else                 rightWidth.value = newWidth;
    }

    function onUp() {
        document.body.style.cursor     = '';
        document.body.style.userSelect = '';
        document.removeEventListener('mousemove', onMove);
        document.removeEventListener('mouseup',   onUp);
    }

    document.addEventListener('mousemove', onMove);
    document.addEventListener('mouseup',   onUp);
}

const deviceList: { key: Device; icon: typeof Monitor; label: string }[] = [
    { key: 'desktop', icon: Monitor, label: 'Desktop' },
    { key: 'tablet', icon: Tablet, label: 'Tablet' },
    { key: 'mobile', icon: Smartphone, label: 'Mobile' },
];

const form = useForm({
    title: props.page.title,
    slug: props.page.slug,
    type: props.page.type ?? 'page',
    content: props.page.content ?? '',
    is_published: props.page.is_published,
    is_homepage: props.page.is_homepage,
    parent_id: props.page.parent_id ?? null,
    blocks: (props.page.blocks ?? []) as Block[],
    metadata: (props.page.metadata ?? {}) as Record<string, string>,
    create_redirect: false,
    meta_title: props.page.meta_title ?? '',
    meta_description: props.page.meta_description ?? '',
    og_image: props.page.og_image ?? '',
    publish_at: props.page.publish_at ?? '',
    unpublish_at: props.page.unpublish_at ?? '',
});

// Track the slug that is actually persisted in the DB so we can detect changes
const savedSlug = ref(props.page.slug);
const slugChanged = computed(() => form.slug.trim() !== '' && form.slug.trim() !== savedSlug.value);

function updateBlocksById(blocks: Block[], id: string, newData: Record<string, unknown>): Block[] {
    return blocks.map((block) => {
        if (block.id === id) {
            return { ...block, data: newData };
        }

        if (typeof block.data !== 'object' || block.data === null) {
            return block;
        }

        const data = { ...block.data } as Record<string, unknown>;
        let changed = false;

        if (Array.isArray(data.children)) {
            const nextChildren = updateBlocksById(data.children as Block[], id, newData);
            if (nextChildren !== data.children) {
                data.children = nextChildren;
                changed = true;
            }
        }

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
            if (typeof block.data !== 'object' || block.data === null) {
                return block;
            }

            const data = { ...block.data } as Record<string, unknown>;
            let changed = false;

            if (Array.isArray(data.children)) {
                const nextChildren = removeBlocksById(data.children as Block[], id);
                if (nextChildren !== data.children) {
                    data.children = nextChildren;
                    changed = true;
                }
            }

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

        if (Array.isArray(block.data?.children)) {
            const found = findBlockById(block.data.children as Block[], id);
            if (found) {
                return found;
            }
        }

        if (typeof block.data !== 'object' || block.data === null) {
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

function updateBlockData(id: string, data: Record<string, unknown>) {
    form.blocks = updateBlocksById(form.blocks, id, data);
}

function removeBlock(id: string) {
    if (selectedBlockId.value === id) selectedBlockId.value = null;
    form.blocks = removeBlocksById(form.blocks, id);
}

function insertBlock(index: number, type: string) {
    const newBlock: Block = { id: crypto.randomUUID(), type, data: {}, order: index };
    const next = [...form.blocks];
    next.splice(index, 0, newBlock);
    form.blocks = next;
    selectedBlockId.value = newBlock.id;
}

function moveBlock(id: string, direction: 'up' | 'down') {
    const idx = form.blocks.findIndex((b) => b.id === id);
    if (idx === -1) return;
    const next = [...form.blocks];
    if (direction === 'up' && idx > 0) {
        [next[idx - 1], next[idx]] = [next[idx], next[idx - 1]];
    } else if (direction === 'down' && idx < next.length - 1) {
        [next[idx + 1], next[idx]] = [next[idx], next[idx + 1]];
    }
    form.blocks = next;
}

const selectedBlock = computed(() => findBlockById(form.blocks, selectedBlockId.value ?? ''));

provide('builderCtx', {
    selectedBlockId,
    selectBlock: (id: string | null) => { selectedBlockId.value = id; },
    updateBlockData,
    removeBlock,
});

function updateFormField(field: keyof typeof form, value: unknown) {
    (form as any)[field] = value;
}

function save() {
    form.put(admin.pages.update(props.page.id).url, {
        onSuccess: () => {
            savedSlug.value = form.slug;
            form.create_redirect = false;
        },
    });
}

async function fetchPreview() {
    previewLoading.value = true;
    try {
        const token = decodeURIComponent(
            document.cookie.match(/XSRF-TOKEN=([^;]+)/)?.[1] ?? '',
        );
        const res = await fetch(admin.pages.preview.live(props.page.id).url, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-XSRF-TOKEN': token,
            },
            body: JSON.stringify({ title: form.title, type: form.type, blocks: form.blocks }),
        });
        previewHtml.value = await res.text();
    } finally {
        previewLoading.value = false;
    }
}

watch(previewMode, (on) => {
    if (on) fetchPreview();
});

watchDebounced(
    () => [form.blocks, form.title] as const,
    () => { if (previewMode.value) fetchPreview(); },
    { debounce: 600, deep: true },
);

function deletePage() {
    if (!confirm(`Delete "${props.page.title}"? This cannot be undone.`)) return;
    router.delete(`/admin/pages/${props.page.id}`);
}
</script>

<template>
    <BuilderLayout>
        <template #header>
            <Link
                :href="admin.pages.index.url()"
                class="flex h-8 items-center gap-1.5 rounded-md px-2 text-sm text-muted-foreground hover:bg-accent hover:text-foreground transition-colors"
            >
                <ArrowLeft class="h-3.5 w-3.5" />
                Pages
            </Link>

            <div class="mx-3 h-4 w-px bg-border" />

            <div class="flex min-w-0 flex-1 items-center gap-2">
                <input
                    :value="form.title"
                    class="min-w-0 flex-1 rounded-md bg-transparent px-2 py-1 text-sm font-medium focus:outline-none focus:ring-1 focus:ring-ring"
                    placeholder="Page title"
                    @input="form.title = ($event.target as HTMLInputElement).value"
                />
                <div class="hidden items-center rounded-md border bg-muted/40 pl-2 sm:flex" :class="{ 'border-amber-400/60': slugChanged }">
                    <span class="text-xs text-muted-foreground">/</span>
                    <input
                        :value="form.slug"
                        class="w-40 bg-transparent px-2 py-1 text-xs font-mono focus:outline-none"
                        placeholder="page-slug"
                        @input="form.slug = ($event.target as HTMLInputElement).value"
                    />
                </div>
                <!-- Redirect prompt shown when slug has changed -->
                <label
                    v-if="slugChanged"
                    class="hidden sm:flex items-center gap-1.5 cursor-pointer text-xs text-amber-600 dark:text-amber-400"
                    title="Create a 301 redirect from the old URL to the new one"
                >
                    <input
                        type="checkbox"
                        :checked="form.create_redirect"
                        class="rounded"
                        @change="form.create_redirect = ($event.target as HTMLInputElement).checked"
                    />
                    Redirect <span class="font-mono">/{{ savedSlug }}</span>
                </label>
            </div>

            <div class="mx-auto" />

            <button
                class="flex h-8 items-center gap-1.5 rounded-md px-3 text-sm transition-colors"
                :class="previewMode ? 'bg-accent text-foreground' : 'text-muted-foreground hover:bg-accent hover:text-foreground'"
                :title="previewMode ? 'Exit preview' : 'Preview theme'"
                @click="previewMode = !previewMode"
            >
                <Loader2 v-if="previewLoading" class="h-3.5 w-3.5 animate-spin" />
                <EyeOff v-else-if="previewMode" class="h-3.5 w-3.5" />
                <Eye v-else class="h-3.5 w-3.5" />
                Preview
            </button>

            <div class="mx-1" />

            <!-- Device switcher -->
            <div class="flex items-center gap-0.5 rounded-md border bg-muted/40 p-0.5">
                <button
                    v-for="d in deviceList"
                    :key="d.key"
                    class="rounded p-1.5 transition-colors"
                    :class="device === d.key ? 'bg-background shadow-sm text-foreground' : 'text-muted-foreground hover:text-foreground'"
                    :title="d.label"
                    @click="device = d.key"
                >
                    <component :is="d.icon" class="h-3.5 w-3.5" />
                </button>
            </div>

            <div class="mx-1" />

            <Button
                size="sm"
                variant="ghost"
                class="h-7 gap-1.5 text-xs text-muted-foreground hover:text-destructive"
                title="Delete page"
                @click="deletePage"
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

            <div class="mx-1" />
        </template>

        <div v-if="!previewMode" class="shrink-0 overflow-hidden" :style="{ width: leftWidth + 'px' }">
            <BlockPalette :available-blocks="availableBlocks" />
        </div>

        <!-- Left drag handle -->
        <div
            v-if="!previewMode"
            class="w-1 shrink-0 cursor-col-resize bg-border hover:bg-primary/40 active:bg-primary/60 transition-colors"
            @mousedown.prevent="startResize('left', $event)"
        />

        <!-- Theme iframe preview -->
        <div v-if="previewMode" class="relative min-w-0 flex-1 overflow-auto bg-muted/30">
            <div
                class="mx-auto transition-all duration-300 overflow-hidden"
                :class="{
                    'w-full': device === 'desktop',
                    'max-w-[768px]': device === 'tablet',
                    'max-w-[390px]': device === 'mobile',
                }"
            >
                <iframe
                    v-if="previewHtml"
                    :srcdoc="previewHtml"
                    sandbox="allow-same-origin allow-scripts allow-popups allow-forms"
                    class="w-full border-0"
                    style="min-height: 100vh"
                    @load="(e: Event) => {
                        const f = e.target as HTMLIFrameElement;
                        const h = f.contentDocument?.documentElement?.scrollHeight;
                        if (h) f.style.height = h + 'px';
                    }"
                />
                <div v-else class="flex h-64 items-center justify-center text-muted-foreground text-sm">
                    <Loader2 class="mr-2 h-4 w-4 animate-spin" /> Loading preview…
                </div>
            </div>
        </div>

        <BlockCanvas
            v-else
            :blocks="form.blocks"
            :title="form.title"
            :available-blocks="availableBlocks"
            :selected-block-id="selectedBlockId"
            :device="device"
            :theme-config="themeConfig"
            @update:blocks="form.blocks = $event"
            @update:block-data="updateBlockData"
            @update:title="form.title = $event"
            @select="selectedBlockId = $event"
            @remove="removeBlock"
            @insert="insertBlock"
            @move-up="moveBlock($event, 'up')"
            @move-down="moveBlock($event, 'down')"
        />

        <!-- Right drag handle -->
        <div
            v-if="!previewMode && sidebarOpen"
            class="w-1 shrink-0 cursor-col-resize bg-border hover:bg-primary/40 active:bg-primary/60 transition-colors"
            @mousedown.prevent="startResize('right', $event)"
        />

        <div v-if="!previewMode && sidebarOpen" class="shrink-0 overflow-hidden" :style="{ width: rightWidth + 'px' }">
            <SettingsPanel
                :form="form"
                :selected-block="selectedBlock"
                :pages="props.pages"
                :available-blocks="availableBlocks"
                :page-types="props.pageTypes"
                @update:form="updateFormField"
                @update:block-data="updateBlockData"
                @deselect="selectedBlockId = null"
            />
        </div>
    </BuilderLayout>
</template>
