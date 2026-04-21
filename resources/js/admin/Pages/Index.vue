<script setup lang="ts">
import { router } from '@inertiajs/vue3';
import {
    CheckCircle2,
    CheckSquare,
    FilePlus,
    Loader2,
    Trash2,
} from 'lucide-vue-next';
import { computed, provide, ref, watch } from 'vue';
import { Button } from '@/components/ui/button';
import AppLayout from '@/layouts/AppLayout.vue';
import type { BreadcrumbItem } from '@/types';
import PageTreeList, { type PageNode } from './partials/PageTreeList.vue';

interface FlatPage {
    id: number;
    title: string;
    slug: string;
    type: string;
    is_published: boolean;
    is_homepage: boolean;
    parent_id: number | null;
    order: number;
    published_at: string | null;
    created_at: string;
}

const props = defineProps<{ pages: FlatPage[] }>();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Pages', href: '/admin/pages' },
];

// ── Build tree from flat list ─────────────────────────────────────────────────

function hydrate(flat: FlatPage[], parentId: number | null = null): PageNode[] {
    return flat
        .filter((p) => p.parent_id === parentId)
        .sort((a, b) => a.order - b.order)
        .map((p) => ({ ...p, children: hydrate(flat, p.id), _expanded: true }));
}

const items = ref<PageNode[]>(hydrate(props.pages));

// Re-hydrate the tree whenever Inertia reloads props (e.g. after delete/bulk action)
watch(
    () => props.pages,
    (newPages) => {
        items.value = hydrate(newPages);
    },
);

// ── Selection (provided to tree) ─────────────────────────────────────────────

const selectedIds = ref<Set<number>>(new Set());

function toggleSelected(id: number) {
    const s = new Set(selectedIds.value);

    if (s.has(id)) {
        s.delete(id);
    } else {
        s.add(id);
    }

    selectedIds.value = s;
}

const allIds = computed(() => props.pages.map((p) => p.id));
const allSelected = computed(
    () =>
        allIds.value.length > 0 &&
        allIds.value.every((id) => selectedIds.value.has(id)),
);

function toggleAll() {
    selectedIds.value = allSelected.value ? new Set() : new Set(allIds.value);
}

provide('selectedIds', selectedIds);
provide('toggleSelected', toggleSelected);

// ── Dirty / reorder ───────────────────────────────────────────────────────────

const saving = ref(false);
const savedFlash = ref(false);
let savedTimer: ReturnType<typeof setTimeout> | null = null;

function getCsrf() {
    return decodeURIComponent(
        document.cookie
            .split('; ')
            .find((c) => c.startsWith('XSRF-TOKEN='))
            ?.split('=')[1] ?? '',
    );
}

function serializeTree(
    nodes: PageNode[],
): { id: number; children: object[] }[] {
    return nodes.map(({ id, children }) => ({
        id,
        children: serializeTree(children),
    }));
}

async function autoSave() {
    if (saving.value) return;
    saving.value = true;
    const res = await fetch('/admin/pages/reorder', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-XSRF-TOKEN': getCsrf(),
        },
        body: JSON.stringify({ pages: serializeTree(items.value) }),
    });
    const data = await res.json();
    if (data.slugs) applySlugUpdates(items.value, data.slugs);
    saving.value = false;
    savedFlash.value = true;
    if (savedTimer) clearTimeout(savedTimer);
    savedTimer = setTimeout(() => (savedFlash.value = false), 2000);
}

function applySlugUpdates(nodes: PageNode[], slugs: Record<number, string>) {
    for (const node of nodes) {
        if (slugs[node.id] !== undefined) node.slug = slugs[node.id];
        if (node.children.length) applySlugUpdates(node.children, slugs);
    }
}

provide('markDirty', autoSave);

// ── Bulk actions ─────────────────────────────────────────────────────────────

const bulkProcessing = ref(false);
const creating = ref(false);

