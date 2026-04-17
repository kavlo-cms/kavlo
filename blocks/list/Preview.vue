<script setup lang="ts">
import { computed } from 'vue';
import { Plus, Trash2 } from 'lucide-vue-next';
import InlineEdit from '@/admin/Pages/partials/InlineEdit.vue';

const props = defineProps<{ data: Record<string, unknown> }>();
const emit = defineEmits<{ 'update:data': [Record<string, unknown>] }>();

const items = computed<string[]>(() => (props.data.items as string[]) ?? ['']);
const isNumbered = computed(() => (props.data.style as string) === 'numbered');

function updateItem(i: number, val: string) {
    const next = [...items.value];
    next[i] = val;
    emit('update:data', { ...props.data, items: next });
}
function addItem() {
    emit('update:data', { ...props.data, items: [...items.value, ''] });
}
function removeItem(i: number) {
    const next = items.value.filter((_, idx) => idx !== i);
    emit('update:data', { ...props.data, items: next.length ? next : [''] });
}
</script>

<template>
    <div class="py-6 px-6 max-w-3xl mx-auto w-full">
        <component
            :is="isNumbered ? 'ol' : 'ul'"
            :class="['space-y-1.5', isNumbered ? 'list-decimal pl-5' : 'list-disc pl-5']"
        >
            <li v-for="(item, i) in items" :key="i" class="group/item flex items-start gap-2">
                <InlineEdit
                    tag="span"
                    :model-value="item"
                    placeholder="List item…"
                    class="block min-w-[2ch] flex-1 text-base text-foreground"
                    @update:model-value="updateItem(i, $event)"
                />
                <button
                    class="mt-0.5 shrink-0 rounded p-0.5 text-muted-foreground/40 opacity-0 transition-all hover:text-destructive group-hover/item:opacity-100"
                    @click.stop="removeItem(i)"
                >
                    <Trash2 class="h-3.5 w-3.5" />
                </button>
            </li>
        </component>
        <button
            class="mt-3 flex items-center gap-1.5 text-xs text-muted-foreground transition-colors hover:text-foreground"
            @click.stop="addItem"
        >
            <Plus class="h-3.5 w-3.5" />
            Add item
        </button>
    </div>
</template>
