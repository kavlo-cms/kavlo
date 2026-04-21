<script setup lang="ts">
import { Eye, Image as ImageIcon, Plus, X } from 'lucide-vue-next';
import { computed, ref, watch } from 'vue';
import MediaPicker from '@/components/MediaPicker.vue';
import type { MediaItem } from '@/components/MediaPicker.vue';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Separator } from '@/components/ui/separator';
import { Switch } from '@/components/ui/switch';
import { Textarea } from '@/components/ui/textarea';
import { useBlockSchemas } from '@/composables/useBlockSchemas';
import type { AvailableBlock } from '@/composables/useBlockSchemas';
import type { Block } from '@/types/blocks';

type Tab = 'page' | 'block' | 'revisions';

interface MetaPair {
    key: string;
    value: string;
}

interface PageOption {
    id: number;
    title: string;
    slug: string;
}

interface PageForm {
    title: string;
    slug: string;
    type: string;
    editor_mode: 'builder' | 'content';
    content: string;
    is_published: boolean;
    is_homepage: boolean;
    parent_id: number | null;
    blocks: Block[];
    metadata: Record<string, string>;
    meta_title: string;
    meta_description: string;
    og_image: string;
    publish_at: string;
    unpublish_at: string;
}

interface PageType {
    type: string;
    label: string;
    view: string;
    description?: string | null;
    source?: string;
    source_label?: string;
}

interface RevisionEntry {
    id: number;
    label: string;
    created_at: string | null;
    user: { id: number; name: string } | null;
    preview_url: string;
    summary: string[];
}

const RESERVED_METADATA_KEYS = new Set([
    'title',
    'description',
    'og_title',
    'og_description',
    'og_image',
    'og:title',
    'og:description',
    'og:image',
]);

const props = defineProps<{
    form: PageForm;
    selectedBlock: Block | null;
    pages: PageOption[];
    revisions: RevisionEntry[];
    availableBlocks: AvailableBlock[];
    pageTypes: PageType[];
}>();

const emit = defineEmits<{
    'update:form': [field: keyof PageForm, value: unknown];
    'update:blockData': [id: string, data: Record<string, unknown>];
    deselect: [];
    previewRevision: [revision: RevisionEntry];
    restoreRevision: [revisionId: number];
}>();

// ── Tabs ──────────────────────────────────────────────────────────────────────
const activeTab = ref<Tab>('page');

watch(
    () => props.selectedBlock,
    (block) => {
        if (block) {
            activeTab.value = 'block';
        } else if (activeTab.value === 'block') {
            activeTab.value = 'page';
        }
    },
);

// ── Block settings ────────────────────────────────────────────────────────────
const { getSchema } = useBlockSchemas(() => props.availableBlocks);
const schema = computed(() =>
    props.selectedBlock ? getSchema(props.selectedBlock.type) : null,
);
const selectedPageType = computed(
    () =>
        props.pageTypes.find(
            (pageType) => pageType.type === (props.form.type ?? 'page'),
        ) ?? null,
);

function updateBlockField(key: string, value: string) {
    if (!props.selectedBlock) {
        return;
    }

    emit('update:blockData', props.selectedBlock.id, {
        ...(props.selectedBlock.data ?? {}),
        [key]: value,
    });
}

// ── Media picker ──────────────────────────────────────────────────────────────
const mediaPickerOpen = ref(false);
const mediaPickerFieldKey = ref('');

function openMediaPicker(fieldKey: string) {
    mediaPickerFieldKey.value = fieldKey;
    mediaPickerOpen.value = true;
}

function onMediaSelect(item: MediaItem) {
    if (!props.selectedBlock) {
        return;
    }

    const updates: Record<string, unknown> = {
        ...(props.selectedBlock.data ?? {}),
        [mediaPickerFieldKey.value]: item.url,
    };

    // If picking the main src field, also update alt
    if (mediaPickerFieldKey.value === 'src') {
        updates.alt = item.alt || item.name;
    }

    emit('update:blockData', props.selectedBlock.id, updates);
}

