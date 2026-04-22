<script setup lang="ts">
import { computed, ref, watch } from 'vue';
import { Input } from '@/components/ui/input';
import type { BlockPageOption } from '../schema';

const props = withDefaults(
    defineProps<{
        id?: string;
        modelValue?: string | null;
        pages?: BlockPageOption[];
        placeholder?: string;
    }>(),
    {
        id: undefined,
        modelValue: '',
        pages: () => [],
        placeholder: 'https://... or javascript:void(0)',
    },
);

const emit = defineEmits<{
    'update:modelValue': [value: string];
}>();

const mode = ref<'page' | 'custom'>('page');

const normalizedValue = computed(() => String(props.modelValue ?? ''));

function detectMode(value: string): 'page' | 'custom' {
    return value === '' || props.pages.some((page) => page.slug === value)
        ? 'page'
        : 'custom';
}

function setMode(nextMode: 'page' | 'custom') {
    mode.value = nextMode;
    emit('update:modelValue', '');
}

watch(
    () => [props.modelValue, props.pages] as const,
    ([value]) => {
        mode.value = detectMode(String(value ?? ''));
    },
    { immediate: true, deep: true },
);
</script>

<template>
    <div class="flex flex-col gap-2">
        <div class="flex gap-1.5">
            <button
                type="button"
                class="flex-1 rounded-md border px-2.5 py-1.5 text-left text-xs transition-colors"
                :class="
                    mode === 'page'
                        ? 'border-primary bg-primary text-primary-foreground'
                        : 'text-muted-foreground hover:bg-accent'
                "
                @click="setMode('page')"
            >
                Page
            </button>
            <button
                type="button"
                class="flex-1 rounded-md border px-2.5 py-1.5 text-left text-xs transition-colors"
                :class="
                    mode === 'custom'
                        ? 'border-primary bg-primary text-primary-foreground'
                        : 'text-muted-foreground hover:bg-accent'
                "
                @click="setMode('custom')"
            >
                Custom
            </button>
        </div>

        <select
            v-if="mode === 'page'"
            :id="id"
            class="flex h-9 w-full rounded-md border border-input bg-transparent px-3 py-1 text-sm shadow-sm transition-colors focus:ring-1 focus:ring-ring focus:outline-none"
            :value="normalizedValue"
            @change="
                emit(
                    'update:modelValue',
                    ($event.target as HTMLSelectElement).value,
                )
            "
        >
            <option value="">— Select a page —</option>
            <option v-for="page in pages" :key="page.id" :value="page.slug">
                {{ page.title }}
            </option>
        </select>

        <Input
            v-else
            :id="id"
            type="text"
            :model-value="normalizedValue"
            :placeholder="placeholder"
            @update:model-value="emit('update:modelValue', String($event))"
        />
    </div>
</template>
