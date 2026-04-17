<script setup lang="ts">
import { computed, inject, type Ref } from 'vue';
import { VueDraggable } from 'vue-draggable-plus';
import { ChevronDown, ChevronRight, Eye, GripVertical, Home, Pencil, Trash2 } from 'lucide-vue-next';
import { Link, router } from '@inertiajs/vue3';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';

export interface PageNode {
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
    children: PageNode[];
    _expanded: boolean;
}

const props = defineProps<{
    modelValue: PageNode[];
    depth?: number;
}>();

const emit = defineEmits<{
    'update:modelValue': [PageNode[]];
}>();

const MAX_DEPTH = 4;
const depth = props.depth ?? 0;

const list = computed({
    get: () => props.modelValue,
    set: (v) => emit('update:modelValue', v),
});

// Provided by Index.vue for bulk selection
const selectedIds = inject<Ref<Set<number>>>('selectedIds')!;
const toggleSelected = inject<(id: number) => void>('toggleSelected')!;
const markDirty = inject<() => void>('markDirty')!;

function deletePage(page: PageNode) {
    if (!confirm(`Delete "${page.title}"? This cannot be undone.`)) return;
    router.delete(`/admin/pages/${page.id}`);
}
</script>

<template>
    <VueDraggable
        v-model="list"
        :group="{ name: 'pages', pull: true, put: depth < MAX_DEPTH }"
        handle=".page-drag-handle"
        :animation="150"
        ghost-class="opacity-30"
        :class="[
            depth > 0 ? 'ml-7 mt-1 space-y-1 border-l-2 border-border pl-3' : 'space-y-1.5',
            depth > 0 && list.length === 0 ? 'min-h-7' : '',
        ]"
        @end="markDirty()"
    >
        <div v-for="page in list" :key="page.id" class="rounded-lg border bg-card shadow-xs">
            <!-- Row -->
            <div class="flex items-center gap-2 px-3 py-2">
                <GripVertical
                    class="page-drag-handle h-4 w-4 shrink-0 cursor-grab text-muted-foreground/50 hover:text-muted-foreground active:cursor-grabbing"
                />

                <input
                    type="checkbox"
                    class="h-4 w-4 shrink-0 cursor-pointer rounded accent-primary"
                    :checked="selectedIds.has(page.id)"
                    @change="toggleSelected(page.id)"
                />

                <!-- Expand/collapse children toggle -->
                <button
                    v-if="page.children.length > 0"
                    class="flex h-5 w-5 shrink-0 items-center justify-center rounded text-muted-foreground hover:text-foreground transition-colors"
                    @click="page._expanded = !page._expanded"
                >
                    <ChevronDown v-if="page._expanded" class="h-3.5 w-3.5" />
                    <ChevronRight v-else class="h-3.5 w-3.5" />
                </button>
                <span v-else class="h-5 w-5 shrink-0" />

                <!-- Title -->
                <Link
                    :href="`/admin/pages/${page.id}/edit`"
                    class="min-w-0 flex-1 truncate rounded-sm text-sm font-medium transition-colors hover:text-primary hover:underline"
                >
                    <Home v-if="page.is_homepage" class="mr-1 inline h-3.5 w-3.5 text-muted-foreground" />
                    {{ page.title }}
                </Link>

                <span class="hidden font-mono text-xs text-muted-foreground sm:block">/{{ page.slug }}</span>

                <Badge
                    v-if="page.type && page.type !== 'page'"
                    variant="outline"
                    class="shrink-0 text-xs capitalize"
                >
                    {{ page.type }}
                </Badge>

                <Badge :variant="page.is_published ? 'default' : 'secondary'" class="shrink-0 text-xs">
                    {{ page.is_published ? 'Published' : 'Draft' }}
                </Badge>

                <!-- Actions -->
                <div class="flex shrink-0 items-center gap-0.5">
                    <Button variant="ghost" size="icon" class="h-7 w-7" as-child title="Preview">
                        <a :href="`/admin/pages/${page.id}/preview`" target="_blank" rel="noopener">
                            <Eye class="h-3.5 w-3.5" />
                        </a>
                    </Button>
                    <Button variant="ghost" size="icon" class="h-7 w-7" as-child title="Edit">
                        <Link :href="`/admin/pages/${page.id}/edit`">
                            <Pencil class="h-3.5 w-3.5" />
                        </Link>
                    </Button>
                    <Button
                        variant="ghost"
                        size="icon"
                        class="h-7 w-7 text-muted-foreground hover:text-destructive"
                        title="Delete"
                        @click="deletePage(page)"
                    >
                        <Trash2 class="h-3.5 w-3.5" />
                    </Button>
                </div>
            </div>

            <!--
                Children area - always rendered when depth allows, so empty pages
                can accept drops (SortableJS needs the container in the DOM).
                - Empty page: show dashed "nest here" drop zone
                - Non-empty + collapsed: still keep DOM for drops, visually hidden
                - Non-empty + expanded: show children normally
            -->
            <div
                v-if="depth < MAX_DEPTH"
                class="px-3 pb-2"
                :class="page.children.length > 0 && !page._expanded ? 'hidden' : ''"
            >
                <!-- Empty drop-zone hint (pointer-events-none so it doesn't block drops) -->
                <div
                    v-if="page.children.length === 0"
                    class="pointer-events-none flex min-h-7 items-center rounded border border-dashed border-border/40 pl-3 text-xs text-muted-foreground/40"
                >
                    ↳ drag a page here to nest it
                </div>

                <PageTreeList v-model="page.children" :depth="depth + 1" />
            </div>
        </div>
    </VueDraggable>
</template>
