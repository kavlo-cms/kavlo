<script setup lang="ts">
interface Attributes {
    headline: string;
    subheadline: string;
    button_url: string;
    button_text: string;
}
const props = defineProps<{ modelValue: Attributes }>();
const emit = defineEmits(['update:modelValue']);

function update(key: keyof Attributes, value: string) {
    emit('update:modelValue', { ...props.modelValue, [key]: value });
}
</script>

<template>
    <div class="p-4 border border-dashed rounded bg-white space-y-3">
        <label class="block text-sm font-bold text-gray-700">Hero Block</label>
        <input
            type="text"
            :value="modelValue.headline"
            @input="update('headline', ($event.target as HTMLInputElement).value)"
            class="w-full p-2 border rounded shadow-sm focus:ring focus:ring-opacity-50"
            placeholder="Headline..."
        />
        <textarea
            :value="modelValue.subheadline"
            @input="update('subheadline', ($event.target as HTMLTextAreaElement).value)"
            class="w-full p-2 border rounded shadow-sm focus:ring focus:ring-opacity-50"
            rows="2"
            placeholder="Sub-headline..."
        />
        <input
            type="url"
            :value="modelValue.button_url"
            @input="update('button_url', ($event.target as HTMLInputElement).value)"
            class="w-full p-2 border rounded shadow-sm focus:ring focus:ring-opacity-50"
            placeholder="Button URL (https://...)"
        />
        <input
            type="text"
            :value="modelValue.button_text"
            @input="update('button_text', ($event.target as HTMLInputElement).value)"
            class="w-full p-2 border rounded shadow-sm focus:ring focus:ring-opacity-50"
            placeholder="Button text (Get Started)"
        />
    </div>
</template>