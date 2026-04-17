<script setup lang="ts">
import {
    CheckCircle2,
    FolderOpen,
    Image as ImageIcon,
    Pencil,
    Search,
    Trash2,
    Upload,
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
    size: number;
    url: string;
    folder: string;
    alt: string;
    created_at: string;
}

const props = defineProps<{
    folders: string[];
    initialMedia: MediaItem[];
}>();

const breadcrumbs = [
    { title: 'Media Library', href: '/admin/media' },
];

// ── State ─────────────────────────────────────────────────────────────────────
const folders = ref<string[]>(props.folders.length ? props.folders : ['uploads']);
const activeFolder = ref(folders.value[0] ?? 'uploads');
const mediaItems = ref<MediaItem[]>(props.initialMedia);
const search = ref('');
const selectedItem = ref<MediaItem | null>(null);
const loading = ref(false);
const uploading = ref(false);
const isDragOver = ref(false);
const editingAlt = ref<{ id: number; value: string } | null>(null);
const editingName = ref<{ id: number; value: string } | null>(null);
const newFolderName = ref('');
const showNewFolder = ref(false);

const filteredItems = computed(() => {
    if (!search.value.trim()) return mediaItems.value;
    const q = search.value.toLowerCase();
    return mediaItems.value.filter(
        (m) => m.name.toLowerCase().includes(q) || m.file_name.toLowerCase().includes(q),
    );
});

// ── CSRF ──────────────────────────────────────────────────────────────────────
function getCsrfToken(): string {
    return (document.cookie.match(/XSRF-TOKEN=([^;]+)/) ?? [])[1]
        ? decodeURIComponent((document.cookie.match(/XSRF-TOKEN=([^;]+)/) ?? [])[1])
        : (document.querySelector('meta[name="csrf-token"]') as HTMLMetaElement)?.content ?? '';
}
const reqHeaders = () => ({ 'X-XSRF-TOKEN': getCsrfToken() });

// ── API ───────────────────────────────────────────────────────────────────────
async function loadMedia(folder = activeFolder.value) {
    loading.value = true;
    try {
        const res = await fetch(`/admin/media/list?folder=${encodeURIComponent(folder)}&per_page=80`, {
            headers: reqHeaders(),
        });
        if (res.ok) {
            const data = await res.json();
            mediaItems.value = data.data;
        }
    } finally {
        loading.value = false;
    }
}

async function uploadFiles(files: FileList | File[]) {
    uploading.value = true;
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
            }
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

async function deleteMedia(item: MediaItem) {
    if (!confirm(`Delete "${item.name}"?`)) return;
    const res = await fetch(`/admin/media/${item.id}`, {
        method: 'DELETE',
        headers: reqHeaders(),
    });
    if (res.ok) {
        mediaItems.value = mediaItems.value.filter((m) => m.id !== item.id);
        if (selectedItem.value?.id === item.id) selectedItem.value = null;
    }
}

function syncItem(updated: MediaItem) {
    const idx = mediaItems.value.findIndex((m) => m.id === updated.id);
    if (idx !== -1) mediaItems.value[idx] = updated;
    if (selectedItem.value?.id === updated.id) selectedItem.value = updated;
}

// ── DnD upload ────────────────────────────────────────────────────────────────
function onDragOver(e: DragEvent) { e.preventDefault(); isDragOver.value = true; }
function onDragLeave() { isDragOver.value = false; }
async function onDrop(e: DragEvent) {
    e.preventDefault();
    isDragOver.value = false;
    if (e.dataTransfer?.files.length) await uploadFiles(e.dataTransfer.files);
}
function onFileInput(e: Event) {
    const files = (e.target as HTMLInputElement).files;
    if (files?.length) uploadFiles(files);
}

// ── Folder creation ───────────────────────────────────────────────────────────
function createFolder() {
    const name = newFolderName.value.trim().replace(/\s+/g, '-').toLowerCase();
    if (!name || folders.value.includes(name)) return;
    folders.value.push(name);
    activeFolder.value = name;
    mediaItems.value = [];
    newFolderName.value = '';
    showNewFolder.value = false;
}

watch(activeFolder, loadMedia);

function formatSize(bytes: number): string {
    if (bytes < 1024) return `${bytes} B`;
    if (bytes < 1024 * 1024) return `${(bytes / 1024).toFixed(1)} KB`;
    return `${(bytes / (1024 * 1024)).toFixed(1)} MB`;
}

function copyUrl(url: string) {
    navigator.clipboard.writeText(url).catch(() => {});
}
</script>