// ── Page-link field ───────────────────────────────────────────────────────────
const pageLinkModes = ref<Record<string, 'page' | 'custom'>>({});

function pageLinkMode(fieldKey: string): 'page' | 'custom' {
    if (pageLinkModes.value[fieldKey]) {
        return pageLinkModes.value[fieldKey];
    }

    // Auto-detect: if value looks like a page slug (starts with /) or matches a page slug → page mode
    const val = (props.selectedBlock?.data?.[fieldKey] as string) ?? '';
    const isPageSlug = val === '' || props.pages.some((p) => p.slug === val);

    return isPageSlug ? 'page' : 'custom';
}

function setPageLinkMode(fieldKey: string, mode: 'page' | 'custom') {
    pageLinkModes.value[fieldKey] = mode;
    // Clear value when switching modes
    updateBlockField(fieldKey, '');
}

// Reset modes when selected block changes
watch(
    () => props.selectedBlock?.id,
    () => {
        pageLinkModes.value = {};
    },
);

// ── Metadata ──────────────────────────────────────────────────────────────────
const METADATA_KEYS = ['keywords', 'robots', 'canonical'];

function filterMetadata(
    meta: Record<string, unknown> | null | undefined,
): MetaPair[] {
    return Object.entries(meta ?? {})
        .filter(([key]) => !RESERVED_METADATA_KEYS.has(key))
        .map(([key, value]) => ({ key, value: String(value) }));
}

const metaPairs = ref<MetaPair[]>(filterMetadata(props.form.metadata));

watch(
    () => props.form.metadata,
    (meta) => {
        const incoming = filterMetadata(meta);

        if (JSON.stringify(incoming) !== JSON.stringify(metaPairs.value)) {
            metaPairs.value = incoming;
        }
    },
);

function syncMeta() {
    const obj: Record<string, string> = {};
    metaPairs.value.forEach((p) => {
        if (p.key.trim()) {
            obj[p.key.trim()] = p.value;
        }
    });
    emit('update:form', 'metadata', obj);
}

function addMeta(key = '') {
    if (key && metaPairs.value.some((p) => p.key === key)) {
        return;
    }

    metaPairs.value.push({ key, value: '' });
    syncMeta();
}

function removeMeta(index: number) {
    metaPairs.value.splice(index, 1);
    syncMeta();
}
</script>

