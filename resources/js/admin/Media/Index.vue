<script setup lang="ts">
import {
    Archive,
    CheckCircle2,
    File,
    FileText,
    FolderOpen,
    Image as ImageIcon,
    Music,
    Pencil,
    Search,
    Trash2,
    Upload,
    Video,
    X,
} from 'lucide-vue-next';
import { computed, ref, watch } from 'vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import AppLayout from '@/layouts/AppLayout.vue';

export interface MediaItem {
    id: number;
    name: string;
    file_name: string;
    mime_type: string;
    extension: string;
    kind: 'image' | 'video' | 'audio' | 'pdf' | 'document' | 'archive' | 'file';
    size: number;
    url: string;
    download_url: string;
    folder: string;
    alt: string;
    created_at: string;
    usage_count: number;
    usage: { type: string; label: string; href: string; context: string }[];
}

const props = defineProps<{
    folders: string[];
    initialMedia: MediaItem[];
}>();

const breadcrumbs = [{ title: 'Media Library', href: '/admin/media' }];

const uploadAccept = [
    'image/*',
    'video/*',
    'audio/*',
    '.pdf',
    '.doc',
    '.docx',
    '.xls',
    '.xlsx',
    '.ppt',
    '.pptx',
    '.txt',
    '.csv',
    '.json',
    '.zip',
].join(',');

const kindFilters = [
    { value: 'all', label: 'All' },
    { value: 'image', label: 'Images' },
    { value: 'video', label: 'Video' },
    { value: 'audio', label: 'Audio' },
    { value: 'document', label: 'Docs' },
    { value: 'archive', label: 'Archives' },
    { value: 'file', label: 'Other' },
] as const;

const folders = ref<string[]>(
    props.folders.length ? props.folders : ['uploads'],
);
const activeFolder = ref(folders.value[0] ?? 'uploads');
const mediaItems = ref<MediaItem[]>(props.initialMedia);
const search = ref('');
const activeKind = ref<(typeof kindFilters)[number]['value']>('all');
const selectedIds = ref<number[]>([]);
const loading = ref(false);
const uploading = ref(false);
const uploadError = ref('');
const isDragOver = ref(false);
const editingAlt = ref<{ id: number; value: string } | null>(null);
const editingName = ref<{ id: number; value: string } | null>(null);
const newFolderName = ref('');
const showNewFolder = ref(false);

const filteredItems = computed(() => {
    const q = search.value.trim().toLowerCase();

    return mediaItems.value.filter((item) => {
        const matchesSearch =
            !q ||
            item.name.toLowerCase().includes(q) ||
            item.file_name.toLowerCase().includes(q) ||
            item.extension.toLowerCase().includes(q) ||
            item.mime_type.toLowerCase().includes(q);

        const matchesKind =
            activeKind.value === 'all' ||
            (activeKind.value === 'document'
                ? ['document', 'pdf'].includes(item.kind)
                : item.kind === activeKind.value);

        return matchesSearch && matchesKind;
    });
});

const selectedItems = computed(() =>
    mediaItems.value.filter((item) => selectedIds.value.includes(item.id)),
);
const detailItem = computed<MediaItem | null>(() =>
    selectedItems.value.length === 1 ? (selectedItems.value[0] ?? null) : null,
);

function getCsrfToken(): string {
    return (document.cookie.match(/XSRF-TOKEN=([^;]+)/) ?? [])[1]
        ? decodeURIComponent(
              (document.cookie.match(/XSRF-TOKEN=([^;]+)/) ?? [])[1],
          )
        : ((
              document.querySelector(
                  'meta[name="csrf-token"]',
              ) as HTMLMetaElement
          )?.content ?? '');
}

const reqHeaders = () => ({
    Accept: 'application/json',
    'X-Requested-With': 'XMLHttpRequest',
    'X-XSRF-TOKEN': getCsrfToken(),
});

async function loadMedia(folder = activeFolder.value) {
    loading.value = true;
    try {
        const res = await fetch(
            `/admin/media/list?folder=${encodeURIComponent(folder)}&per_page=80`,
            {
                headers: reqHeaders(),
            },
        );
        if (res.ok) {
            const data = await res.json();
            mediaItems.value = data.data;
            selectedIds.value = selectedIds.value.filter((id) =>
                data.data.some((item: MediaItem) => item.id === id),
            );
        }
    } finally {
        loading.value = false;
    }
}

