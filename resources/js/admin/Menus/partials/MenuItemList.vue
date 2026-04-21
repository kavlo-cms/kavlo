<script setup lang="ts">
import { computed } from 'vue';
import { VueDraggable } from 'vue-draggable-plus';
import {
    ChevronDown,
    ChevronRight,
    ExternalLink,
    GripVertical,
    Trash2,
} from 'lucide-vue-next';
import { Input } from '@/components/ui/input';

export interface MenuItemNode {
    _id: string;
    id: number | null;
    label: string;
    url: string;
    page_id: number | null;
    target: '_self' | '_blank';
    children: MenuItemNode[];
    _open: boolean;
}

const props = defineProps<{
    modelValue: MenuItemNode[];
    depth?: number;
}>();

const emit = defineEmits<{
    'update:modelValue': [MenuItemNode[]];
    sort: [];
}>();

function onSort() {
    emit('sort');
}

const MAX_DEPTH = 3;
const depth = props.depth ?? 0;

const list = computed({
    get: () => props.modelValue,
    set: (v) => emit('update:modelValue', v),
});

function remove(index: number) {
    const next = [...list.value];
    next.splice(index, 1);
    list.value = next;
}
</script>

<template>
    <VueDraggable
        v-model="list"
        :group="{ name: 'menu-items', pull: true, put: depth < MAX_DEPTH }"
        handle=".drag-handle"
        :animation="150"
        ghost-class="opacity-30"
        :class="
            depth > 0
                ? 'mt-1 ml-7 space-y-1 border-l-2 border-border pl-3'
                : 'space-y-1.5'
        "
        @end="onSort"
    >
        <div
            v-for="(item, i) in list"
            :key="item._id"
            class="rounded-lg border bg-card shadow-xs"
        >
            <!-- Row header -->
            <div class="flex items-center gap-2 px-3 py-2.5">
                <GripVertical
                    class="drag-handle h-4 w-4 shrink-0 cursor-grab text-muted-foreground hover:text-foreground active:cursor-grabbing"
                />
                <span class="min-w-0 flex-1 truncate text-sm font-medium">
                    {{ item.label || 'Untitled' }}
                </span>
                <span
                    class="hidden max-w-[160px] truncate text-xs text-muted-foreground sm:block"
                >
                    {{ item.url || '' }}
                </span>
                <ExternalLink
                    v-if="item.target === '_blank'"
                    class="h-3.5 w-3.5 shrink-0 text-muted-foreground"
                    title="Opens in new tab"
                />
                <button
                    class="flex h-6 w-6 items-center justify-center rounded text-muted-foreground transition-colors hover:bg-accent hover:text-foreground"
                    :title="item._open ? 'Collapse' : 'Edit'"
                    @click="item._open = !item._open"
                >
                    <ChevronDown v-if="item._open" class="h-3.5 w-3.5" />
                    <ChevronRight v-else class="h-3.5 w-3.5" />
                </button>
                <button
                    class="flex h-6 w-6 items-center justify-center rounded text-muted-foreground transition-colors hover:bg-destructive/10 hover:text-destructive"
                    title="Remove"
                    @click="remove(i)"
                >
                    <Trash2 class="h-3.5 w-3.5" />
                </button>
            </div>

            <!-- Expanded settings -->
            <div
                v-if="item._open"
                class="space-y-3 border-t bg-muted/30 px-4 py-3"
            >
                <div class="grid grid-cols-2 gap-3">
                    <div class="space-y-1">
                        <label class="text-xs font-medium text-muted-foreground"
                            >Navigation Label</label
                        >
                        <Input
                            v-model="item.label"
                            class="h-8 text-sm"
                            placeholder="Label"
                        />
                    </div>
                    <div class="space-y-1">
                        <label class="text-xs font-medium text-muted-foreground"
                            >URL</label
                        >
                        <Input
                            v-model="item.url"
                            class="h-8 font-mono text-sm"
                            placeholder="/page or https://…"
                        />
                    </div>
                </div>
                <div class="flex items-center gap-2">
                    <input
                        :id="`target-${item._id}`"
                        v-model="item.target"
                        type="checkbox"
                        true-value="_blank"
                        false-value="_self"
                        class="h-4 w-4 cursor-pointer rounded accent-primary"
                    />
                    <label
                        :for="`target-${item._id}`"
                        class="cursor-pointer text-xs text-muted-foreground select-none"
                    >
                        Open in new tab
                    </label>
                </div>
            </div>

            <!-- Recursive children -->
            <div v-if="depth < MAX_DEPTH" class="px-3 pb-2">
                <MenuItemList
                    v-model="item.children"
                    :depth="depth + 1"
                    @sort="onSort"
                />
            </div>
        </div>
    </VueDraggable>
</template>