<template>
    <aside
        class="flex h-full min-h-0 w-full shrink-0 flex-col overflow-hidden border-l bg-background"
    >
        <!-- Tabs -->
        <div class="flex shrink-0 border-b">
            <button
                class="-mb-px flex-1 border-b-2 py-2.5 text-xs font-medium transition-colors"
                :class="
                    activeTab === 'page'
                        ? 'border-primary text-foreground'
                        : 'border-transparent text-muted-foreground hover:text-foreground'
                "
                @click="activeTab = 'page'"
            >
                Page
            </button>
            <button
                class="-mb-px flex-1 border-b-2 py-2.5 text-xs font-medium transition-colors"
                :class="
                    activeTab === 'block'
                        ? 'border-primary text-foreground'
                        : 'border-transparent text-muted-foreground hover:text-foreground'
                "
                :disabled="!selectedBlock"
                @click="activeTab = 'block'"
            >
                Block
            </button>
            <button
                class="-mb-px flex-1 border-b-2 py-2.5 text-xs font-medium transition-colors"
                :class="
                    activeTab === 'revisions'
                        ? 'border-primary text-foreground'
                        : 'border-transparent text-muted-foreground hover:text-foreground'
                "
                @click="activeTab = 'revisions'"
            >
                Revisions
                <span class="ml-1 text-[10px] text-muted-foreground">{{
                    revisions.length
                }}</span>
            </button>
        </div>

        <!-- ── Page tab ── -->
        <div v-if="activeTab === 'page'" class="min-h-0 flex-1 overflow-y-auto">
            <div class="flex flex-col gap-4 p-4">
                <div class="flex flex-col gap-1.5">
                    <Label for="page-title">Title</Label>
                    <Input
                        id="page-title"
                        :model-value="form.title"
                        @update:model-value="
                            emit('update:form', 'title', $event)
                        "
                    />
                </div>

                <div class="flex flex-col gap-1.5">
                    <Label for="page-slug">Slug</Label>
                    <Input
                        id="page-slug"
                        :model-value="form.slug"
                        @update:model-value="
                            emit('update:form', 'slug', $event)
                        "
                    />
                </div>

                <div class="flex flex-col gap-1.5">
                    <Label for="page-type">Page Type</Label>
                    <select
                        id="page-type"
                        class="flex h-9 w-full rounded-md border border-input bg-transparent px-3 py-1 text-sm shadow-xs transition-colors focus:ring-1 focus:ring-ring focus:outline-none"
                        :value="form.type ?? 'page'"
                        @change="
                            emit(
                                'update:form',
                                'type',
                                ($event.target as HTMLSelectElement).value,
                            )
                        "
                    >
                        <option
                            v-for="pt in pageTypes"
                            :key="pt.type"
                            :value="pt.type"
                        >
                            {{ pt.label }} ({{
                                pt.source_label ?? pt.source ?? 'Core'
                            }})
                        </option>
                    </select>
                    <p
                        v-if="selectedPageType"
                        class="text-xs text-muted-foreground"
                    >
                        Provided by
                        {{
                            selectedPageType.source_label ??
                            selectedPageType.source ??
                            'Core'
                        }}.
                    </p>
                    <p
                        v-if="selectedPageType?.description"
                        class="text-xs text-muted-foreground"
                    >
                        {{ selectedPageType.description }}
                    </p>
                </div>

                <div class="flex flex-col gap-1.5">
                    <Label for="page-parent">Parent Page</Label>
                    <select
                        id="page-parent"
                        class="flex h-9 w-full rounded-md border border-input bg-transparent px-3 py-1 text-sm shadow-xs transition-colors focus:ring-1 focus:ring-ring focus:outline-none"
                        :value="form.parent_id ?? ''"
                        @change="
                            emit(
                                'update:form',
                                'parent_id',
                                ($event.target as HTMLSelectElement).value
                                    ? Number(
                                          ($event.target as HTMLSelectElement)
                                              .value,
                                      )
                                    : null,
                            )
                        "
                    >
                        <option value="">— No parent (top-level) —</option>
                        <option v-for="p in pages" :key="p.id" :value="p.id">
                            {{ p.title }}
                        </option>
                    </select>
                </div>

                <Separator />

                <div class="flex items-center justify-between">
                    <div>
                        <Label for="page-published" class="cursor-pointer"
                            >Published</Label
                        >
                        <p class="text-xs text-muted-foreground">
                            Visible to the public
                        </p>
                    </div>
                    <Switch
                        id="page-published"
                        :model-value="form.is_published"
                        @update:model-value="
                            emit('update:form', 'is_published', $event)
                        "
                    />
                </div>

                <div class="flex items-center justify-between">
                    <div>
                        <Label for="page-homepage" class="cursor-pointer"
                            >Homepage</Label
                        >
                        <p class="text-xs text-muted-foreground">
                            Set as the site homepage
                        </p>
                    </div>
                    <Switch
                        id="page-homepage"
                        :model-value="form.is_homepage"
                        @update:model-value="
                            emit('update:form', 'is_homepage', $event)
                        "
                    />
                </div>

                <Separator />

                <!-- SEO Metadata -->
                <div class="flex flex-col gap-3">
                    <div class="flex items-center justify-between">
                        <Label
                            class="text-xs tracking-wide text-muted-foreground uppercase"
                            >Metadata</Label
                        >
                        <button
                            class="flex h-5 w-5 items-center justify-center rounded border text-muted-foreground transition-colors hover:bg-accent hover:text-foreground"
                            title="Add custom key"
                            @click="addMeta()"
                        >
                            <Plus class="h-3 w-3" />
                        </button>
                    </div>

                    <!-- Quick-add predefined keys -->
                    <div class="flex flex-wrap gap-1">
                        <button
                            v-for="key in METADATA_KEYS.filter(
                                (k) => !metaPairs.some((p) => p.key === k),
                            )"
                            :key="key"
                            class="rounded-full border bg-muted/30 px-2 py-0.5 text-xs text-muted-foreground transition-colors hover:bg-accent hover:text-foreground"
                            @click="addMeta(key)"
                        >
                            + {{ key }}
                        </button>
                    </div>

                    <!-- Key / Value rows -->
                    <div
                        v-for="(pair, i) in metaPairs"
                        :key="i"
                        class="flex items-start gap-1"
                    >
                        <input
                            :value="pair.key"
                            placeholder="key"
                            class="w-24 shrink-0 rounded-md border bg-transparent px-2 py-1 font-mono text-xs focus:ring-1 focus:ring-ring focus:outline-none"
                            @input="
                                pair.key = (
                                    $event.target as HTMLInputElement
                                ).value;
                                syncMeta();
                            "
                        />
                        <textarea
                            :value="pair.value"
                            placeholder="value"
                            rows="1"
                            class="min-h-[30px] flex-1 resize-none rounded-md border bg-transparent px-2 py-1 text-xs focus:ring-1 focus:ring-ring focus:outline-none"
                            @input="
                                pair.value = (
                                    $event.target as HTMLTextAreaElement
                                ).value;
                                syncMeta();
                            "
                        />
                        <button
                            class="mt-1 rounded p-1 text-muted-foreground transition-colors hover:text-destructive"
                            @click="removeMeta(i)"
                        >
                            <X class="h-3 w-3" />
                        </button>
                    </div>

                    <p
                        v-if="metaPairs.length === 0"
                        class="text-xs text-muted-foreground"
                    >
                        No metadata yet. Add predefined keys above or click +
                        for a custom key.
                    </p>
                </div>

                <Separator />

                <!-- SEO Fields -->
                <div class="flex flex-col gap-3">
                    <Label
                        class="text-xs tracking-wide text-muted-foreground uppercase"
                        >SEO</Label
                    >

                    <div class="flex flex-col gap-1.5">
                        <Label for="page-meta-title" class="text-xs"
                            >Meta Title</Label
                        >
                        <Input
                            id="page-meta-title"
                            :model-value="form.meta_title"
                            placeholder="Overrides page title in search results"
                            @update:model-value="
                                emit('update:form', 'meta_title', $event)
                            "
                        />
                    </div>

                    <div class="flex flex-col gap-1.5">
                        <Label for="page-meta-desc" class="text-xs"
                            >Meta Description</Label
                        >
                        <Textarea
                            id="page-meta-desc"
                            :model-value="form.meta_description"
                            placeholder="Brief description for search engines (150–160 chars)"
                            rows="3"
                            @update:model-value="
                                emit('update:form', 'meta_description', $event)
                            "
                        />
                        <p class="text-right text-xs text-muted-foreground">
                            {{ (form.meta_description ?? '').length }}/160
                        </p>
                    </div>

                    <div class="flex flex-col gap-1.5">
                        <Label for="page-og-image" class="text-xs"
                            >OG Image URL</Label
                        >
                        <Input
                            id="page-og-image"
                            :model-value="form.og_image"
                            placeholder="https://… or /storage/…"
                            @update:model-value="
                                emit('update:form', 'og_image', $event)
                            "
                        />
                    </div>
                </div>

                <Separator />

                <!-- Scheduled Publishing -->
                <div class="flex flex-col gap-3">
                    <Label
                        class="text-xs tracking-wide text-muted-foreground uppercase"
                        >Scheduled Publishing</Label
                    >

                    <div class="flex flex-col gap-1.5">
                        <Label for="page-publish-at" class="text-xs"
                            >Publish At</Label
                        >
                        <input
                            id="page-publish-at"
                            type="datetime-local"
                            :value="form.publish_at"
                            class="flex h-9 w-full rounded-md border border-input bg-transparent px-3 py-1 text-sm shadow-xs focus:ring-1 focus:ring-ring focus:outline-none"
                            @input="
                                emit(
                                    'update:form',
                                    'publish_at',
                                    ($event.target as HTMLInputElement).value,
                                )
                            "
                        />
                        <p class="text-xs text-muted-foreground">
                            Auto-publishes at this date/time
                        </p>
                    </div>

                    <div class="flex flex-col gap-1.5">
                        <Label for="page-unpublish-at" class="text-xs"
                            >Unpublish At</Label
                        >
                        <input
                            id="page-unpublish-at"
                            type="datetime-local"
                            :value="form.unpublish_at"
                            class="flex h-9 w-full rounded-md border border-input bg-transparent px-3 py-1 text-sm shadow-xs focus:ring-1 focus:ring-ring focus:outline-none"
                            @input="
                                emit(
                                    'update:form',
                                    'unpublish_at',
                                    ($event.target as HTMLInputElement).value,
                                )
                            "
                        />
                        <p class="text-xs text-muted-foreground">
                            Auto-unpublishes at this date/time
                        </p>
                    </div>
                </div>
            </div>
        </div>
        <!-- ── Block tab ── -->
        <div
            v-else-if="activeTab === 'block'"
            class="min-h-0 flex-1 overflow-y-auto"
        >
            <template v-if="selectedBlock">
                <div class="flex items-center gap-2 border-b px-4 py-3">
                    <span class="text-sm font-semibold">
                        {{ schema?.label ?? selectedBlock.type }}
                    </span>
                    <button
                        class="ml-auto rounded px-2 py-0.5 text-xs text-muted-foreground transition-colors hover:bg-accent"
                        @click="emit('deselect')"
                    >
                        Deselect
                    </button>
                </div>

                <div v-if="schema" class="flex flex-col gap-4 p-4">
                    <div
                        v-for="field in schema.fields"
                        :key="field.key"
                        class="flex flex-col gap-1.5"
                    >
                        <Label :for="`block-${field.key}`">{{
                            field.label
                        }}</Label>

                        <!-- Toggle -->
                        <div
                            v-if="field.type === 'toggle'"
                            class="flex items-center gap-2 pt-0.5"
                        >
                            <Switch
                                :id="`block-${field.key}`"
                                :model-value="!!selectedBlock.data?.[field.key]"
                                @update:model-value="
                                    updateBlockField(
                                        field.key,
                                        $event ? 'true' : '',
                                    )
                                "
                            />
                            <span class="text-xs text-muted-foreground">{{
                                selectedBlock.data?.[field.key] ? 'On' : 'Off'
                            }}</span>
                        </div>

                        <!-- Select -->
                        <select
                            v-else-if="field.type === 'select'"
                            :id="`block-${field.key}`"
                            :value="
                                (selectedBlock.data?.[field.key] as string) ??
                                field.options?.[0]?.value ??
                                ''
                            "
                            class="flex h-9 w-full rounded-md border border-input bg-transparent px-3 py-1 text-sm shadow-sm transition-colors focus:ring-1 focus:ring-ring focus:outline-none"
                            @change="
                                updateBlockField(
                                    field.key,
                                    ($event.target as HTMLSelectElement).value,
                                )
                            "
                        >
                            <option
                                v-for="opt in field.options"
                                :key="opt.value"
                                :value="opt.value"
                            >
                                {{ opt.label }}
                            </option>
                        </select>

                        <!-- Textarea -->
                        <Textarea
                            v-else-if="field.type === 'textarea'"
                            :id="`block-${field.key}`"
                            :model-value="
                                (selectedBlock.data?.[field.key] as string) ??
                                ''
                            "
                            :placeholder="field.placeholder"
                            rows="3"
                            @update:model-value="
                                updateBlockField(field.key, $event as string)
                            "
                        />

                        <!-- Media picker -->
                        <div
                            v-else-if="field.type === 'media'"
                            class="flex flex-col gap-1.5"
                        >
                            <div
                                v-if="selectedBlock.data?.[field.key]"
                                class="group relative overflow-hidden rounded-lg border"
                            >
                                <img
                                    :src="
                                        selectedBlock.data[field.key] as string
                                    "
                                    class="h-28 w-full object-cover"
                                    alt=""
                                />
                                <button
                                    class="absolute inset-0 flex items-center justify-center bg-black/40 opacity-0 transition-opacity group-hover:opacity-100"
                                    @click="openMediaPicker(field.key)"
                                >
                                    <span
                                        class="rounded bg-white/90 px-2 py-1 text-xs font-medium text-gray-900"
                                        >Change</span
                                    >
                                </button>
                            </div>
                            <button
                                v-else
                                class="flex h-20 w-full flex-col items-center justify-center gap-1 rounded-lg border-2 border-dashed border-muted-foreground/30 text-muted-foreground transition-colors hover:border-primary/50 hover:bg-primary/5"
                                @click="openMediaPicker(field.key)"
                            >
                                <ImageIcon class="h-5 w-5 opacity-50" />
                                <span class="text-xs">Choose from Library</span>
                            </button>
                        </div>

                        <!-- Page link picker -->
                        <div
                            v-else-if="field.type === 'page-link'"
                            class="flex flex-col gap-2"
                        >
                            <div class="flex gap-1.5">
                                <button
                                    class="flex-1 rounded-md border px-2.5 py-1.5 text-left text-xs transition-colors"
                                    :class="
                                        pageLinkMode(field.key) === 'page'
                                            ? 'border-primary bg-primary text-primary-foreground'
                                            : 'text-muted-foreground hover:bg-accent'
                                    "
                                    @click="setPageLinkMode(field.key, 'page')"
                                >
                                    Page
                                </button>
                                <button
                                    class="flex-1 rounded-md border px-2.5 py-1.5 text-left text-xs transition-colors"
                                    :class="
                                        pageLinkMode(field.key) === 'custom'
                                            ? 'border-primary bg-primary text-primary-foreground'
                                            : 'text-muted-foreground hover:bg-accent'
                                    "
                                    @click="
                                        setPageLinkMode(field.key, 'custom')
                                    "
                                >
                                    Custom
                                </button>
                            </div>
                            <select
                                v-if="pageLinkMode(field.key) === 'page'"
                                :value="
                                    (selectedBlock.data?.[
                                        field.key
                                    ] as string) ?? ''
                                "
                                class="flex h-9 w-full rounded-md border border-input bg-transparent px-3 py-1 text-sm shadow-sm transition-colors focus:ring-1 focus:ring-ring focus:outline-none"
                                @change="
                                    updateBlockField(
                                        field.key,
                                        ($event.target as HTMLSelectElement)
                                            .value,
                                    )
                                "
                            >
                                <option value="">— Select a page —</option>
                                <option
                                    v-for="p in pages"
                                    :key="p.id"
                                    :value="p.slug"
                                >
                                    {{ p.title }}
                                </option>
                            </select>
                            <Input
                                v-else
                                :id="`block-${field.key}`"
                                type="text"
                                :model-value="
                                    (selectedBlock.data?.[
                                        field.key
                                    ] as string) ?? ''
                                "
                                placeholder="https://... or javascript:void(0)"
                                @update:model-value="
                                    updateBlockField(
                                        field.key,
                                        $event as string,
                                    )
                                "
                            />
                        </div>

                        <!-- Text / URL / Number -->
                        <Input
                            v-else
                            :id="`block-${field.key}`"
                            :type="
                                field.type === 'url'
                                    ? 'url'
                                    : field.type === 'number'
                                      ? 'number'
                                      : 'text'
                            "
                            :model-value="
                                (selectedBlock.data?.[field.key] as string) ??
                                ''
                            "
                            :placeholder="field.placeholder"
                            @update:model-value="
                                updateBlockField(field.key, $event as string)
                            "
                        />
                    </div>

                    <p
                        v-if="schema.fields.length === 0"
                        class="text-sm text-muted-foreground"
                    >
                        This block has no editable fields.
                    </p>
                </div>

                <p v-else class="p-4 text-sm text-muted-foreground">
                    No fields defined for this block type.
                </p>
            </template>

            <div
                v-else
                class="flex flex-col items-center justify-center gap-2 py-16 text-center"
            >
                <p class="text-sm text-muted-foreground">No block selected</p>
                <p class="text-xs text-muted-foreground">
                    Click a block on the canvas to edit its settings
                </p>
            </div>
        </div>

        <!-- ── Revisions tab ── -->
        <div v-else class="min-h-0 flex-1 overflow-y-auto">
            <div class="flex flex-col gap-3 p-4">
                <div class="flex items-center justify-between">
                    <Label
                        class="text-xs tracking-wide text-muted-foreground uppercase"
                        >Revision History</Label
                    >
                    <span class="text-xs text-muted-foreground">{{
                        revisions.length
                    }}</span>
                </div>

                <p class="text-xs text-muted-foreground">
                    Preview a saved revision before restoring it. Restoring
                    still creates a new restore point first.
                </p>

                <div
                    v-if="revisions.length === 0"
                    class="rounded-md border border-dashed p-3 text-xs text-muted-foreground"
                >
                    No saved revisions yet.
                </div>

                <div v-else class="space-y-2">
                    <div
                        v-for="revision in revisions"
                        :key="revision.id"
                        class="rounded-md border p-3"
                    >
                        <div class="flex items-start justify-between gap-2">
                            <div class="min-w-0">
                                <p class="truncate text-sm font-medium">
                                    {{ revision.label }}
                                </p>
                                <p class="text-xs text-muted-foreground">
                                    {{
                                        revision.created_at
                                            ? new Date(
                                                  revision.created_at,
                                              ).toLocaleString()
                                            : 'Unknown time'
                                    }}
                                    <span v-if="revision.user"
                                        >&middot; {{ revision.user.name }}</span
                                    >
                                </p>
                            </div>
                            <div class="flex shrink-0 items-center gap-1.5">
                                <button
                                    type="button"
                                    class="inline-flex items-center gap-1 rounded border px-2 py-1 text-xs text-muted-foreground transition-colors hover:bg-accent hover:text-foreground"
                                    @click="emit('previewRevision', revision)"
                                >
                                    <Eye class="h-3 w-3" />
                                    Preview
                                </button>
                                <button
                                    type="button"
                                    class="shrink-0 rounded border px-2 py-1 text-xs text-muted-foreground transition-colors hover:bg-accent hover:text-foreground"
                                    @click="
                                        emit('restoreRevision', revision.id)
                                    "
                                >
                                    Restore
                                </button>
                            </div>
                        </div>

                        <ul class="mt-2 space-y-1">
                            <li
                                v-for="item in revision.summary"
                                :key="item"
                                class="text-xs text-muted-foreground"
                            >
                                {{ item }}
                            </li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>

        <!-- Media picker dialog (shared across all media fields) -->
        <MediaPicker
            :open="mediaPickerOpen"
            @update:open="mediaPickerOpen = $event"
            @select="onMediaSelect"
        />
    </aside>
</template>