async function uploadFiles(files: FileList | File[]) {
    uploading.value = true;
    uploadError.value = '';
    try {
        for (const file of Array.from(files)) {
            const fd = new FormData();
            fd.append('file', file);
            fd.append('folder', activeFolder.value);
            const res = await fetch('/admin/media/upload', {
                method: 'POST',
                headers: reqHeaders(),
                body: fd,
            });
            if (res.ok) {
                const item: MediaItem = await res.json();
                mediaItems.value.unshift(item);
                if (!folders.value.includes(activeFolder.value)) {
                    folders.value.push(activeFolder.value);
                }
                continue;
            }

            uploadError.value = await extractError(
                res,
                `Uploading "${file.name}" failed.`,
            );
            break;
        }
    } finally {
        uploading.value = false;
    }
}

async function saveAlt() {
    if (!editingAlt.value) return;
    const { id, value } = editingAlt.value;
    const res = await fetch(`/admin/media/${id}`, {
        method: 'PATCH',
        headers: { ...reqHeaders(), 'Content-Type': 'application/json' },
        body: JSON.stringify({ alt: value }),
    });
    if (res.ok) {
        const updated: MediaItem = await res.json();
        syncItem(updated);
    }
    editingAlt.value = null;
}

async function saveName() {
    if (!editingName.value) return;
    const { id, value } = editingName.value;
    const res = await fetch(`/admin/media/${id}`, {
        method: 'PATCH',
        headers: { ...reqHeaders(), 'Content-Type': 'application/json' },
        body: JSON.stringify({ name: value }),
    });
    if (res.ok) {
        const updated: MediaItem = await res.json();
        syncItem(updated);
    }
    editingName.value = null;
}

async function saveFolder(item: MediaItem, folder: string) {
    const res = await fetch(`/admin/media/${item.id}`, {
        method: 'PATCH',
        headers: { ...reqHeaders(), 'Content-Type': 'application/json' },
        body: JSON.stringify({ folder }),
    });

    if (res.ok) {
        const updated: MediaItem = await res.json();
        syncItem(updated);

        if (!folders.value.includes(folder)) {
            folders.value.push(folder);
        }

        if (updated.folder !== activeFolder.value) {
            mediaItems.value = mediaItems.value.filter(
                (entry) => entry.id !== updated.id,
            );
            selectedIds.value = selectedIds.value.filter(
                (id) => id !== updated.id,
            );
        }
    }
}

async function deleteMedia(
    item: MediaItem,
    force = false,
    skipConfirm = false,
) {
    if (!force && !skipConfirm && !confirm(`Delete "${item.name}"?`)) return;

    const res = await fetch(
        `/admin/media/${item.id}${force ? '?force=1' : ''}`,
        {
            method: 'DELETE',
            headers: reqHeaders(),
        },
    );

    if (res.status === 422) {
        const payload = await res.json();
        const usageCount = payload?.usage?.count ?? item.usage_count;

        if (
            confirm(
                `"${item.name}" is still used in ${usageCount} place(s). Delete it anyway?`,
            )
        ) {
            await deleteMedia(item, true);
        }

        return;
    }

    if (res.ok) {
        mediaItems.value = mediaItems.value.filter((m) => m.id !== item.id);
        selectedIds.value = selectedIds.value.filter((id) => id !== item.id);
    }
}

function syncItem(updated: MediaItem) {
    const idx = mediaItems.value.findIndex((m) => m.id === updated.id);
    if (idx !== -1) mediaItems.value[idx] = updated;
}

function onDragOver(e: DragEvent) {
    e.preventDefault();
    isDragOver.value = true;
}
function onDragLeave() {
    isDragOver.value = false;
}
async function onDrop(e: DragEvent) {
    e.preventDefault();
    isDragOver.value = false;
    if (e.dataTransfer?.files.length) await uploadFiles(e.dataTransfer.files);
}
function onFileInput(e: Event) {
    const target = e.target as HTMLInputElement;
    const files = target.files;
    if (files?.length) {
        uploadFiles(files);
    }
    target.value = '';
}

