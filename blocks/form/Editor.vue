<script setup lang="ts">
interface Attributes {
    form_slug: string;
}

defineProps<{ modelValue: Attributes }>();
const emit = defineEmits(['update:modelValue']);
</script>

<template>
    <div class="p-4 border border-dashed rounded bg-white space-y-2">
        <label class="block text-sm font-bold text-gray-700">Form Block</label>
        <input
            type="text"
            :value="modelValue.form_slug"
            @input="emit('update:modelValue', { ...modelValue, form_slug: ($event.target as HTMLInputElement).value })"
            class="w-full p-2 border rounded shadow-sm focus:ring focus:ring-opacity-50"
            placeholder="Enter form slug (e.g. contact-form)"
        />
        <p class="text-xs text-gray-400">Enter the slug of the form to embed.</p>
    </div>
</template>
