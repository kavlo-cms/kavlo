<script setup lang="ts">
import type { editor as MonacoEditor } from 'monaco-editor';
import EditorWorker from 'monaco-editor/esm/vs/editor/editor.worker?worker';
import HtmlWorker from 'monaco-editor/esm/vs/language/html/html.worker?worker';
import { onBeforeUnmount, onMounted, ref, watch } from 'vue';

const props = defineProps<{
    modelValue: string;
}>();

const emit = defineEmits<{
    'update:modelValue': [value: string];
}>();

const root = ref<HTMLElement | null>(null);

let editor: MonacoEditor.IStandaloneCodeEditor | null = null;
let syncing = false;

function theme(): 'vs' | 'vs-dark' {
    return document.documentElement.classList.contains('dark')
        ? 'vs-dark'
        : 'vs';
}

async function mountEditor() {
    if (!root.value || editor) {
        return;
    }

    Object.assign(globalThis, {
        MonacoEnvironment: {
            getWorker(_: string, label: string) {
                if (
                    label === 'html' ||
                    label === 'handlebars' ||
                    label === 'razor'
                ) {
                    return new HtmlWorker();
                }

                return new EditorWorker();
            },
        },
    });

    const monaco = await import('monaco-editor');

    editor = monaco.editor.create(root.value, {
        value: props.modelValue ?? '',
        language: 'html',
        theme: theme(),
        automaticLayout: true,
        minimap: { enabled: false },
        fontSize: 14,
        lineNumbersMinChars: 3,
        scrollBeyondLastLine: false,
        tabSize: 2,
        wordWrap: 'on',
    });

    editor.onDidChangeModelContent(() => {
        if (!editor || syncing) {
            return;
        }

        emit('update:modelValue', editor.getValue());
    });
}

watch(
    () => props.modelValue,
    (value) => {
        if (!editor) {
            return;
        }

        const next = value ?? '';

        if (editor.getValue() === next) {
            return;
        }

        syncing = true;
        editor.setValue(next);
        syncing = false;
    },
);

onMounted(() => {
    void mountEditor();
});

onBeforeUnmount(() => {
    editor?.dispose();
    editor = null;
});
</script>

<template>
    <div
        ref="root"
        class="h-full min-h-0 w-full overflow-hidden rounded-b-lg bg-background"
    />
</template>
