<script setup lang="ts">
import { computed, ref, watch } from 'vue';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Separator } from '@/components/ui/separator';
import { Switch } from '@/components/ui/switch';
import { Textarea } from '@/components/ui/textarea';
import type { AvailableBlock } from '@/composables/useBlockSchemas';
import type { Block } from '@/types/blocks';

type Tab = 'template' | 'block';

interface VariableDefinition {
    key: string;
    label: string;
    example?: string;
}

interface TemplateContext {
    key: string;
    label: string;
    description?: string;
    source?: string;
    variables?: VariableDefinition[];
}

interface EmailTemplateForm {
    name: string;
    slug: string;
    description: string;
    context_key: string;
    subject: string;
    blocks: Block[];
}

const props = defineProps<{
    form: EmailTemplateForm;
    selectedBlock: Block | null;
    availableBlocks: AvailableBlock[];
    availableContexts: TemplateContext[];
}>();

const emit = defineEmits<{
    'update:form': [field: keyof EmailTemplateForm, value: unknown];
    'update:blockData': [id: string, data: Record<string, unknown>];
    'touch-slug': [];
    deselect: [];
}>();

const activeTab = ref<Tab>('template');

watch(() => props.selectedBlock, (block) => {
    activeTab.value = block ? 'block' : 'template';
});

const blockSchema = computed(() => props.availableBlocks.find((block) => block.type === props.selectedBlock?.type) ?? null);
const selectedContext = computed(() => props.availableContexts.find((context) => context.key === props.form.context_key) ?? props.availableContexts[0] ?? null);
const subjectExample = '{{ site.name }}';

function variableToken(key: string) {
    return `{{ ${key} }}`;
}

function updateBlock(patch: Record<string, unknown>) {
    if (!props.selectedBlock) {
        return;
    }

    emit('update:blockData', props.selectedBlock.id, {
        ...(props.selectedBlock.data ?? {}),
        ...patch,
    });
}

function updateGenericField(key: string, value: unknown) {
    updateBlock({ [key]: value });
}
</script>

