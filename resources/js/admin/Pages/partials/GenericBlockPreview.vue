<script setup lang="ts">
import { computed } from 'vue';

const props = defineProps<{
    type?: string;
    data?: Record<string, unknown>;
}>();

const options = computed(() => {
    const raw = props.data?.options;

    if (!Array.isArray(raw)) {
return [];
}

    return raw
        .filter((item): item is { label?: string; value?: string } => typeof item === 'object' && item !== null)
        .map((item) => ({
            label: item.label || item.value || 'Option',
            value: item.value || item.label || 'option',
        }));
});

const label = computed(() => String(props.data?.label ?? props.type ?? 'Block'));
const inputType = computed(() => String(props.data?.input_type ?? 'text'));
const buttonLabel = computed(() => String(props.data?.label ?? 'Submit'));
</script>

<template>
    <div class="pointer-events-none border-b bg-background px-8 py-6">
        <template v-if="type === 'input'">
            <label class="mb-2 block text-sm font-medium">{{ label }}</label>
            <div
                v-if="inputType === 'file'"
                class="rounded-md border border-dashed px-3 py-4 text-sm text-muted-foreground"
            >
                Choose file
            </div>
            <input
                v-else
                :type="inputType"
                :placeholder="String(data?.placeholder ?? '')"
                class="w-full rounded-md border bg-background px-3 py-2 text-sm"
                disabled
            />
        </template>

        <template v-else-if="type === 'textarea'">
            <label class="mb-2 block text-sm font-medium">{{ label }}</label>
            <textarea
                :placeholder="String(data?.placeholder ?? '')"
                class="min-h-24 w-full rounded-md border bg-background px-3 py-2 text-sm"
                disabled
            ></textarea>
        </template>

        <template v-else-if="type === 'select'">
            <label class="mb-2 block text-sm font-medium">{{ label }}</label>
            <select class="w-full rounded-md border bg-background px-3 py-2 text-sm" disabled>
                <option>{{ data?.placeholder || 'Select an option' }}</option>
                <option v-for="option in options" :key="option.value">{{ option.label }}</option>
            </select>
        </template>

        <template v-else-if="type === 'checkbox' || type === 'radio'">
            <p class="mb-2 text-sm font-medium">{{ label }}</p>
            <div class="space-y-2">
                <label v-for="option in options" :key="option.value" class="flex items-center gap-2 text-sm text-muted-foreground">
                    <input :type="type" disabled />
                    <span>{{ option.label }}</span>
                </label>
            </div>
        </template>

        <template v-else-if="type === 'button'">
            <button
                type="button"
                class="inline-flex rounded-md bg-primary px-4 py-2 text-sm font-medium text-primary-foreground"
            >
                {{ buttonLabel }}
            </button>
        </template>

        <template v-else>
            <div class="rounded-md border border-dashed px-4 py-5 text-sm text-muted-foreground">
                {{ type || 'Block' }}
            </div>
        </template>
    </div>
</template>
