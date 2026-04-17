<script setup lang="ts">
import { CheckCircle2, FolderOpen, Image as ImageIcon, Search, Trash2, Upload, X } from 'lucide-vue-next';
import { computed, onMounted, ref, watch } from 'vue';
import { Button } from '@/components/ui/button';
import { Dialog, DialogContent, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';

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
    open: boolean;
    multiple?: boolean;
}>();

const emit = defineEmits<{
    'update:open': [val: boolean];
    select: [item: MediaItem];
}>();

// ── State ─────────────────────────────────────────────────────────────────────
const folders = ref<string[]>(['uploads']);
const activeFolder = ref('uploads');
const mediaItems = ref<MediaItem[]>([]);
const search = ref('');
const selectedItem = ref<MediaItem | null>(null);
const loading = ref(false);
const uploading = ref(false);
const isDragOver = ref(false);
const editingAlt = ref<{ id: number; value: string } | null>(null);
const newFolderName = ref('');
const showNewFolder = ref(false);

// ── Computed ──────────────────────────────────────────────────────────────────
const filteredItems = computed(() => {
    if (!search.value.trim()) return mediaItems.value;
    const q = search.value.toLowerCase();
    return mediaItems.value.filter(
        (m) => m.name.toLowerCase().includes(q) || m.file_name.toLowerCase().includes(q),
    );
});

// ── CSRF helper ───────────────────────────────────────────────────────────────
function getCsrfToken(): string {
    return (document.cookie.match(/XSRF-TOKEN=([^;]+)/) ?? [])[1]
        ? decodeURIComponent((document.cookie.match(/XSRF-TOKEN=([^;]+)/) ?? [])[1])
        : (document.querySelector('meta[name="csrf-token"]') as HTMLMetaElement)?.content ?? '';
}

const headers = () => ({ 'X-XSRF-TOKEN': getCsrfToken() });

// ── API calls ─────────────────────────────────────────────────────────────────
async function loadFolders() {
    const res = await fetch('/admin/media/folders', { headers: headers() });
    if (res.ok) folders.value = await res.json();
}

async function loadMedia(folder = activeFolder.value) {
    loading.value = true;
    try {
        const url = `/admin/media/list?folder=${encodeURIComponent(folder)}&per_page=80`;
        const res = await fetch(url, { headers: headers() });
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
                headers: headers(),
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
        headers: { ...headers(), 'Content-Type': 'application/json' },
        body: JSON.stringify({ alt: value }),
    });
    if (res.ok) {
        const updated: MediaItem = await res.json();
        const idx = mediaItems.value.findIndex((m) => m.id === id);
        if (idx !== -1) mediaItems.value[idx] = updated;
        if (selectedItem.value?.id === id) selectedItem.value = updated;
    }
    editingAlt.value = null;
}

async function deleteMedia(item: MediaItem) {
    if (!confirm(`Delete "${item.name}"?`)) return;
    const res = await fetch(`/admin/media/${item.id}`, {
        method: 'DELETE',
        headers: headers(),
    });
    if (res.ok) {
        mediaItems.value = mediaItems.value.filter((m) => m.id !== item.id);
        if (selectedItem.value?.id === item.id) selectedItem.value = null;
    }
}

// ── Drag / drop upload ────────────────────────────────────────────────────────
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
    const files = (e.target as HTMLInputElement).files;
    if (files?.length) uploadFiles(files);
}

// ── Folder management ─────────────────────────────────────────────────────────
function createFolder() {
    const name = newFolderName.value.trim().replace(/\s+/g, '-').toLowerCase();
    if (!name || folders.value.includes(name)) return;
    folders.value.push(name);
    activeFolder.value = name;
    mediaItems.value = [];
    newFolderName.value = '';
    showNewFolder.value = false;
}

// ── Select & confirm ──────────────────────────────────────────────────────────
function selectItem(item: MediaItem) {
    selectedItem.value = selectedItem.value?.id === item.id ? null : item;
}

function confirm() {
    if (selectedItem.value) {
        emit('select', selectedItem.value);
        emit('update:open', false);
    }
}

// ── Watches ───────────────────────────────────────────────────────────────────
watch(() => props.open, async (val) => {
    if (val) {
        selectedItem.value = null;
        await loadFolders();
        await loadMedia(activeFolder.value);
    }
});

watch(activeFolder, (folder) => {
    selectedItem.value = null;
    loadMedia(folder);
});

