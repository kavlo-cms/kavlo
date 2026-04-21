<script setup lang="ts">
import Link from '@tiptap/extension-link';
import StarterKit from '@tiptap/starter-kit';
import { EditorContent, useEditor } from '@tiptap/vue-3';
import {
    Bold,
    Code2,
    Heading1,
    Heading2,
    Italic,
    Link as LinkIcon,
    List,
    ListOrdered,
    Quote,
    Redo2,
    RemoveFormatting,
    Undo2,
} from 'lucide-vue-next';
import { defineAsyncComponent, ref, watch } from 'vue';
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs';

const HtmlCodeEditor = defineAsyncComponent(
    () => import('./HtmlCodeEditor.vue'),
);

const props = defineProps<{
    modelValue: string;
    context: {
        variables: {
            label: string;
            token: string;
            description: string;
        }[];
        snippets: {
            label: string;
            description: string;
            snippet: string;
        }[];
    };
}>();

const emit = defineEmits<{
    'update:modelValue': [value: string];
}>();

const editorMode = ref<'visual' | 'code'>('visual');
const currentContent = ref(props.modelValue ?? '');
const syncingVisual = ref(false);
const visualBaseline = ref('');

const editor = useEditor({
    content: currentContent.value,
    extensions: [
        StarterKit.configure({
            heading: { levels: [1, 2] },
        }),
        Link.configure({
            autolink: true,
            openOnClick: false,
            protocols: ['http', 'https', 'mailto', 'tel'],
        }),
    ],
    editorProps: {
        attributes: {
            class: 'h-full min-h-[420px] px-6 py-5 text-sm leading-7 focus:outline-none',
        },
    },
    onCreate: ({ editor }) => {
        visualBaseline.value = editor.getHTML();
    },
    onUpdate: ({ editor }) => {
        if (syncingVisual.value || editorMode.value !== 'visual') {
            return;
        }

        const html = editor.getHTML();
        visualBaseline.value = html;

        if (html === currentContent.value) {
            return;
        }

        currentContent.value = html;
        emit('update:modelValue', html);
    },
});

watch(
    () => props.modelValue,
    (value) => {
        const html = value ?? '';

        if (html === currentContent.value) {
            return;
        }

        currentContent.value = html;

        if (!editor.value || editorMode.value !== 'visual') {
            return;
        }

        if (html === editor.value.getHTML()) {
            return;
        }

        syncingVisual.value = true;
        editor.value.commands.setContent(html, false);
        visualBaseline.value = editor.value.getHTML();
        syncingVisual.value = false;
    },
);

watch(editorMode, (mode) => {
    if (!editor.value) {
        return;
    }

    if (mode === 'code') {
        const html = editor.value.getHTML();

        if (html !== visualBaseline.value && html !== currentContent.value) {
            currentContent.value = html;
            emit('update:modelValue', html);
        }

        return;
    }

    if (currentContent.value === editor.value.getHTML()) {
        return;
    }

    syncingVisual.value = true;
    editor.value.commands.setContent(currentContent.value, false);
    visualBaseline.value = editor.value.getHTML();
    syncingVisual.value = false;
});

function toggleLink() {
    if (!editor.value) {
        return;
    }

    const current = editor.value.getAttributes('link').href as
        | string
        | undefined;
    const href = window.prompt('Link URL', current ?? 'https://');

    if (href === null) {
        return;
    }

    const trimmed = href.trim();

    if (trimmed === '') {
        editor.value.chain().focus().unsetLink().run();

        return;
    }

    editor.value
        .chain()
        .focus()
        .extendMarkRange('link')
        .setLink({ href: trimmed })
        .run();
}

function toolbarClass(active = false) {
    return [
        'inline-flex h-8 items-center gap-1 rounded-md px-2 text-xs transition-colors',
        active
            ? 'bg-background text-foreground shadow-sm'
            : 'text-muted-foreground hover:bg-background hover:text-foreground',
    ];
}

function updateCodeContent(value: string) {
    if (value === currentContent.value) {
        return;
    }

    currentContent.value = value;
    emit('update:modelValue', value);
}

async function copySnippet(value: string) {
    await navigator.clipboard.writeText(value);
}
</script>