<template>
    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex h-[calc(100vh-6rem)] overflow-hidden rounded-lg border bg-background shadow-sm">
            <!-- Folder sidebar -->
            <aside class="flex w-48 shrink-0 flex-col border-r">
                <div class="flex items-center justify-between border-b px-3 py-3">
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
                            class="min-w-0 flex-1 rounded border bg-background px-2 py-1 text-xs focus:outline-none focus:ring-1 focus:ring-ring"
                            @keydown.enter="createFolder"
                            @keydown.esc="showNewFolder = false"
                        />
                        <button
                            class="rounded bg-primary px-2 py-1 text-xs text-primary-foreground"
                            @click="createFolder"
                        >Add</button>
                    </div>
                </div>

                <nav class="flex flex-col gap-0.5 overflow-y-auto px-1 py-1">
                    <button
                        v-for="folder in folders"
                        :key="folder"
                        class="flex items-center gap-2 rounded-md px-2 py-2 text-left text-sm transition-colors"
                        :class="activeFolder === folder
                            ? 'bg-primary text-primary-foreground'
                            : 'text-muted-foreground hover:bg-accent hover:text-foreground'"
                        @click="activeFolder = folder"
                    >
                        <FolderOpen class="h-4 w-4 shrink-0" />
                        <span class="truncate">{{ folder }}</span>
                    </button>
                </nav>
            </aside>

            <!-- Main area -->
            <div class="flex min-w-0 flex-1 flex-col">
                <!-- Toolbar -->
                <div class="flex shrink-0 items-center gap-3 border-b px-4 py-3">
                    <div class="relative flex-1 max-w-sm">
                        <Search class="absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-muted-foreground" />
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
                            accept="image/*,video/*,application/pdf"
                            class="sr-only"
                            @change="onFileInput"
                        />
                        <Button variant="outline" as="span" :disabled="uploading">
                            <Upload class="mr-2 h-4 w-4" />
                            {{ uploading ? 'Uploading…' : 'Upload Files' }}
                        </Button>
                    </label>
                </div>

                <!-- Drop zone / grid -->
                <div
                    class="relative flex-1 overflow-y-auto p-4"
                    :class="isDragOver ? 'ring-2 ring-inset ring-primary bg-primary/5' : ''"
                    @dragover="onDragOver"
                    @dragleave="onDragLeave"
                    @drop="onDrop"
                >
                    <!-- Drop overlay -->
                    <div
                        v-if="isDragOver"
                        class="pointer-events-none absolute inset-0 z-10 flex flex-col items-center justify-center gap-3"
                    >
                        <Upload class="h-12 w-12 text-primary" />
                        <p class="text-sm font-medium text-primary">Drop files to upload to "{{ activeFolder }}"</p>
                    </div>

                    <div v-if="loading" class="flex h-40 items-center justify-center">
                        <span class="text-sm text-muted-foreground">Loading…</span>
                    </div>

                    <div
                        v-else-if="filteredItems.length === 0"
                        class="flex h-64 flex-col items-center justify-center gap-3 rounded-xl border-2 border-dashed border-muted-foreground/20 text-muted-foreground"
                    >
                        <ImageIcon class="h-12 w-12 opacity-30" />
                        <p class="text-sm">
                            {{ search ? `No results for "${search}"` : 'No files in this folder yet' }}
                        </p>
                        <p class="text-xs">Drag files here or click Upload Files</p>
                    </div>

                    <div v-else class="grid grid-cols-4 gap-3 xl:grid-cols-6 2xl:grid-cols-8">
                        <div
                            v-for="item in filteredItems"
                            :key="item.id"
                            class="group relative cursor-pointer overflow-hidden rounded-xl border-2 transition-all"
                            :class="selectedItem?.id === item.id
                                ? 'border-primary shadow-lg'
                                : 'border-transparent hover:border-muted-foreground/30'"
                            @click="selectedItem = selectedItem?.id === item.id ? null : item"
                        >
                            <div class="aspect-square bg-muted/30">
                                <img
                                    v-if="item.mime_type?.startsWith('image/')"
                                    :src="item.url"
                                    :alt="item.alt || item.name"
                                    class="h-full w-full object-cover"
                                    loading="lazy"
                                />
                                <div v-else class="flex h-full items-center justify-center">
                                    <ImageIcon class="h-8 w-8 text-muted-foreground/40" />
                                </div>
                            </div>

                            <!-- Selected badge -->
                            <div
                                v-if="selectedItem?.id === item.id"
                                class="absolute right-1.5 top-1.5 rounded-full bg-primary text-primary-foreground"
                            >
                                <CheckCircle2 class="h-4 w-4" />
                            </div>

                            <!-- Hover actions -->
                            <div class="absolute left-1.5 top-1.5 hidden gap-1 group-hover:flex">
                                <button
                                    class="rounded bg-destructive/90 p-1 text-destructive-foreground"
                                    title="Delete"
                                    @click.stop="deleteMedia(item)"
                                >
                                    <Trash2 class="h-3 w-3" />
                                </button>
                            </div>

                            <p class="truncate px-1.5 py-1 text-xs text-muted-foreground">{{ item.name }}</p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Detail panel -->
            <aside
                v-if="selectedItem"
                class="flex w-64 shrink-0 flex-col border-l"
            >
                <div class="flex items-center justify-between border-b px-4 py-3">
                    <span class="text-sm font-semibold">File Details</span>
                    <button class="text-muted-foreground hover:text-foreground" @click="selectedItem = null">
                        <X class="h-4 w-4" />
                    </button>
                </div>

                <div class="flex flex-col gap-4 overflow-y-auto p-4 text-sm">
                    <!-- Preview -->
                    <div class="aspect-square overflow-hidden rounded-lg bg-muted/30">
                        <img
                            v-if="selectedItem.mime_type?.startsWith('image/')"
                            :src="selectedItem.url"
                            :alt="selectedItem.alt || selectedItem.name"
                            class="h-full w-full object-contain"
                        />
                        <div v-else class="flex h-full items-center justify-center">
                            <ImageIcon class="h-10 w-10 text-muted-foreground/40" />
                        </div>
                    </div>

                    <!-- Name (editable) -->
                    <div class="flex flex-col gap-1">
                        <span class="text-xs text-muted-foreground">Name</span>
                        <template v-if="editingName?.id === selectedItem.id">
                            <div class="flex gap-1">
                                <input
                                    v-model="editingName.value"
                                    class="min-w-0 flex-1 rounded border bg-background px-2 py-1 text-xs focus:outline-none focus:ring-1 focus:ring-ring"
                                    @keydown.enter="saveName"
                                    @keydown.esc="editingName = null"
                                />
                                <button
                                    class="rounded bg-primary px-2 py-1 text-xs text-primary-foreground"
                                    @click="saveName"
                                >Save</button>
                            </div>
                        </template>
                        <div v-else class="flex items-center gap-1">
                            <span class="flex-1 break-all text-xs font-medium">{{ selectedItem.name }}</span>
                            <button
                                class="rounded p-1 text-muted-foreground hover:text-foreground"
                                @click="editingName = { id: selectedItem.id, value: selectedItem.name }"
                            >
                                <Pencil class="h-3 w-3" />
                            </button>
                        </div>
                    </div>

                    <!-- File name -->
                    <div class="flex flex-col gap-0.5">
                        <span class="text-xs text-muted-foreground">File</span>
                        <span class="break-all text-xs">{{ selectedItem.file_name }}</span>
                    </div>

                    <!-- Size -->
                    <div class="flex flex-col gap-0.5">
                        <span class="text-xs text-muted-foreground">Size</span>
                        <span class="text-xs">{{ formatSize(selectedItem.size) }}</span>
                    </div>

                    <!-- MIME -->
                    <div class="flex flex-col gap-0.5">
                        <span class="text-xs text-muted-foreground">Type</span>
                        <span class="text-xs">{{ selectedItem.mime_type }}</span>
                    </div>

                    <!-- Folder -->
                    <div class="flex flex-col gap-0.5">
                        <span class="text-xs text-muted-foreground">Folder</span>
                        <span class="text-xs">{{ selectedItem.folder }}</span>
                    </div>

                    <!-- Alt text (editable) -->
                    <div class="flex flex-col gap-1">
                        <span class="text-xs text-muted-foreground">Alt text</span>
                        <template v-if="editingAlt?.id === selectedItem.id">
                            <textarea
                                v-model="editingAlt.value"
                                rows="2"
                                placeholder="Describe this image…"
                                class="w-full resize-none rounded border bg-background px-2 py-1 text-xs focus:outline-none focus:ring-1 focus:ring-ring"
                                @keydown.enter.prevent="saveAlt"
                                @keydown.esc="editingAlt = null"
                            />
                            <button
                                class="rounded bg-primary px-2 py-1 text-xs text-primary-foreground"
                                @click="saveAlt"
                            >Save</button>
                        </template>
                        <button
                            v-else
                            class="rounded border px-2 py-1.5 text-left text-xs text-muted-foreground hover:bg-accent"
                            @click="editingAlt = { id: selectedItem.id, value: selectedItem.alt }"
                        >
                            {{ selectedItem.alt || '+ Add alt text' }}
                        </button>
                    </div>

                    <!-- URL copy -->
                    <div class="flex flex-col gap-1">
                        <span class="text-xs text-muted-foreground">URL</span>
                        <div class="flex gap-1">
                            <input
                                :value="selectedItem.url"
                                readonly
                                class="min-w-0 flex-1 rounded border bg-muted/30 px-2 py-1 text-xs text-muted-foreground"
                            />
                            <button
                                class="rounded border px-2 py-1 text-xs hover:bg-accent"
                                title="Copy URL"
                                @click="copyUrl(selectedItem.url)"
                            >Copy</button>
                        </div>
                    </div>

                    <Button
                        variant="destructive"
                        size="sm"
                        class="w-full"
                        @click="deleteMedia(selectedItem!)"
                    >
                        <Trash2 class="mr-2 h-3.5 w-3.5" />
                        Delete
                    </Button>
                </div>
            </aside>
        </div>
    </AppLayout>
</template>