function createFolder() {
    const name = newFolderName.value.trim().replace(/\s+/g, '-').toLowerCase();
    if (!name || folders.value.includes(name)) return;
    folders.value.push(name);
    activeFolder.value = name;
    mediaItems.value = [];
    newFolderName.value = '';
    showNewFolder.value = false;
}

function toggleSelection(item: MediaItem) {
    if (selectedIds.value.includes(item.id)) {
        selectedIds.value = selectedIds.value.filter((id) => id !== item.id);
        return;
    }

    selectedIds.value = [...selectedIds.value, item.id];
}

function clearSelection() {
    selectedIds.value = [];
}

function selectAllVisible() {
    selectedIds.value = filteredItems.value.map((item) => item.id);
}

async function moveSelected(folder: string) {
    const items = [...selectedItems.value];

    for (const item of items) {
        await saveFolder(item, folder);
    }

    selectedIds.value = selectedIds.value.filter((id) =>
        mediaItems.value.some((item) => item.id === id),
    );
}

async function deleteSelected() {
    if (
        !selectedItems.value.length ||
        !confirm(`Delete ${selectedItems.value.length} selected file(s)?`)
    ) {
        return;
    }

    for (const item of [...selectedItems.value]) {
        await deleteMedia(item, false, true);
    }
}

watch(activeFolder, loadMedia);

function formatSize(bytes: number): string {
    if (bytes < 1024) return `${bytes} B`;
    if (bytes < 1024 * 1024) return `${(bytes / 1024).toFixed(1)} KB`;
    return `${(bytes / (1024 * 1024)).toFixed(1)} MB`;
}

function formatDate(value: string): string {
    return new Date(value).toLocaleString();
}

function copyUrl(url: string) {
    navigator.clipboard.writeText(url).catch(() => {});
}

async function extractError(res: Response, fallback: string): Promise<string> {
    const contentType = res.headers.get('content-type') ?? '';

    if (contentType.includes('application/json')) {
        const payload = await res.json();
        const errors = payload?.errors
            ? Object.values(payload.errors).flat()
            : [];
        const firstError = errors.find((value) => typeof value === 'string');

        if (typeof firstError === 'string' && firstError.length > 0) {
            return firstError;
        }

        if (
            typeof payload?.message === 'string' &&
            payload.message.length > 0
        ) {
            return payload.message;
        }
    }

    return fallback;
}

function itemKindLabel(item: MediaItem): string {
    switch (item.kind) {
        case 'image':
            return 'Image';
        case 'video':
            return 'Video';
        case 'audio':
            return 'Audio';
        case 'pdf':
            return 'PDF';
        case 'document':
            return 'Document';
        case 'archive':
            return 'Archive';
        default:
            return 'File';
    }
}

function itemIcon(item: MediaItem) {
    switch (item.kind) {
        case 'image':
            return ImageIcon;
        case 'video':
            return Video;
        case 'audio':
            return Music;
        case 'pdf':
        case 'document':
            return FileText;
        case 'archive':
            return Archive;
        default:
            return File;
    }
}

function isImage(item: MediaItem | null): boolean {
    return item?.kind === 'image';
}

function isVideo(item: MediaItem | null): boolean {
    return item?.kind === 'video';
}

function isAudio(item: MediaItem | null): boolean {
    return item?.kind === 'audio';
}

function isPdf(item: MediaItem | null): boolean {
    return item?.kind === 'pdf';
}
</script>