<template>
    <div class="flex h-full min-h-0 flex-1 flex-col overflow-hidden bg-muted/20">
        <div class="border-b bg-background px-6 py-4">
            <h2 class="text-sm font-medium">Content editor</h2>
            <p class="text-xs text-muted-foreground">
                Edit visually or switch to HTML source when you need exact
                markup control.
            </p>
        </div>

        <div class="min-h-0 flex-1 overflow-hidden p-6">
            <div class="grid h-full min-h-0 gap-6 xl:grid-cols-[minmax(0,1fr)_20rem]">
                <Tabs
                    v-model="editorMode"
                    class="flex h-full min-h-0 flex-col gap-4"
                >
                    <div class="flex items-center justify-between gap-4">
                        <TabsList>
                            <TabsTrigger value="visual">WYSIWYG</TabsTrigger>
                            <TabsTrigger value="code">Code</TabsTrigger>
                        </TabsList>
                        <span class="text-xs text-muted-foreground"
                            >Both modes edit the same HTML content.</span
                        >
                    </div>

                    <TabsContent value="visual" class="mt-0 min-h-0 flex-1">
                        <div
                            class="flex h-full min-h-0 flex-col overflow-hidden rounded-lg border bg-background"
                        >
                            <div
                                class="flex flex-wrap items-center gap-1 border-b bg-muted/40 p-2"
                            >
                                <button
                                    type="button"
                                    :class="
                                        toolbarClass(editor?.isActive('bold'))
                                    "
                                    @click="
                                        editor
                                            ?.chain()
                                            .focus()
                                            .toggleBold()
                                            .run()
                                    "
                                >
                                    <Bold class="h-3.5 w-3.5" />
                                    Bold
                                </button>
                                <button
                                    type="button"
                                    :class="
                                        toolbarClass(editor?.isActive('italic'))
                                    "
                                    @click="
                                        editor
                                            ?.chain()
                                            .focus()
                                            .toggleItalic()
                                            .run()
                                    "
                                >
                                    <Italic class="h-3.5 w-3.5" />
                                    Italic
                                </button>
                                <button
                                    type="button"
                                    :class="
                                        toolbarClass(
                                            editor?.isActive('heading', {
                                                level: 1,
                                            }),
                                        )
                                    "
                                    @click="
                                        editor
                                            ?.chain()
                                            .focus()
                                            .toggleHeading({ level: 1 })
                                            .run()
                                    "
                                >
                                    <Heading1 class="h-3.5 w-3.5" />
                                    H1
                                </button>
                                <button
                                    type="button"
                                    :class="
                                        toolbarClass(
                                            editor?.isActive('heading', {
                                                level: 2,
                                            }),
                                        )
                                    "
                                    @click="
                                        editor
                                            ?.chain()
                                            .focus()
                                            .toggleHeading({ level: 2 })
                                            .run()
                                    "
                                >
                                    <Heading2 class="h-3.5 w-3.5" />
                                    H2
                                </button>
                                <button
                                    type="button"
                                    :class="
                                        toolbarClass(
                                            editor?.isActive('bulletList'),
                                        )
                                    "
                                    @click="
                                        editor
                                            ?.chain()
                                            .focus()
                                            .toggleBulletList()
                                            .run()
                                    "
                                >
                                    <List class="h-3.5 w-3.5" />
                                    Bullets
                                </button>
                                <button
                                    type="button"
                                    :class="
                                        toolbarClass(
                                            editor?.isActive('orderedList'),
                                        )
                                    "
                                    @click="
                                        editor
                                            ?.chain()
                                            .focus()
                                            .toggleOrderedList()
                                            .run()
                                    "
                                >
                                    <ListOrdered class="h-3.5 w-3.5" />
                                    Numbers
                                </button>
                                <button
                                    type="button"
                                    :class="
                                        toolbarClass(
                                            editor?.isActive('blockquote'),
                                        )
                                    "
                                    @click="
                                        editor
                                            ?.chain()
                                            .focus()
                                            .toggleBlockquote()
                                            .run()
                                    "
                                >
                                    <Quote class="h-3.5 w-3.5" />
                                    Quote
                                </button>
                                <button
                                    type="button"
                                    :class="
                                        toolbarClass(editor?.isActive('link'))
                                    "
                                    @click="toggleLink"
                                >
                                    <LinkIcon class="h-3.5 w-3.5" />
                                    Link
                                </button>
                                <button
                                    type="button"
                                    :class="toolbarClass()"
                                    @click="
                                        editor
                                            ?.chain()
                                            .focus()
                                            .unsetAllMarks()
                                            .clearNodes()
                                            .run()
                                    "
                                >
                                    <RemoveFormatting class="h-3.5 w-3.5" />
                                    Clear
                                </button>
                                <div class="ml-auto flex items-center gap-1">
                                    <button
                                        type="button"
                                        :class="toolbarClass()"
                                        @click="
                                            editor?.chain().focus().undo().run()
                                        "
                                    >
                                        <Undo2 class="h-3.5 w-3.5" />
                                    </button>
                                    <button
                                        type="button"
                                        :class="toolbarClass()"
                                        @click="
                                            editor?.chain().focus().redo().run()
                                        "
                                    >
                                        <Redo2 class="h-3.5 w-3.5" />
                                    </button>
                                </div>
                            </div>

                            <div class="min-h-0 flex-1 overflow-auto">
                                <EditorContent :editor="editor" />
                            </div>

                            <div
                                v-if="!currentContent"
                                class="border-t bg-muted/20 px-6 py-3 text-xs text-muted-foreground"
                            >
                                Start writing here, or switch to
                                <span class="font-medium">Code</span> to edit
                                the HTML directly.
                            </div>
                        </div>
                    </TabsContent>

                    <TabsContent value="code" class="mt-0 min-h-0 flex-1">
                        <div
                            class="flex h-full min-h-0 flex-col overflow-hidden rounded-lg border bg-background"
                        >
                            <div
                                class="flex items-center gap-2 border-b bg-muted/40 px-4 py-2 text-xs text-muted-foreground"
                            >
                                <Code2 class="h-3.5 w-3.5" />
                                HTML source
                            </div>
                            <HtmlCodeEditor
                                :model-value="currentContent"
                                @update:model-value="updateCodeContent"
                            />
                        </div>
                    </TabsContent>
                </Tabs>

                <aside class="min-h-0 space-y-4 overflow-auto pr-1">
                    <div class="rounded-lg border bg-background p-4">
                        <h3 class="text-sm font-medium">Available data</h3>
                        <p class="mt-1 text-xs text-muted-foreground">
                            Use these Blade expressions in either editor mode.
                            Simple values are easiest in WYSIWYG; loops and
                            conditionals are best in code mode.
                        </p>

                        <div class="mt-4 space-y-3">
                            <div
                                v-for="variable in context.variables"
                                :key="variable.token"
                                class="rounded-md border p-3"
                            >
                                <div
                                    class="flex items-start justify-between gap-3"
                                >
                                    <div>
                                        <p class="text-sm font-medium">
                                            {{ variable.label }}
                                        </p>
                                        <p
                                            class="mt-1 font-mono text-xs text-foreground"
                                        >
                                            {{ variable.token }}
                                        </p>
                                        <p
                                            class="mt-1 text-xs text-muted-foreground"
                                        >
                                            {{ variable.description }}
                                        </p>
                                    </div>
                                    <button
                                        type="button"
                                        class="text-xs text-muted-foreground hover:text-foreground"
                                        @click="copySnippet(variable.token)"
                                    >
                                        Copy
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="rounded-lg border bg-background p-4">
                        <h3 class="text-sm font-medium">Blade snippets</h3>
                        <p class="mt-1 text-xs text-muted-foreground">
                            Copy/paste these when you need loops or
                            conditionals.
                        </p>

                        <div class="mt-4 space-y-3">
                            <div
                                v-for="snippet in context.snippets"
                                :key="snippet.label"
                                class="rounded-md border p-3"
                            >
                                <div
                                    class="flex items-start justify-between gap-3"
                                >
                                    <div class="min-w-0">
                                        <p class="text-sm font-medium">
                                            {{ snippet.label }}
                                        </p>
                                        <p
                                            class="mt-1 text-xs text-muted-foreground"
                                        >
                                            {{ snippet.description }}
                                        </p>
                                    </div>
                                    <button
                                        type="button"
                                        class="text-xs text-muted-foreground hover:text-foreground"
                                        @click="copySnippet(snippet.snippet)"
                                    >
                                        Copy
                                    </button>
                                </div>
                                <pre
                                    class="mt-3 overflow-x-auto rounded-md bg-muted/50 p-3 text-xs"
                                ><code>{{ snippet.snippet }}</code></pre>
                            </div>
                        </div>
                    </div>
                </aside>
            </div>
        </div>
    </div>
</template>

<style scoped>
:deep(.ProseMirror) h1 {
    margin: 1.25rem 0 0.75rem;
    font-size: 1.875rem;
    font-weight: 700;
    line-height: 1.2;
}

:deep(.ProseMirror) h2 {
    margin: 1rem 0 0.625rem;
    font-size: 1.5rem;
    font-weight: 600;
    line-height: 1.3;
}

:deep(.ProseMirror) p {
    margin: 0.75rem 0;
}

:deep(.ProseMirror) ul,
:deep(.ProseMirror) ol {
    margin: 0.75rem 0;
    padding-left: 1.5rem;
}

:deep(.ProseMirror) blockquote {
    margin: 1rem 0;
    border-left: 3px solid hsl(var(--border));
    padding-left: 1rem;
    color: hsl(var(--muted-foreground));
}

:deep(.ProseMirror) a {
    color: hsl(var(--primary));
    text-decoration: underline;
}
</style>
