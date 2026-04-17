<script setup lang="ts">
import { computed } from 'vue';

defineOptions({ name: 'FormPreviewBlocks' });

interface BlockData {
    id?: string;
    type: string;
    data?: Record<string, unknown>;
    order?: number;
}

const props = defineProps<{
    blocks: BlockData[];
}>();

const gapClassMap: Record<string, string> = {
    sm: 'gap-2',
    md: 'gap-6',
    lg: 'gap-10',
};

const orderedBlocks = computed(() =>
    [...props.blocks].sort((a, b) => Number(a.order ?? 0) - Number(b.order ?? 0)),
);

function blockData(block: BlockData): Record<string, unknown> {
    return typeof block.data === 'object' && block.data !== null ? block.data : {};
}

function options(data: Record<string, unknown>): { label: string; value: string }[] {
    const raw = data.options;

    if (!Array.isArray(raw)) {
        return [];
    }

    return raw
        .filter((item): item is { label?: string; value?: string } => typeof item === 'object' && item !== null)
        .map((item) => ({
            label: String(item.label ?? item.value ?? 'Option'),
            value: String(item.value ?? item.label ?? 'option'),
        }));
}

function columnBlocks(data: Record<string, unknown>, index: number): BlockData[] {
    const value = data[`col_${index}`];

    return Array.isArray(value) ? value as BlockData[] : [];
}

function columnCount(data: Record<string, unknown>): number {
    return Math.max(2, Math.min(4, Number(data.count ?? 2)));
}

function gridClass(count: number): string {
    return {
        2: 'grid-cols-2',
        3: 'grid-cols-3',
        4: 'grid-cols-4',
    }[count] ?? 'grid-cols-2';
}
</script>

<template>
    <div class="space-y-5">
        <template v-for="block in orderedBlocks" :key="block.id ?? `${block.type}-${block.order ?? 0}`">
            <template v-if="block.type === 'columns'">
                <div
                    class="grid"
                    :class="[gridClass(columnCount(blockData(block))), gapClassMap[String(blockData(block).gap ?? 'md')] ?? gapClassMap.md]"
                >
                    <div
                        v-for="index in columnCount(blockData(block))"
                        :key="`${block.id ?? 'columns'}-${index}`"
                        class="min-w-0 space-y-5"
                    >
                        <FormPreviewBlocks :blocks="columnBlocks(blockData(block), index - 1)" />
                    </div>
                </div>
            </template>

            <template v-else-if="block.type === 'button'">
                <button
                    type="button"
                    class="cms-form-submit rounded-md bg-primary px-5 py-2.5 text-sm font-medium text-primary-foreground opacity-100"
                >
                    {{ String(blockData(block).label ?? 'Submit') }}
                </button>
            </template>

            <template v-else-if="block.type === 'textarea'">
                <div class="space-y-1.5">
                    <label class="block text-sm font-medium">
                        {{ String(blockData(block).label ?? 'Textarea') }}
                        <span v-if="Boolean(blockData(block).required)" class="text-red-500">*</span>
                    </label>
                    <textarea
                        :placeholder="String(blockData(block).placeholder ?? '')"
                        class="cms-form-field min-h-24 w-full rounded-md border bg-transparent px-3 py-2 text-sm opacity-100"
                        disabled
                    ></textarea>
                </div>
            </template>

            <template v-else-if="block.type === 'select'">
                <div class="space-y-1.5">
                    <label class="block text-sm font-medium">
                        {{ String(blockData(block).label ?? 'Select') }}
                        <span v-if="Boolean(blockData(block).required)" class="text-red-500">*</span>
                    </label>
                    <select class="cms-form-field w-full rounded-md border bg-transparent px-3 py-2 text-sm opacity-100" disabled>
                        <option>{{ String(blockData(block).placeholder ?? 'Select an option') }}</option>
                        <option v-for="option in options(blockData(block))" :key="option.value">{{ option.label }}</option>
                    </select>
                </div>
            </template>

            <template v-else-if="block.type === 'checkbox' || block.type === 'radio'">
                <div class="space-y-2">
                    <p class="text-sm font-medium">
                        {{ String(blockData(block).label ?? block.type) }}
                        <span v-if="Boolean(blockData(block).required)" class="text-red-500">*</span>
                    </p>
                    <label
                        v-for="option in options(blockData(block))"
                        :key="option.value"
                        class="flex items-center gap-2 text-sm"
                    >
                        <input :type="block.type" disabled />
                        <span>{{ option.label }}</span>
                    </label>
                </div>
            </template>

            <template v-else>
                <div class="space-y-1.5">
                    <label class="block text-sm font-medium">
                        {{ String(blockData(block).label ?? 'Field') }}
                        <span v-if="Boolean(blockData(block).required)" class="text-red-500">*</span>
                    </label>
                    <div
                        v-if="String(blockData(block).input_type ?? 'text') === 'file'"
                        class="rounded-md border border-dashed px-3 py-4 text-sm text-muted-foreground"
                    >
                        Choose file
                    </div>
                    <input
                        v-else
                        :type="String(blockData(block).input_type ?? 'text')"
                        :placeholder="String(blockData(block).placeholder ?? '')"
                        class="cms-form-field w-full rounded-md border bg-transparent px-3 py-2 text-sm opacity-100"
                        disabled
                    />
                </div>
            </template>
        </template>
    </div>
</template>