function bulkAction(action: 'delete' | 'publish' | 'unpublish') {
    if (!selectedIds.value.size) return;
    if (
        action === 'delete' &&
        !confirm(
            `Delete ${selectedIds.value.size} page(s)? This cannot be undone.`,
        )
    )
        return;

    bulkProcessing.value = true;
    router.post(
        '/admin/pages/bulk',
        { action, ids: [...selectedIds.value] },
        {
            onFinish: () => {
                bulkProcessing.value = false;
                selectedIds.value = new Set();
            },
        },
    );
}

function createPage() {
    if (creating.value) return;

    router.post(
        '/admin/pages/quick-create',
        {},
        {
            preserveScroll: true,
            onStart: () => {
                creating.value = true;
            },
            onFinish: () => {
                creating.value = false;
            },
        },
    );
}
</script>

<template>
    <AppLayout :breadcrumbs="breadcrumbs">
        <!-- Header -->
        <div class="flex items-center justify-between">
            <div class="flex items-center gap-3">
                <h1 class="text-2xl font-semibold tracking-tight">Pages</h1>
                <div class="flex items-center gap-1.5">
                    <input
                        type="checkbox"
                        class="h-4 w-4 cursor-pointer rounded accent-primary"
                        :checked="allSelected"
                        @change="toggleAll"
                        title="Select all"
                    />
                </div>
            </div>
            <div class="flex items-center gap-2">
                <Transition
                    enter-active-class="transition-all duration-200"
                    enter-from-class="opacity-0 scale-90"
                    leave-active-class="transition-all duration-200"
                    leave-to-class="opacity-0 scale-90"
                >
                    <span
                        v-if="saving"
                        class="flex items-center gap-1 text-xs text-muted-foreground"
                    >
                        <Loader2 class="h-3.5 w-3.5 animate-spin" /> Saving…
                    </span>
                    <span
                        v-else-if="savedFlash"
                        class="flex items-center gap-1 text-xs text-muted-foreground"
                    >
                        <CheckCircle2 class="h-3.5 w-3.5 text-green-500" />
                        Saved
                    </span>
                </Transition>
                <Button :disabled="creating" @click="createPage">
                    <Loader2
                        v-if="creating"
                        class="mr-2 h-4 w-4 animate-spin"
                    />
                    <FilePlus v-else class="mr-2 h-4 w-4" />
                    New Page
                </Button>
            </div>
        </div>

        <!-- Bulk action toolbar -->
        <Transition
            enter-active-class="transition-all duration-150"
            enter-from-class="opacity-0 -translate-y-2"
            leave-active-class="transition-all duration-100"
            leave-to-class="opacity-0 -translate-y-2"
        >
            <div
                v-if="selectedIds.size > 0"
                class="flex items-center gap-3 rounded-lg border bg-accent px-4 py-2.5"
            >
                <CheckSquare class="h-4 w-4 text-muted-foreground" />
                <span class="text-sm font-medium"
                    >{{ selectedIds.size }} selected</span
                >
                <div class="ml-auto flex items-center gap-2">
                    <Button
                        size="sm"
                        variant="outline"
                        :disabled="bulkProcessing"
                        @click="bulkAction('publish')"
                        >Publish</Button
                    >
                    <Button
                        size="sm"
                        variant="outline"
                        :disabled="bulkProcessing"
                        @click="bulkAction('unpublish')"
                        >Unpublish</Button
                    >
                    <Button
                        size="sm"
                        variant="destructive"
                        :disabled="bulkProcessing"
                        @click="bulkAction('delete')"
                    >
                        <Loader2
                            v-if="bulkProcessing"
                            class="mr-1.5 h-3.5 w-3.5 animate-spin"
                        />
                        <Trash2 v-else class="mr-1.5 h-3.5 w-3.5" />
                        Delete
                    </Button>
                </div>
            </div>
        </Transition>

        <!-- Tree -->
        <div
            v-if="items.length === 0"
            class="flex min-h-48 items-center justify-center rounded-lg border border-dashed text-sm text-muted-foreground"
        >
            No pages yet.
            <button
                type="button"
                class="ml-1 underline"
                :disabled="creating"
                @click="createPage"
            >
                Create one.
            </button>
        </div>

        <PageTreeList v-else v-model="items" />
    </AppLayout>
</template>