<template>
    <AppLayout :breadcrumbs="breadcrumbs">
        <div
            class="relative flex h-[calc(100vh-6rem)] overflow-hidden rounded-lg border bg-background shadow-sm"
        >
            <aside class="flex w-48 shrink-0 flex-col border-r">
                <div
                    class="flex items-center justify-between border-b px-3 py-3"
                >
                    <span class="text-sm font-semibold">Folders</span>
                    <button
                        class="rounded p-0.5 text-muted-foreground hover:text-foreground"
                        title="New folder"
                        @click="showNewFolder = !showNewFolder"
                    >
                        <span class="text-lg leading-none">+</span>
                    </button>
                </div>

                <div v-if="showNewFolder" class="px-2 py-2">
                    <div class="flex gap-1">
                        <input
                            v-model="newFolderName"
                            placeholder="folder-name"
                            class="min-w-0 flex-1 rounded border bg-background px-2 py-1 text-xs focus:ring-1 focus:ring-ring focus:outline-none"
                            @keydown.enter="createFolder"
                            @keydown.esc="showNewFolder = false"
                        />
                        <button
                            class="rounded bg-primary px-2 py-1 text-xs text-primary-foreground"
                            @click="createFolder"
                        >
                            Add
                        </button>
                    </div>
                </div>

                <nav class="flex flex-col gap-0.5 overflow-y-auto px-1 py-1">
                    <button
                        v-for="folder in folders"
                        :key="folder"
                        class="flex items-center gap-2 rounded-md px-2 py-2 text-left text-sm transition-colors"
                        :class="
                            activeFolder === folder
                                ? 'bg-primary text-primary-foreground'
                                : 'text-muted-foreground hover:bg-accent hover:text-foreground'
                        "
                        @click="activeFolder = folder"
                    >
                        <FolderOpen class="h-4 w-4 shrink-0" />
                        <span class="truncate">{{ folder }}</span>
                    </button>
                </nav>
            </aside>

            <div class="flex min-w-0 flex-1 flex-col">
                <div class="flex shrink-0 flex-col gap-3 border-b px-4 py-3">
                    <div class="flex flex-wrap items-center gap-3">
                        <div class="relative max-w-sm flex-1">
                            <Search
                                class="absolute top-1/2 left-3 h-4 w-4 -translate-y-1/2 text-muted-foreground"
                            />
                            <Input
                                v-model="search"
                                placeholder="Search media…"
                                class="pl-9"
                            />
                        </div>
                        <label class="cursor-pointer">
                            <input
                                type="file"
                                multiple
                                :accept="uploadAccept"
                                class="sr-only"
                                @change="onFileInput"
                            />
                            <Button
                                variant="outline"
                                as="span"
                                :disabled="uploading"
                            >
                                <Upload class="mr-2 h-4 w-4" />
                                {{ uploading ? 'Uploading…' : 'Upload Files' }}
                            </Button>
                        </label>
                    </div>

                    <div
                        v-if="filteredItems.length"
                        class="flex flex-wrap items-center gap-2"
                    >
                        <Button
                            size="sm"
                            variant="outline"
                            @click="selectAllVisible"
                        >
                            Select visible
                        </Button>
                        <Button
                            size="sm"
                            variant="outline"
                            :disabled="!selectedIds.length"
                            @click="clearSelection"
                        >
                            Clear selection
                        </Button>
                        <span class="text-xs text-muted-foreground">
                            {{ selectedIds.length }} selected
                        </span>
                        <select
                            v-if="selectedIds.length"
                            class="rounded border bg-background px-2 py-1.5 text-xs"
                            @change="
                                moveSelected(
                                    ($event.target as HTMLSelectElement).value,
                                );
                                ($event.target as HTMLSelectElement).value = '';
                            "
                        >
                            <option value="">Move selected to...</option>
                            <option
                                v-for="folder in folders.filter(
                                    (folder) => folder !== activeFolder,
                                )"
                                :key="folder"
                                :value="folder"
                            >
                                {{ folder }}
                            </option>
                        </select>
                        <Button
                            v-if="selectedIds.length"
                            size="sm"
                            variant="destructive"
                            @click="deleteSelected"
                        >
                            Delete selected
                        </Button>
                    </div>

                    <div class="flex flex-wrap gap-2">
                        <button
                            v-for="filter in kindFilters"
                            :key="filter.value"
                            class="rounded-full border px-3 py-1 text-xs transition-colors"
                            :class="
                                activeKind === filter.value
                                    ? 'border-primary bg-primary text-primary-foreground'
                                    : 'hover:bg-accent'
                            "
                            @click="activeKind = filter.value"
                        >
                            {{ filter.label }}
                        </button>
                    </div>

                    <p class="text-xs text-muted-foreground">
                        Supports common images, video, audio, PDFs, office
                        files, text/CSV/JSON, and ZIP archives.
                    </p>
                    <p v-if="uploadError" class="text-xs text-destructive">
                        {{ uploadError }}
                    </p>
                </div>

                <div
                    class="relative flex-1 overflow-y-auto p-4"
                    :class="
                        isDragOver
                            ? 'bg-primary/5 ring-2 ring-primary ring-inset'
                            : ''
                    "
                    @dragover="onDragOver"
                    @dragleave="onDragLeave"
                    @drop="onDrop"
                >
                    <div
                        v-if="isDragOver"
                        class="pointer-events-none absolute inset-0 z-10 flex flex-col items-center justify-center gap-3"
                    >
                        <Upload class="h-12 w-12 text-primary" />
                        <p class="text-sm font-medium text-primary">
                            Drop files to upload to "{{ activeFolder }}"
                        </p>
                    </div>

                    <div
                        v-if="loading"
                        class="flex h-40 items-center justify-center"
                    >
                        <span class="text-sm text-muted-foreground"
                            >Loading…</span
                        >
                    </div>

                    <div
                        v-else-if="filteredItems.length === 0"
                        class="flex h-64 flex-col items-center justify-center gap-3 rounded-xl border-2 border-dashed border-muted-foreground/20 text-muted-foreground"
                    >
                        <ImageIcon class="h-12 w-12 opacity-30" />
                        <p class="text-sm">
                            {{
                                search
                                    ? `No results for "${search}"`
                                    : 'No files in this folder yet'
                            }}
                        </p>
                        <p class="text-xs">
                            Drag files here or click Upload Files
                        </p>
                    </div>

                    <div
                        v-else
                        class="grid grid-cols-3 gap-3 lg:grid-cols-4 xl:grid-cols-6 2xl:grid-cols-8"
                    >
                        <div
                            v-for="item in filteredItems"
                            :key="item.id"
                            class="group relative cursor-pointer overflow-hidden rounded-xl border-2 transition-all"
                            :class="
                                selectedIds.includes(item.id)
                                    ? 'border-primary shadow-lg'
                                    : 'border-transparent hover:border-muted-foreground/30'
                            "
                            @click="toggleSelection(item)"
                        >
                            <div class="aspect-square bg-muted/30">
                                <img
                                    v-if="isImage(item)"
                                    :src="item.url"
                                    :alt="item.alt || item.name"
                                    class="h-full w-full object-cover"
                                    loading="lazy"
                                />
                                <video
                                    v-else-if="isVideo(item)"
                                    :src="item.url"
                                    class="h-full w-full object-cover"
                                    muted
                                    playsinline
                                    preload="metadata"
                                />
                                <div
                                    v-else
                                    class="flex h-full flex-col items-center justify-center gap-2 px-3 text-center"
                                >
                                    <component
                                        :is="itemIcon(item)"
                                        class="h-8 w-8 text-muted-foreground/50"
                                    />
                                    <span
                                        class="rounded bg-background/80 px-2 py-0.5 text-[10px] font-medium tracking-wide text-muted-foreground uppercase"
                                    >
                                        {{
                                            item.extension ||
                                            itemKindLabel(item)
                                        }}
                                    </span>
                                </div>
                            </div>

                            <div
                                v-if="selectedIds.includes(item.id)"
                                class="absolute top-1.5 right-1.5 rounded-full bg-primary text-primary-foreground"
                            >
                                <CheckCircle2 class="h-4 w-4" />
                            </div>

                            <div
                                class="absolute top-1.5 left-1.5 hidden gap-1 group-hover:flex"
                            >
                                <button
                                    class="rounded bg-destructive/90 p-1 text-destructive-foreground"
                                    title="Delete"
                                    @click.stop="deleteMedia(item)"
                                >
                                    <Trash2 class="h-3 w-3" />
                                </button>
                            </div>

                            <div class="space-y-1 px-1.5 py-1">
                                <p class="truncate text-xs text-foreground">
                                    {{ item.name }}
                                </p>
                                <p
                                    class="truncate text-[11px] text-muted-foreground"
                                >
                                    {{ itemKindLabel(item) }}
                                </p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <aside
                v-if="detailItem"
                class="absolute inset-y-0 right-0 z-20 flex w-72 flex-col border-l bg-background shadow-xl"
            >
                <div
                    class="flex items-center justify-between border-b px-4 py-3"
                >
                    <span class="text-sm font-semibold">File Details</span>
                    <button
                        class="text-muted-foreground hover:text-foreground"
                        @click="clearSelection"
                    >
                        <X class="h-4 w-4" />
                    </button>
                </div>

                <div class="flex flex-col gap-4 overflow-y-auto p-4 text-sm">
                    <div
                        class="aspect-square overflow-hidden rounded-lg bg-muted/30"
                    >
                        <img
                            v-if="isImage(detailItem)"
                            :src="detailItem.url"
                            :alt="detailItem.alt || detailItem.name"
                            class="h-full w-full object-contain"
                        />
                        <video
                            v-else-if="isVideo(detailItem)"
                            :src="detailItem.url"
                            class="h-full w-full bg-black object-contain"
                            controls
                            playsinline
                            preload="metadata"
                        />
                        <div
                            v-else-if="isAudio(detailItem)"
                            class="flex h-full flex-col items-center justify-center gap-4 p-4"
                        >
                            <Music class="h-10 w-10 text-muted-foreground/50" />
                            <audio
                                :src="detailItem.url"
                                class="w-full"
                                controls
                                preload="metadata"
                            />
                        </div>
                        <iframe
                            v-else-if="isPdf(detailItem)"
                            :src="detailItem.url"
                            class="h-full w-full bg-white"
                            title="PDF preview"
                        />
                        <div
                            v-else
                            class="flex h-full flex-col items-center justify-center gap-3 px-4 text-center"
                        >
                            <component
                                :is="itemIcon(detailItem)"
                                class="h-12 w-12 text-muted-foreground/50"
                            />
                            <div>
                                <p class="text-sm font-medium">
                                    {{ itemKindLabel(detailItem) }}
                                </p>
                                <p
                                    class="text-xs text-muted-foreground uppercase"
                                >
                                    {{ detailItem.extension || 'file' }}
                                </p>
                            </div>
                        </div>
                    </div>

                    <div class="flex gap-2">
                        <a
                            :href="detailItem.url"
                            target="_blank"
                            rel="noreferrer"
                            class="flex-1"
                        >
                            <Button variant="outline" size="sm" class="w-full"
                                >Open</Button
                            >
                        </a>
                        <a
                            :href="detailItem.download_url"
                            target="_blank"
                            rel="noreferrer"
                            class="flex-1"
                        >
                            <Button variant="outline" size="sm" class="w-full"
                                >Download</Button
                            >
                        </a>
                    </div>

                    <div class="flex flex-col gap-1">
                        <span class="text-xs text-muted-foreground">Name</span>
                        <template v-if="editingName?.id === detailItem.id">
                            <div class="flex gap-1">
                                <input
                                    v-model="editingName.value"
                                    class="min-w-0 flex-1 rounded border bg-background px-2 py-1 text-xs focus:ring-1 focus:ring-ring focus:outline-none"
                                    @keydown.enter="saveName"
                                    @keydown.esc="editingName = null"
                                />
                                <button
                                    class="rounded bg-primary px-2 py-1 text-xs text-primary-foreground"
                                    @click="saveName"
                                >
                                    Save
                                </button>
                            </div>
                        </template>
                        <div v-else class="flex items-center gap-1">
                            <span
                                class="flex-1 text-xs font-medium break-all"
                                >{{ detailItem.name }}</span
                            >
                            <button
                                class="rounded p-1 text-muted-foreground hover:text-foreground"
                                @click="
                                    editingName = {
                                        id: detailItem.id,
                                        value: detailItem.name,
                                    }
                                "
                            >
                                <Pencil class="h-3 w-3" />
                            </button>
                        </div>
                    </div>

                    <div class="grid grid-cols-2 gap-3">
                        <div class="flex flex-col gap-0.5">
                            <span class="text-xs text-muted-foreground"
                                >Type</span
                            >
                            <span class="text-xs">{{
                                itemKindLabel(detailItem)
                            }}</span>
                        </div>
                        <div class="flex flex-col gap-0.5">
                            <span class="text-xs text-muted-foreground"
                                >Extension</span
                            >
                            <span class="text-xs uppercase">{{
                                detailItem.extension || '-'
                            }}</span>
                        </div>
                        <div class="flex flex-col gap-0.5">
                            <span class="text-xs text-muted-foreground"
                                >Size</span
                            >
                            <span class="text-xs">{{
                                formatSize(detailItem.size)
                            }}</span>
                        </div>
                        <div class="flex flex-col gap-0.5">
                            <span class="text-xs text-muted-foreground"
                                >Added</span
                            >
                            <span class="text-xs">{{
                                formatDate(detailItem.created_at)
                            }}</span>
                        </div>
                    </div>

                    <div class="flex flex-col gap-0.5">
                        <span class="text-xs text-muted-foreground"
                            >MIME type</span
                        >
                        <span class="text-xs break-all">{{
                            detailItem.mime_type
                        }}</span>
                    </div>

                    <div class="flex flex-col gap-1">
                        <span class="text-xs text-muted-foreground"
                            >Folder</span
                        >
                        <select
                            :value="detailItem.folder"
                            class="rounded border bg-background px-2 py-1.5 text-xs"
                            @change="
                                saveFolder(
                                    detailItem,
                                    ($event.target as HTMLSelectElement).value,
                                )
                            "
                        >
                            <option
                                v-for="folder in folders"
                                :key="folder"
                                :value="folder"
                            >
                                {{ folder }}
                            </option>
                        </select>
                    </div>

                    <div class="flex flex-col gap-1">
                        <div class="flex items-center justify-between">
                            <span class="text-xs text-muted-foreground"
                                >Usage</span
                            >
                            <span class="text-xs text-muted-foreground">{{
                                detailItem.usage_count
                            }}</span>
                        </div>
                        <div
                            v-if="detailItem.usage_count === 0"
                            class="rounded border border-dashed px-2 py-2 text-xs text-muted-foreground"
                        >
                            No known CMS references.
                        </div>
                        <div v-else class="space-y-2">
                            <a
                                v-for="reference in detailItem.usage"
                                :key="`${reference.type}-${reference.href}-${reference.label}`"
                                :href="reference.href"
                                class="block rounded border px-2 py-2 text-xs transition-colors hover:bg-accent"
                            >
                                <p class="font-medium">{{ reference.label }}</p>
                                <p class="text-muted-foreground">
                                    {{ reference.context }}
                                </p>
                            </a>
                        </div>
                    </div>

                    <div
                        v-if="detailItem.kind === 'image'"
                        class="flex flex-col gap-1"
                    >
                        <span class="text-xs text-muted-foreground"
                            >Alt text</span
                        >
                        <template v-if="editingAlt?.id === detailItem.id">
                            <textarea
                                v-model="editingAlt.value"
                                rows="2"
                                placeholder="Describe this image…"
                                class="w-full resize-none rounded border bg-background px-2 py-1 text-xs focus:ring-1 focus:ring-ring focus:outline-none"
                                @keydown.enter.prevent="saveAlt"
                                @keydown.esc="editingAlt = null"
                            />
                            <button
                                class="rounded bg-primary px-2 py-1 text-xs text-primary-foreground"
                                @click="saveAlt"
                            >
                                Save
                            </button>
                        </template>
                        <button
                            v-else
                            class="rounded border px-2 py-1.5 text-left text-xs text-muted-foreground hover:bg-accent"
                            @click="
                                editingAlt = {
                                    id: detailItem.id,
                                    value: detailItem.alt,
                                }
                            "
                        >
                            {{ detailItem.alt || '+ Add alt text' }}
                        </button>
                    </div>

                    <div class="flex flex-col gap-1">
                        <span class="text-xs text-muted-foreground">URL</span>
                        <div class="flex gap-1">
                            <input
                                :value="detailItem.url"
                                readonly
                                class="min-w-0 flex-1 rounded border bg-muted/30 px-2 py-1 text-xs text-muted-foreground"
                            />
                            <button
                                class="rounded border px-2 py-1 text-xs hover:bg-accent"
                                title="Copy URL"
                                @click="copyUrl(detailItem.url)"
                            >
                                Copy
                            </button>
                        </div>
                    </div>

                    <Button
                        variant="destructive"
                        size="sm"
                        class="w-full"
                        @click="deleteMedia(detailItem)"
                    >
                        <Trash2 class="mr-2 h-3.5 w-3.5" />
                        Delete
                    </Button>
                </div>
            </aside>
        </div>
    </AppLayout>
</template>
