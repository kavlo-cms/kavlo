<script setup lang="ts">
import { Plus, X } from 'lucide-vue-next';
import { computed, ref, watch } from 'vue';
import { BlockFieldInput } from '@/block-kit';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Separator } from '@/components/ui/separator';
import { Switch } from '@/components/ui/switch';
import { Textarea } from '@/components/ui/textarea';
import { useBlockSchemas } from '@/composables/useBlockSchemas';
import type { AvailableBlock } from '@/composables/useBlockSchemas';
import type { Block } from '@/types/blocks';

type Tab = 'form' | 'block';
type ActionFieldType =
    | 'text'
    | 'textarea'
    | 'email'
    | 'url'
    | 'select'
    | 'toggle';

export interface FormActionField {
    key: string;
    label: string;
    type: ActionFieldType;
    placeholder?: string;
    options?: { value: string; label: string }[];
}

export interface FormAction {
    key: string;
    label: string;
    description?: string;
    source?: string;
    fields?: FormActionField[];
}

export interface FormEditData {
    id?: number;
    name: string;
    slug: string;
    description: string;
    blocks: Block[];
    submission_action: string;
    action_config: Record<string, string | number | boolean | null>;
}

type FormEditableField = keyof Omit<FormEditData, 'id'>;

const props = defineProps<{
    form: FormEditData;
    selectedBlock: Block | null;
    availableBlocks: AvailableBlock[];
    availableActions: FormAction[];
}>();

const emit = defineEmits<{
    'update:form': [field: FormEditableField, value: unknown];
    'update:blockData': [id: string, data: Record<string, unknown>];
    'touch-slug': [];
    deselect: [];
}>();

const activeTab = ref<Tab>('form');

watch(
    () => props.selectedBlock,
    (block) => {
        activeTab.value = block ? 'block' : 'form';
    },
);

const { getSchema } = useBlockSchemas(() => props.availableBlocks);
const schema = computed(() =>
    props.selectedBlock ? getSchema(props.selectedBlock.type) : null,
);
const selectedAction = computed(
    () =>
        props.availableActions.find(
            (action) => action.key === props.form.submission_action,
        ) ??
        props.availableActions[0] ??
        null,
);

