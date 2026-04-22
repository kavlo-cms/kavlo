<script setup lang="ts">
import type { editor as MonacoEditor } from 'monaco-editor';
import EditorWorker from 'monaco-editor/esm/vs/editor/editor.worker?worker';
import HtmlWorker from 'monaco-editor/esm/vs/language/html/html.worker?worker';
import TsWorker from 'monaco-editor/esm/vs/language/typescript/ts.worker?worker';
import { onBeforeUnmount, onMounted, ref, watch } from 'vue';

const loadMonaco = () => import('monaco-editor');

const props = withDefaults(
    defineProps<{
        modelValue: string;
        language?: 'html' | 'javascript' | 'typescript';
        wordWrap?: 'on' | 'off';
        tabSize?: number;
    }>(),
    {
        language: 'html',
        wordWrap: 'on',
        tabSize: 2,
    },
);

const emit = defineEmits<{
    'update:modelValue': [value: string];
}>();

const root = ref<HTMLElement | null>(null);

let editor: MonacoEditor.IStandaloneCodeEditor | null = null;
let model: MonacoEditor.ITextModel | null = null;
let monaco: Awaited<ReturnType<typeof loadMonaco>> | null = null;
let syncing = false;
let themeObserver: MutationObserver | null = null;

function theme(): 'vs' | 'vs-dark' {
    return document.documentElement.classList.contains('dark')
        ? 'vs-dark'
        : 'vs';
}

function installWorkers() {
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

                if (label === 'javascript' || label === 'typescript') {
                    return new TsWorker();
                }

                return new EditorWorker();
            },
        },
    });
}

function syncTheme() {
    monaco?.editor.setTheme(theme());
}

async function mountEditor() {
    if (!root.value || editor) {
        return;
    }

    installWorkers();

    monaco = await loadMonaco();
    model = monaco.editor.createModel(props.modelValue ?? '', props.language);

    editor = monaco.editor.create(root.value, {
        model,
        theme: theme(),
        automaticLayout: true,
        minimap: { enabled: false },
        fontSize: 14,
        lineNumbersMinChars: 3,
        scrollBeyondLastLine: false,
        tabSize: props.tabSize,
        wordWrap: props.wordWrap,
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

watch(
    () => props.language,
    (language) => {
        if (!monaco || !model) {
            return;
        }

        monaco.editor.setModelLanguage(model, language);
    },
);

watch(
    () => props.wordWrap,
    (wordWrap) => {
        editor?.updateOptions({ wordWrap });
    },
);

watch(
    () => props.tabSize,
    (tabSize) => {
        model?.updateOptions({ tabSize });
    },
);

onMounted(() => {
    void mountEditor();

    themeObserver = new MutationObserver(() => {
        syncTheme();
    });

    themeObserver.observe(document.documentElement, {
        attributes: true,
        attributeFilter: ['class'],
    });
});

onBeforeUnmount(() => {
    themeObserver?.disconnect();
    themeObserver = null;
    editor?.dispose();
    editor = null;
    model?.dispose();
    model = null;
});
</script>

<template>
    <div
        ref="root"
        class="h-full min-h-0 w-full overflow-hidden bg-background"
        v-bind="$attrs"
    />
</template>