<template>
    <aside class="flex w-full shrink-0 flex-col overflow-hidden border-l bg-background">
        <div class="flex shrink-0 border-b">
            <button
                class="-mb-px flex-1 border-b-2 py-2.5 text-xs font-medium transition-colors"
                :class="activeTab === 'template' ? 'border-primary text-foreground' : 'border-transparent text-muted-foreground hover:text-foreground'"
                @click="activeTab = 'template'"
            >
                Template
            </button>
            <button
                class="-mb-px flex-1 border-b-2 py-2.5 text-xs font-medium transition-colors"
                :class="activeTab === 'block' ? 'border-primary text-foreground' : 'border-transparent text-muted-foreground hover:text-foreground'"
                :disabled="!selectedBlock"
                @click="activeTab = 'block'"
            >
                Block
            </button>
        </div>

        <div v-if="activeTab === 'template'" class="flex flex-col gap-4 overflow-y-auto p-4">
            <div class="flex flex-col gap-1.5">
                <Label for="template-name">Name</Label>
                <Input id="template-name" :model-value="form.name" @update:model-value="emit('update:form', 'name', $event)" />
            </div>

            <div class="flex flex-col gap-1.5">
                <Label for="template-slug">Slug</Label>
                <Input
                    id="template-slug"
                    :model-value="form.slug"
                    @focus="emit('touch-slug')"
                    @update:model-value="emit('touch-slug'); emit('update:form', 'slug', $event)"
                />
            </div>

            <div class="flex flex-col gap-1.5">
                <Label for="template-context">Usage</Label>
                <select
                    id="template-context"
                    class="flex h-9 w-full rounded-md border border-input bg-transparent px-3 py-1 text-sm shadow-xs transition-colors focus:outline-none focus:ring-1 focus:ring-ring"
                    :value="form.context_key"
                    @change="emit('update:form', 'context_key', ($event.target as HTMLSelectElement).value)"
                >
                    <option v-for="context in availableContexts" :key="context.key" :value="context.key">
                        {{ context.label }} ({{ context.source ?? 'core' }})
                    </option>
                </select>
                <p v-if="selectedContext?.description" class="text-xs text-muted-foreground">{{ selectedContext.description }}</p>
            </div>

            <div class="flex flex-col gap-1.5">
                <Label for="template-subject">Subject</Label>
                <Input
                    id="template-subject"
                    :model-value="form.subject"
                    placeholder="Welcome to {{ site.name }}"
                    @update:model-value="emit('update:form', 'subject', $event)"
                />
                <p class="text-xs text-muted-foreground">Use variables like <code class="rounded bg-muted px-1">{{ subjectExample }}</code> in the subject and body.</p>
            </div>

            <div class="flex flex-col gap-1.5">
                <Label for="template-description">Description</Label>
                <Textarea
                    id="template-description"
                    :model-value="form.description"
                    rows="3"
                    placeholder="Optional internal notes about this template."
                    @update:model-value="emit('update:form', 'description', $event)"
                />
            </div>

            <Separator />

            <div class="space-y-3">
                <div>
                    <Label class="text-xs uppercase tracking-wide text-muted-foreground">Available variables</Label>
                    <p class="mt-1 text-xs text-muted-foreground">These variables can be inserted anywhere in the subject or body blocks.</p>
                </div>

                <div v-if="selectedContext?.variables?.length" class="space-y-2">
                    <div v-for="variable in selectedContext.variables" :key="variable.key" class="rounded-md border p-3">
                        <p class="font-mono text-xs text-foreground">{{ variableToken(variable.key) }}</p>
                        <p class="mt-1 text-sm">{{ variable.label }}</p>
                        <p v-if="variable.example" class="mt-1 text-xs text-muted-foreground">Example: {{ variable.example }}</p>
                    </div>
                </div>

                <p v-else class="text-xs text-muted-foreground">No variables registered for this template context yet.</p>
            </div>
        </div>

        <div v-else-if="selectedBlock" class="flex flex-col gap-4 overflow-y-auto p-4">
            <div class="flex items-start justify-between gap-3">
                <div>
                    <p class="text-sm font-medium">{{ blockSchema?.label ?? selectedBlock.type }}</p>
                    <p v-if="blockSchema?.description" class="text-xs text-muted-foreground">{{ blockSchema.description }}</p>
                </div>
                <button class="text-xs text-muted-foreground hover:text-foreground" @click="emit('deselect')">Done</button>
            </div>

            <template v-for="field in blockSchema?.fields ?? []" :key="field.key">
                <div class="flex flex-col gap-1.5">
                    <Label :for="`block-${field.key}`">{{ field.label }}</Label>

                    <Textarea
                        v-if="field.type === 'textarea'"
                        :id="`block-${field.key}`"
                        :model-value="String(selectedBlock.data?.[field.key] ?? '')"
                        :placeholder="field.placeholder"
                        rows="4"
                        @update:model-value="updateGenericField(field.key, $event)"
                    />

                    <Switch
                        v-else-if="field.type === 'toggle'"
                        :id="`block-${field.key}`"
                        :model-value="Boolean(selectedBlock.data?.[field.key])"
                        @update:model-value="updateGenericField(field.key, $event)"
                    />

                    <select
                        v-else-if="field.type === 'select'"
                        :id="`block-${field.key}`"
                        class="flex h-9 w-full rounded-md border border-input bg-transparent px-3 py-1 text-sm shadow-xs transition-colors focus:outline-none focus:ring-1 focus:ring-ring"
                        :value="String(selectedBlock.data?.[field.key] ?? '')"
                        @change="updateGenericField(field.key, ($event.target as HTMLSelectElement).value)"
                    >
                        <option v-for="option in field.options ?? []" :key="option.value" :value="option.value">
                            {{ option.label }}
                        </option>
                    </select>

                    <Input
                        v-else
                        :id="`block-${field.key}`"
                        :type="field.type === 'url' ? 'url' : 'text'"
                        :model-value="String(selectedBlock.data?.[field.key] ?? '')"
                        :placeholder="field.placeholder"
                        @update:model-value="updateGenericField(field.key, $event)"
                    />
                </div>
            </template>
        </div>
    </aside>
</template>