function slugify(value: string) {
    return value
        .toLowerCase()
        .trim()
        .replace(/[^a-z0-9\s-]/g, '')
        .replace(/\s+/g, '-')
        .replace(/-+/g, '-');
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

function updateGenericBlockField(key: string, value: unknown) {
    updateBlock({ [key]: value });
}

function updateBlockLabel(value: string) {
    if (!props.selectedBlock) {
        return;
    }

    const currentKey = String(props.selectedBlock.data?.key ?? '');
    const previousLabel = String(props.selectedBlock.data?.label ?? '');
    const previousSlug = slugify(previousLabel);

    updateBlock({
        label: value,
        key:
            !currentKey || currentKey === previousSlug
                ? slugify(value)
                : currentKey,
    });
}

function updateActionConfig(key: string, value: unknown) {
    emit('update:form', 'action_config', {
        ...(props.form.action_config ?? {}),
        [key]: value,
    });
}

function optionsForSelectedBlock(): { label: string; value: string }[] {
    if (!props.selectedBlock) {
        return [];
    }

    const options = props.selectedBlock.data?.options;

    return Array.isArray(options)
        ? options.filter(
              (item): item is { label: string; value: string } =>
                  typeof item === 'object' && item !== null,
          )
        : [];
}

function syncOptions(options: { label: string; value: string }[]) {
    updateBlock({ options });
}

function addOption() {
    syncOptions([...optionsForSelectedBlock(), { label: '', value: '' }]);
}

function removeOption(index: number) {
    const options = [...optionsForSelectedBlock()];
    options.splice(index, 1);
    syncOptions(options);
}

function updateOption(
    index: number,
    patch: Partial<{ label: string; value: string }>,
) {
    const options = [...optionsForSelectedBlock()];
    const next = { ...options[index], ...patch };

    if (patch.label !== undefined) {
        const previousSlug = slugify(options[index]?.label ?? '');

        if (!next.value || next.value === previousSlug) {
            next.value = slugify(patch.label);
        }
    }

    options[index] = next;
    syncOptions(options);
}
</script>

<template>
    <aside
        class="flex w-full shrink-0 flex-col overflow-hidden border-l bg-background"
    >
        <div class="flex shrink-0 border-b">
            <button
                class="-mb-px flex-1 border-b-2 py-2.5 text-xs font-medium transition-colors"
                :class="
                    activeTab === 'form'
                        ? 'border-primary text-foreground'
                        : 'border-transparent text-muted-foreground hover:text-foreground'
                "
                @click="activeTab = 'form'"
            >
                Form
            </button>
            <button
                class="-mb-px flex-1 border-b-2 py-2.5 text-xs font-medium transition-colors"
                :class="
                    activeTab === 'block'
                        ? 'border-primary text-foreground'
                        : 'border-transparent text-muted-foreground hover:text-foreground'
                "
                :disabled="!selectedBlock"
                @click="activeTab = 'block'"
            >
                Block
            </button>
        </div>

        <div
            v-if="activeTab === 'form'"
            class="flex flex-col gap-4 overflow-y-auto p-4"
        >
            <div class="flex flex-col gap-1.5">
                <Label for="form-name">Name</Label>
                <Input
                    id="form-name"
                    :model-value="form.name"
                    @update:model-value="emit('update:form', 'name', $event)"
                />
            </div>

            <div class="flex flex-col gap-1.5">
                <Label for="form-slug">Slug</Label>
                <Input
                    id="form-slug"
                    :model-value="form.slug"
                    @focus="emit('touch-slug')"
                    @update:model-value="
                        emit('touch-slug');
                        emit('update:form', 'slug', $event);
                    "
                />
            </div>

            <div class="flex flex-col gap-1.5">
                <Label for="form-description">Description</Label>
                <Textarea
                    id="form-description"
                    :model-value="form.description"
                    rows="3"
                    placeholder="Optional internal description"
                    @update:model-value="
                        emit('update:form', 'description', $event)
                    "
                />
            </div>

            <Separator />

            <div class="flex flex-col gap-1.5">
                <Label for="submission-action">Submission Action</Label>
                <select
                    id="submission-action"
                    class="flex h-9 w-full rounded-md border border-input bg-transparent px-3 py-1 text-sm shadow-xs transition-colors focus:ring-1 focus:ring-ring focus:outline-none"
                    :value="form.submission_action"
                    @change="
                        emit(
                            'update:form',
                            'submission_action',
                            ($event.target as HTMLSelectElement).value,
                        )
                    "
                >
                    <option
                        v-for="action in availableActions"
                        :key="action.key"
                        :value="action.key"
                    >
                        {{ action.label }} ({{ action.source ?? 'core' }})
                    </option>
                </select>
                <p
                    v-if="selectedAction?.description"
                    class="text-xs text-muted-foreground"
                >
                    {{ selectedAction.description }}
                </p>
            </div>

            <div
                v-if="selectedAction?.fields?.length"
                class="flex flex-col gap-3"
            >
                <Label
                    class="text-xs tracking-wide text-muted-foreground uppercase"
                    >Action Settings</Label
                >

                <div
                    v-for="field in selectedAction.fields"
                    :key="field.key"
                    class="flex flex-col gap-1.5"
                >
                    <Label :for="`action-${field.key}`">{{
                        field.label
                    }}</Label>

                    <Textarea
                        v-if="field.type === 'textarea'"
                        :id="`action-${field.key}`"
                        :model-value="
                            String(form.action_config?.[field.key] ?? '')
                        "
                        :placeholder="field.placeholder"
                        rows="3"
                        @update:model-value="
                            updateActionConfig(field.key, $event)
                        "
                    />

                    <Switch
                        v-else-if="field.type === 'toggle'"
                        :id="`action-${field.key}`"
                        :model-value="Boolean(form.action_config?.[field.key])"
                        @update:model-value="
                            updateActionConfig(field.key, $event)
                        "
                    />

                    <select
                        v-else-if="field.type === 'select'"
                        :id="`action-${field.key}`"
                        class="flex h-9 w-full rounded-md border border-input bg-transparent px-3 py-1 text-sm shadow-xs transition-colors focus:ring-1 focus:ring-ring focus:outline-none"
                        :value="String(form.action_config?.[field.key] ?? '')"
                        @change="
                            updateActionConfig(
                                field.key,
                                ($event.target as HTMLSelectElement).value,
                            )
                        "
                    >
                        <option value="">Select…</option>
                        <option
                            v-for="option in field.options ?? []"
                            :key="option.value"
                            :value="option.value"
                        >
                            {{ option.label }}
                        </option>
                    </select>

                    <Input
                        v-else
                        :id="`action-${field.key}`"
                        :type="field.type"
                        :model-value="
                            String(form.action_config?.[field.key] ?? '')
                        "
                        :placeholder="field.placeholder"
                        @update:model-value="
                            updateActionConfig(field.key, $event)
                        "
                    />
                </div>
            </div>

            <Separator />

            <div
                class="rounded-lg border bg-muted/20 p-3 text-xs text-muted-foreground"
            >
                Add at least one field block and one button block. Themes and
                plugins can register more submission actions through the action
                registry.
            </div>
        </div>

        <div
            v-else-if="selectedBlock"
            class="flex flex-col gap-4 overflow-y-auto p-4"
        >
            <div class="flex items-start justify-between gap-3">
                <div>
                    <p class="text-sm font-medium">
                        {{ schema?.label ?? selectedBlock.type }}
                    </p>
                    <p
                        v-if="schema?.description"
                        class="text-xs text-muted-foreground"
                    >
                        {{ schema.description }}
                    </p>
                </div>
                <button
                    class="text-xs text-muted-foreground hover:text-foreground"
                    @click="emit('deselect')"
                >
                    Done
                </button>
            </div>

            <template v-if="selectedBlock.type === 'columns'">
                <div
                    v-for="field in schema?.fields ?? []"
                    :key="field.key"
                    class="flex flex-col gap-1.5"
                >
                    <Label :for="`block-${field.key}`">{{ field.label }}</Label>

                    <BlockFieldInput
                        :id="`block-${field.key}`"
                        :field="field"
                        :model-value="selectedBlock.data?.[field.key]"
                        @update:model-value="
                            updateGenericBlockField(field.key, $event)
                        "
                    />
                </div>

                <div
                    class="rounded-lg border bg-muted/20 p-3 text-xs text-muted-foreground"
                >
                    Drag field blocks into each column on the canvas to build
                    the layout.
                </div>
            </template>

            <template v-if="selectedBlock.type === 'input'">
                <div class="flex flex-col gap-1.5">
                    <Label for="block-input-type">Input Type</Label>
                    <select
                        id="block-input-type"
                        class="flex h-9 w-full rounded-md border border-input bg-transparent px-3 py-1 text-sm shadow-xs transition-colors focus:ring-1 focus:ring-ring focus:outline-none"
                        :value="
                            String(selectedBlock.data?.input_type ?? 'text')
                        "
                        @change="
                            updateBlock({
                                input_type: ($event.target as HTMLSelectElement)
                                    .value,
                            })
                        "
                    >
                        <option value="text">Text</option>
                        <option value="email">Email</option>
                        <option value="tel">Phone</option>
                        <option value="number">Number</option>
                        <option value="date">Date</option>
                        <option value="file">File</option>
                    </select>
                </div>
            </template>

            <template v-if="selectedBlock.type !== 'button'">
                <div class="flex flex-col gap-1.5">
                    <Label for="block-label">Label</Label>
                    <Input
                        id="block-label"
                        :model-value="String(selectedBlock.data?.label ?? '')"
                        @update:model-value="updateBlockLabel(String($event))"
                    />
                </div>

                <div class="flex flex-col gap-1.5">
                    <Label for="block-key">Field Key</Label>
                    <Input
                        id="block-key"
                        :model-value="String(selectedBlock.data?.key ?? '')"
                        class="font-mono text-xs"
                        @update:model-value="updateBlock({ key: $event })"
                    />
                </div>

                <div
                    v-if="
                        ['input', 'textarea', 'select'].includes(
                            selectedBlock.type,
                        )
                    "
                    class="flex flex-col gap-1.5"
                >
                    <Label for="block-placeholder">Placeholder</Label>
                    <Input
                        id="block-placeholder"
                        :model-value="
                            String(selectedBlock.data?.placeholder ?? '')
                        "
                        @update:model-value="
                            updateBlock({ placeholder: $event })
                        "
                    />
                </div>

                <div class="flex items-center justify-between">
                    <div>
                        <Label for="block-required" class="cursor-pointer"
                            >Required</Label
                        >
                        <p class="text-xs text-muted-foreground">
                            Require a value before submit
                        </p>
                    </div>
                    <Switch
                        id="block-required"
                        :model-value="Boolean(selectedBlock.data?.required)"
                        @update:model-value="updateBlock({ required: $event })"
                    />
                </div>
            </template>

            <template v-else>
                <div class="flex flex-col gap-1.5">
                    <Label for="button-label">Button Label</Label>
                    <Input
                        id="button-label"
                        :model-value="
                            String(selectedBlock.data?.label ?? 'Submit')
                        "
                        @update:model-value="updateBlock({ label: $event })"
                    />
                </div>
            </template>

            <template
                v-if="
                    ['select', 'checkbox', 'radio'].includes(selectedBlock.type)
                "
            >
                <Separator />

                <div class="flex items-center justify-between">
                    <Label
                        class="text-xs tracking-wide text-muted-foreground uppercase"
                        >Options</Label
                    >
                    <button
                        class="inline-flex items-center gap-1 rounded-md border px-2 py-1 text-xs transition-colors hover:bg-accent"
                        @click="addOption"
                    >
                        <Plus class="h-3 w-3" />
                        Add
                    </button>
                </div>

                <div
                    v-for="(option, index) in optionsForSelectedBlock()"
                    :key="index"
                    class="grid gap-2 sm:grid-cols-[1fr_1fr_auto]"
                >
                    <Input
                        :model-value="option.label"
                        placeholder="Label"
                        @update:model-value="
                            updateOption(index, { label: String($event) })
                        "
                    />
                    <Input
                        :model-value="option.value"
                        placeholder="value"
                        class="font-mono text-xs"
                        @update:model-value="
                            updateOption(index, { value: String($event) })
                        "
                    />
                    <button
                        class="inline-flex h-9 w-9 items-center justify-center rounded-md border text-muted-foreground transition-colors hover:text-destructive"
                        @click="removeOption(index)"
                    >
                        <X class="h-3.5 w-3.5" />
                    </button>
                </div>
            </template>
        </div>
    </aside>
</template>