function formatSize(bytes: number): string {
    if (bytes < 1024) return `${bytes} B`;
    if (bytes < 1024 * 1024) return `${(bytes / 1024).toFixed(1)} KB`;
    return `${(bytes / (1024 * 1024)).toFixed(1)} MB`;
}

onMounted(() => {
    if (props.open) {
        loadFolders();
        loadMedia();
    }
});
</script>

<template>
    <Dialog :open="open" @update:open="emit('update:open', $event)">
        <DialogContent class="flex h-[90vh] w-[90vw] max-w-none flex-col gap-0 overflow-hidden p-0 sm:max-w-none">
            <DialogHeader class="shrink-0 border-b px-4 py-3">
                <DialogTitle>Media Library</DialogTitle>
            </DialogHeader>

            <div class="flex min-h-0 flex-1">
                <!-- Folder sidebar -->
                <aside class="flex w-44 shrink-0 flex-col border-r bg-muted/30">
                    <div class="flex items-center justify-between px-3 py-2">
                        <span class="text-xs font-semibold uppercase tracking-wide text-muted-foreground">Folders</span>
                        <button
                            class="rounded p-0.5 text-muted-foreground transition-colors hover:text-foreground"
                            title="New folder"
                            @click="showNewFolder = !showNewFolder"
                        >
                            <span class="text-base leading-none">+</span>
                        </button>
                    </div>

                    <div v-if="showNewFolder" class="px-2 pb-2">
                        <input
                            v-model="newFolderName"
                            placeholder="folder-name"
                            class="w-full rounded border bg-background px-2 py-1 text-xs focus:outline-none focus:ring-1 focus:ring-ring"
                            @keydown.enter="createFolder"
                            @keydown.esc="showNewFolder = false"
                        />
                    </div>

                    <nav class="flex flex-col gap-0.5 overflow-y-auto px-1 py-1">
                        <button
                            v-for="folder in folders"
                            :key="folder"
                            class="flex items-center gap-2 rounded-md px-2 py-1.5 text-left text-sm transition-colors"
                            :class="activeFolder === folder
                                ? 'bg-primary text-primary-foreground'
                                : 'text-muted-foreground hover:bg-accent hover:text-foreground'"
                            @click="activeFolder = folder"
                        >
                            <FolderOpen class="h-3.5 w-3.5 shrink-0" />
                            {{ folder }}
                        </button>
                    </nav>
                </aside>

                <!-- Main content -->
                <div class="flex min-w-0 flex-1 flex-col">
                    <!-- Toolbar -->
                    <div class="flex shrink-0 items-center gap-2 border-b px-3 py-2">
                        <div class="relative flex-1">
                            <Search class="absolute left-2.5 top-1/2 h-3.5 w-3.5 -translate-y-1/2 text-muted-foreground" />
                            <input
                                v-model="search"
                                placeholder="Search media…"
                                class="h-8 w-full rounded-md border bg-background pl-8 pr-3 text-sm focus:outline-none focus:ring-1 focus:ring-ring"
                            />
                        </div>
                        <label class="cursor-pointer">
                            <input type="file" multiple accept="image/*,video/*,application/pdf" class="sr-only" @change="onFileInput" />
                            <Button size="sm" variant="outline" as="span" :disabled="uploading">
                                <Upload class="mr-1.5 h-3.5 w-3.5" />
                                {{ uploading ? 'Uploading…' : 'Upload' }}
                            </Button>
                        </label>
                    </div>

                    <!-- Grid + drop zone -->
                    <div
                        class="relative flex-1 overflow-y-auto p-3"
                        :class="isDragOver ? 'ring-2 ring-inset ring-primary bg-primary/5' : ''"
                        @dragover="onDragOver"
                        @dragleave="onDragLeave"
                        @drop="onDrop"
                    >
                        <!-- Drop overlay -->
                        <div
                            v-if="isDragOver"
                            class="pointer-events-none absolute inset-0 z-10 flex flex-col items-center justify-center gap-2"
                        >
                            <Upload class="h-10 w-10 text-primary" />
                            <p class="text-sm font-medium text-primary">Drop to upload to "{{ activeFolder }}"</p>
                        </div>

                        <!-- Loading -->
                        <div v-if="loading" class="flex h-32 items-center justify-center">
                            <span class="text-sm text-muted-foreground">Loading…</span>
                        </div>

                        <!-- Empty state -->
                        <div
                            v-else-if="filteredItems.length === 0"
                            class="flex h-48 flex-col items-center justify-center gap-2 text-muted-foreground"
                        >
                            <ImageIcon class="h-10 w-10 opacity-30" />
                            <p class="text-sm">
                                {{ search ? 'No results for "' + search + '"' : 'No files in this folder' }}
                            </p>
                            <p class="text-xs">Drag files here or click Upload</p>
                        </div>

                        <!-- Media grid -->
                        <div v-else class="grid grid-cols-4 gap-2 xl:grid-cols-5">
                            <div
                                v-for="item in filteredItems"
                                :key="item.id"
                                class="group relative cursor-pointer overflow-hidden rounded-lg border-2 transition-all"
                                :class="selectedItem?.id === item.id
                                    ? 'border-primary shadow-md'
                                    : 'border-transparent hover:border-muted-foreground/30'"
                                @click="selectItem(item)"
                            >
                                <!-- Thumbnail -->
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

                                <!-- Selected checkmark -->
                                <div
                                    v-if="selectedItem?.id === item.id"
                                    class="absolute right-1 top-1 rounded-full bg-primary text-primary-foreground"
                                >
                                    <CheckCircle2 class="h-4 w-4" />
                                </div>

                                <!-- Delete button -->
                                <button
                                    class="absolute left-1 top-1 hidden rounded bg-destructive/90 p-0.5 text-destructive-foreground group-hover:flex"
                                    @click.stop="deleteMedia(item)"
                                >
                                    <Trash2 class="h-3 w-3" />
                                </button>

                                <!-- Name -->
                                <p class="truncate px-1 py-0.5 text-xs text-muted-foreground">{{ item.name }}</p>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Detail panel for selected item -->
                <aside
                    v-if="selectedItem"
                    class="flex w-52 shrink-0 flex-col border-l"
                >
                    <div class="flex items-center justify-between border-b px-3 py-2">
                        <span class="text-xs font-semibold">Details</span>
                        <button class="text-muted-foreground hover:text-foreground" @click="selectedItem = null">
                            <X class="h-3.5 w-3.5" />
                        </button>
                    </div>
                    <div class="flex flex-col gap-3 overflow-y-auto p-3 text-xs">
                        <div class="aspect-square overflow-hidden rounded-lg bg-muted/30">
                            <img
                                v-if="selectedItem.mime_type?.startsWith('image/')"
                                :src="selectedItem.url"
                                :alt="selectedItem.alt || selectedItem.name"
                                class="h-full w-full object-contain"
                            />
                        </div>
                        <div class="flex flex-col gap-0.5">
                            <span class="text-muted-foreground">Name</span>
                            <span class="font-medium break-all">{{ selectedItem.file_name }}</span>
                        </div>
                        <div class="flex flex-col gap-0.5">
                            <span class="text-muted-foreground">Size</span>
                            <span>{{ formatSize(selectedItem.size) }}</span>
                        </div>
                        <div class="flex flex-col gap-0.5">
                            <span class="text-muted-foreground">Folder</span>
                            <span>{{ selectedItem.folder }}</span>
                        </div>
                        <div class="flex flex-col gap-1">
                            <span class="text-muted-foreground">Alt text</span>
                            <template v-if="editingAlt?.id === selectedItem.id">
                                <input
                                    v-model="editingAlt.value"
                                    class="w-full rounded border bg-background px-2 py-1 text-xs focus:outline-none focus:ring-1 focus:ring-ring"
                                    @keydown.enter="saveAlt"
                                    @keydown.esc="editingAlt = null"
                                />
                                <button
                                    class="rounded bg-primary px-2 py-1 text-xs text-primary-foreground"
                                    @click="saveAlt"
                                >Save</button>
                            </template>
                            <button
                                v-else
                                class="rounded border px-2 py-1 text-left text-xs text-muted-foreground hover:bg-accent"
                                @click="editingAlt = { id: selectedItem.id, value: selectedItem.alt }"
                            >
                                {{ selectedItem.alt || '+ Add alt text' }}
                            </button>
                        </div>
                    </div>
                </aside>
            </div>

            <!-- Footer -->
            <div class="flex shrink-0 items-center justify-end gap-2 border-t px-4 py-3">
                <Button variant="ghost" size="sm" @click="emit('update:open', false)">Cancel</Button>
                <Button size="sm" :disabled="!selectedItem" @click="confirm">
                    Insert Image
                </Button>
            </div>
        </DialogContent>
    </Dialog>
</template>
