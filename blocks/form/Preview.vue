<script setup lang="ts">
import { usePage } from '@inertiajs/vue3';
import { computed } from 'vue';
import FormPreviewBlocks from './FormPreviewBlocks.vue';

interface Attributes {
    form_slug: string;
}

interface FormPreview {
    value: string;
    label: string;
    source?: string;
    blocks?: Array<{
        id?: string;
        type: string;
        data?: Record<string, unknown>;
        order?: number;
    }>;
    preview_html?: string | null;
}

const props = defineProps<{ data: Attributes }>();
const page = usePage();

const selectedPreview = computed(() => {
    const previews = (page.props.availableFormPreviews ?? []) as FormPreview[];

    return previews.find((preview) => preview.value === props.data?.form_slug) ?? null;
});

const selectedLabel = computed(() => selectedPreview.value?.label ?? props.data?.form_slug ?? '');
const previewBlocks = computed(() => Array.isArray(selectedPreview.value?.blocks) ? selectedPreview.value?.blocks : []);
const previewHtml = computed(() => selectedPreview.value?.preview_html ?? '');
const isRegisteredPreview = computed(() => (selectedPreview.value?.source ?? 'forms') !== 'forms');
</script>

<template>
    <div class="pointer-events-none px-4 py-8">
        <template v-if="selectedPreview">
            <div class="mx-auto max-w-xl">
                <div
                    v-if="previewHtml"
                    class="prose prose-sm max-w-none"
                    v-html="previewHtml"
                />

                <div
                    v-else-if="isRegisteredPreview && previewBlocks.length === 0"
                    class="rounded border border-dashed border-current/20 px-4 py-5 text-sm text-muted-foreground"
                >
                    {{ selectedLabel }}
                </div>

                <form v-else class="space-y-5">
                    <FormPreviewBlocks :blocks="previewBlocks" />
                </form>
            </div>
        </template>

        <div v-else class="mx-auto max-w-xl rounded border border-dashed border-current/20 px-4 py-5 text-center text-sm text-muted-foreground">
            <span class="font-medium">Form:</span> {{ selectedLabel || '(no form selected)' }}
        </div>
    </div>
</template>
