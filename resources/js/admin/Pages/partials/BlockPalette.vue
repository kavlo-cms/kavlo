<script setup lang="ts">
import {
    AlertCircle,
    AlignLeft,
    ArrowUpDown,
    ChevronDown,
    GripVertical,
    Heading1,
    Image,
    LayoutGrid,
    List,
    Minus,
    MousePointer2,
    Quote,
    Search,
    Sparkles,
    Square,
    Video,
    X,
} from 'lucide-vue-next';
import { computed, ref } from 'vue';
import { VueDraggable } from 'vue-draggable-plus';
import { useBlockSchemas } from '@/composables/useBlockSchemas';
import type { AvailableBlock } from '@/composables/useBlockSchemas';
import type { Block } from '@/types/blocks';
import { blockGroupOrder, blockGroups } from '../config/blockSchemas';

const props = defineProps<{
    availableBlocks: AvailableBlock[];
}>();

const { getSchema } = useBlockSchemas(() => props.availableBlocks);

const iconMap: Record<string, unknown> = {
    Heading1,
    AlignLeft,
    Quote,
    List,
    Square,
    LayoutGrid,
    Minus,
    ArrowUpDown,
    Image,
    Video,
    Sparkles,
    MousePointer2,
    AlertCircle,
};

function blockIcon(type: string) {
    const name = getSchema(type)?.icon ?? 'Square';

    return iconMap[name] ?? Square;
}

function cloneBlock(item: AvailableBlock): Block {
    return {
        id: crypto.randomUUID(),
        type: item.type,
        data: { ...(item.defaultData ?? {}) },
        order: 0,
    };
}

const search = ref('');
const collapsedGroups = ref(new Set<string>());

function toggleGroup(group: string) {
    const s = new Set(collapsedGroups.value);

    if (s.has(group)) {
        s.delete(group);
    } else {
        s.add(group);
    }

    collapsedGroups.value = s;
}

const filteredBlocks = computed(() => {
    const q = search.value.toLowerCase().trim();

    if (!q) {
        return props.availableBlocks;
    }

    return props.availableBlocks.filter((b) => {
        const schema = getSchema(b.type);

        return (
            (schema?.label ?? b.label).toLowerCase().includes(q) ||
            b.type.includes(q) ||
            (schema?.description ?? '').toLowerCase().includes(q)
        );
    });
});

const groupedBlocks = computed(() => {
    const groups: Record<string, AvailableBlock[]> = {};

    for (const block of filteredBlocks.value) {
        const group = getSchema(block.type)?.group ?? 'other';

        if (!groups[group]) {
            groups[group] = [];
        }

        groups[group].push(block);
    }

    return groups;
});

const visibleGroups = computed(() => {
    const order = [...blockGroupOrder, 'other'];

    return order.filter((g) => groupedBlocks.value[g]?.length);
});
</script>

<template>
    <aside
        class="flex h-full w-full shrink-0 flex-col overflow-hidden border-r bg-muted/30"
    >
        <!-- Header + search -->
        <div class="shrink-0 border-b px-3 pt-3 pb-2">
            <span
                class="text-xs font-semibold tracking-wide text-muted-foreground uppercase"
                >Blocks</span
            >
            <div class="relative mt-2">
                <Search
                    class="pointer-events-none absolute top-1/2 left-2.5 h-3.5 w-3.5 -translate-y-1/2 text-muted-foreground"
                />
                <input
                    v-model="search"
                    type="search"
                    placeholder="Search blocks…"
                    class="h-8 w-full rounded-md border bg-background pr-7 pl-8 text-xs focus:ring-1 focus:ring-ring focus:outline-none"
                />
                <button
                    v-if="search"
                    class="absolute top-1/2 right-2 -translate-y-1/2 text-muted-foreground hover:text-foreground"
                    @click="search = ''"
                >
                    <X class="h-3.5 w-3.5" />
                </button>
            </div>
        </div>

        <!-- Groups -->
        <div class="flex-1 overflow-y-auto">
            <template v-if="filteredBlocks.length === 0">
                <p class="px-4 py-8 text-center text-xs text-muted-foreground">
                    No blocks match "{{ search }}"
                </p>
            </template>

            <div v-for="group in visibleGroups" :key="group">
                <!-- Group header -->
                <button
                    class="flex w-full items-center justify-between px-3 py-2 text-xs font-semibold tracking-wide text-muted-foreground uppercase transition-colors hover:text-foreground"
                    @click="toggleGroup(group)"
                >
                    <span>{{ blockGroups[group] ?? group }}</span>
                    <ChevronDown
                        class="h-3.5 w-3.5 transition-transform duration-200"
                        :class="collapsedGroups.has(group) ? '-rotate-90' : ''"
                    />
                </button>

                <!-- Group blocks (draggable) -->
                <VueDraggable
                    v-if="!collapsedGroups.has(group)"
                    :model-value="groupedBlocks[group]"
                    :group="{ name: 'page-blocks', pull: 'clone', put: false }"
                    :clone="cloneBlock"
                    :sort="false"
                    class="flex flex-col gap-1 px-2 pb-2"
                >
                    <div
                        v-for="block in groupedBlocks[group]"
                        :key="block.type"
                        class="flex cursor-grab items-center gap-2.5 rounded-md border bg-background px-2.5 py-2 transition-colors select-none hover:border-primary/50 hover:bg-accent active:cursor-grabbing"
                    >
                        <component
                            :is="blockIcon(block.type)"
                            class="h-4 w-4 shrink-0 text-muted-foreground"
                        />
                        <div class="min-w-0 flex-1">
                            <p
                                class="truncate text-sm leading-none font-medium"
                            >
                                {{
                                    getSchema(block.type)?.label ?? block.label
                                }}
                            </p>
                            <p
                                v-if="getSchema(block.type)?.description"
                                class="mt-0.5 truncate text-xs text-muted-foreground"
                            >
                                {{ getSchema(block.type)?.description }}
                            </p>
                        </div>
                        <GripVertical
                            class="h-3.5 w-3.5 shrink-0 text-muted-foreground/50"
                        />
                    </div>
                </VueDraggable>
            </div>
        </div>
    </aside>
</template>
