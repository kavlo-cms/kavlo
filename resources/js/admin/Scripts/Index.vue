<script setup lang="ts">
import { Link, router, useForm } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import { Code, Pencil, Plus, Save, Trash2 } from 'lucide-vue-next';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Textarea } from '@/components/ui/textarea';
import AppLayout from '@/layouts/AppLayout.vue';
import type { BreadcrumbItem } from '@/types';

interface SiteScript {
    id: number;
    name: string;
    placement: string;
    source_type: string;
    source_url: string | null;
    file_path: string | null;
    file_url: string | null;
    file_name: string | null;
    inline_content: string | null;
    load_strategy: string;
    sort_order: number;
    is_enabled: boolean;
    notes: string | null;
    updated_at: string | null;
}

interface Option {
    value: string;
    label: string;
    description: string;
}

const props = defineProps<{
    scripts: SiteScript[];
    placementOptions: Option[];
    sourceTypeOptions: Option[];
    loadStrategyOptions: Option[];
}>();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Settings', href: '/admin/settings' },
    { title: 'Scripts', href: '/admin/scripts' },
];

const editingId = ref<number | null>(null);

const form = useForm<{
    name: string;
    placement: string;
    source_type: string;
    source_url: string;
    inline_content: string;
    load_strategy: string;
    sort_order: number;
    is_enabled: boolean;
    notes: string;
    file: File | null;
}>({
    name: '',
    placement: props.placementOptions[0]?.value ?? 'head',
    source_type: props.sourceTypeOptions[0]?.value ?? 'inline',
    source_url: '',
    inline_content: '',
    load_strategy: props.loadStrategyOptions[0]?.value ?? 'blocking',
    sort_order: 0,
    is_enabled: true,
    notes: '',
    file: null,
});

const selectedScript = computed(() => props.scripts.find((script) => script.id === editingId.value) ?? null);

const placementLabel = (value: string) => props.placementOptions.find((option) => option.value === value)?.label ?? value;
const sourceTypeLabel = (value: string) => props.sourceTypeOptions.find((option) => option.value === value)?.label ?? value;
const loadStrategyLabel = (value: string) => props.loadStrategyOptions.find((option) => option.value === value)?.label ?? value;

function resetForm() {
    editingId.value = null;
    form.reset();
    form.clearErrors();
    form.placement = props.placementOptions[0]?.value ?? 'head';
    form.source_type = props.sourceTypeOptions[0]?.value ?? 'inline';
    form.load_strategy = props.loadStrategyOptions[0]?.value ?? 'blocking';
    form.sort_order = 0;
    form.is_enabled = true;
}

function editScript(script: SiteScript) {
    editingId.value = script.id;
    form.name = script.name;
    form.placement = script.placement;
    form.source_type = script.source_type;
    form.source_url = script.source_url ?? '';
    form.inline_content = script.inline_content ?? '';
    form.load_strategy = script.load_strategy;
    form.sort_order = script.sort_order;
    form.is_enabled = script.is_enabled;
    form.notes = script.notes ?? '';
    form.file = null;
    form.clearErrors();
}

function saveScript() {
    form.transform((data) => ({
        ...data,
        is_enabled: data.is_enabled ? 1 : 0,
        _method: editingId.value ? 'put' : undefined,
    })).post(editingId.value ? `/admin/scripts/${editingId.value}` : '/admin/scripts', {
        preserveScroll: true,
        forceFormData: true,
        onSuccess: () => resetForm(),
    });
}

function deleteScript(script: SiteScript) {
    router.delete(`/admin/scripts/${script.id}`, {
        preserveScroll: true,
        onSuccess: () => {
            if (editingId.value === script.id) {
                resetForm();
            }
        },
    });
}

function onFileChange(event: Event) {
    const target = event.target as HTMLInputElement;
    form.file = target.files?.[0] ?? null;
}
</script>

<template>
    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
            <div>
                <h1 class="text-2xl font-semibold tracking-tight">Script Manager</h1>
                <p class="text-sm text-muted-foreground">
                    Manage inline snippets, external URLs, and uploaded JavaScript files.
                </p>
            </div>

            <Button variant="outline" @click="resetForm">
                <Plus class="mr-2 h-4 w-4" />
                New Script
            </Button>
        </div>

        <div class="grid gap-6 xl:grid-cols-[1.15fr_0.85fr]">
            <Card>
                <CardHeader>
                    <CardTitle>Registered Scripts</CardTitle>
                    <CardDescription>
                        These scripts are rendered on the public site according to their placement and order.
                    </CardDescription>
                </CardHeader>
                <CardContent class="space-y-4">
                    <div v-if="props.scripts.length === 0" class="rounded-lg border border-dashed px-4 py-10 text-center text-sm text-muted-foreground">
                        No managed scripts yet. Create one from the form on the right.
                    </div>

                    <div v-else class="space-y-3">
                        <div
                            v-for="script in props.scripts"
                            :key="script.id"
                            class="rounded-lg border p-4 transition-colors"
                            :class="editingId === script.id ? 'border-primary bg-muted/30' : ''"
                        >
                            <div class="flex flex-col gap-3 lg:flex-row lg:items-start lg:justify-between">
                                <div class="space-y-2">
                                    <div class="flex flex-wrap items-center gap-2">
                                        <h2 class="font-medium">{{ script.name }}</h2>
                                        <Badge :variant="script.is_enabled ? 'default' : 'secondary'">
                                            {{ script.is_enabled ? 'Enabled' : 'Disabled' }}
                                        </Badge>
                                        <Badge variant="outline">{{ placementLabel(script.placement) }}</Badge>
                                        <Badge variant="outline">{{ sourceTypeLabel(script.source_type) }}</Badge>
                                        <Badge variant="outline">{{ loadStrategyLabel(script.load_strategy) }}</Badge>
                                    </div>

                                    <p class="text-sm text-muted-foreground">
                                        <template v-if="script.source_type === 'url'">
                                            {{ script.source_url }}
                                        </template>
                                        <template v-else-if="script.source_type === 'upload'">
                                            {{ script.file_name }}
                                        </template>
                                        <template v-else>
                                            {{ (script.inline_content ?? '').slice(0, 140) || 'Inline snippet' }}
                                        </template>
                                    </p>

                                    <div class="text-xs text-muted-foreground">
                                        Order: {{ script.sort_order }}
                                    </div>
                                </div>

                                <div class="flex gap-2">
                                    <Button variant="outline" size="sm" @click="editScript(script)">
                                        <Pencil class="mr-2 h-3.5 w-3.5" />
                                        Edit
                                    </Button>
                                    <Button variant="outline" size="sm" @click="deleteScript(script)">
                                        <Trash2 class="mr-2 h-3.5 w-3.5" />
                                        Delete
                                    </Button>
                                </div>
                            </div>
                        </div>
                    </div>
                </CardContent>
            </Card>

            <Card>
                <CardHeader>
                    <CardTitle class="flex items-center gap-2">
                        <Code class="h-4 w-4" />
                        {{ editingId ? 'Edit Script' : 'Add Script' }}
                    </CardTitle>
                    <CardDescription>
                        Choose where the script should load and whether it comes from inline code, a URL, or an uploaded file.
                    </CardDescription>
                </CardHeader>
                <CardContent class="space-y-4">
                    <div class="space-y-2">
                        <Label for="script-name">Name</Label>
                        <Input id="script-name" v-model="form.name" placeholder="Google Tag Manager" />
                        <p v-if="form.errors.name" class="text-sm text-destructive">{{ form.errors.name }}</p>
                    </div>

                    <div class="grid gap-4 sm:grid-cols-2">
                        <div class="space-y-2">
                            <Label for="script-placement">Placement</Label>
                            <Select :model-value="form.placement" @update:model-value="(value: string) => (form.placement = value)">
                                <SelectTrigger id="script-placement">
                                    <SelectValue placeholder="Select placement" />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem v-for="option in props.placementOptions" :key="option.value" :value="option.value">
                                        {{ option.label }}
                                    </SelectItem>
                                </SelectContent>
                            </Select>
                            <p v-if="form.errors.placement" class="text-sm text-destructive">{{ form.errors.placement }}</p>
                        </div>

                        <div class="space-y-2">
                            <Label for="script-source-type">Source Type</Label>
                            <Select :model-value="form.source_type" @update:model-value="(value: string) => (form.source_type = value)">
                                <SelectTrigger id="script-source-type">
                                    <SelectValue placeholder="Select source type" />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem v-for="option in props.sourceTypeOptions" :key="option.value" :value="option.value">
                                        {{ option.label }}
                                    </SelectItem>
                                </SelectContent>
                            </Select>
                            <p v-if="form.errors.source_type" class="text-sm text-destructive">{{ form.errors.source_type }}</p>
                        </div>
                    </div>

                    <div v-if="form.source_type === 'url'" class="space-y-2">
                        <Label for="script-source-url">External URL</Label>
                        <Input id="script-source-url" v-model="form.source_url" placeholder="https://cdn.example.com/widget.js" />
                        <p v-if="form.errors.source_url" class="text-sm text-destructive">{{ form.errors.source_url }}</p>
                    </div>

                    <div v-if="form.source_type === 'upload'" class="space-y-2">
                        <Label for="script-file">Upload Script File</Label>
                        <Input id="script-file" type="file" accept=".js,.mjs,text/javascript,application/javascript" @change="onFileChange" />
                        <p class="text-xs text-muted-foreground">
                            Upload a JavaScript file from your computer. Existing uploaded files stay in place until you replace them.
                        </p>
                        <p v-if="selectedScript?.file_url" class="text-xs text-muted-foreground">
                            Current file:
                            <a :href="selectedScript.file_url" target="_blank" rel="noopener noreferrer" class="underline">
                                {{ selectedScript.file_name }}
                            </a>
                        </p>
                        <p v-if="form.errors.file" class="text-sm text-destructive">{{ form.errors.file }}</p>
                    </div>

                    <div v-if="form.source_type === 'inline'" class="space-y-2">
                        <Label for="script-inline">Inline Script or Embed Snippet</Label>
                        <Textarea
                            id="script-inline"
                            v-model="form.inline_content"
                            rows="10"
                            class="font-mono text-xs"
                            placeholder="console.log('hello');"
                        />
                        <p class="text-xs text-muted-foreground">
                            Paste JavaScript code or a full embed snippet that already contains its own
                            <code class="rounded bg-muted px-1">&lt;script&gt;</code> tag.
                        </p>
                        <p v-if="form.errors.inline_content" class="text-sm text-destructive">{{ form.errors.inline_content }}</p>
                    </div>

                    <div class="grid gap-4 sm:grid-cols-2">
                        <div class="space-y-2">
                            <Label for="script-load-strategy">Load Strategy</Label>
                            <Select :model-value="form.load_strategy" @update:model-value="(value: string) => (form.load_strategy = value)">
                                <SelectTrigger id="script-load-strategy">
                                    <SelectValue placeholder="Select load strategy" />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem v-for="option in props.loadStrategyOptions" :key="option.value" :value="option.value">
                                        {{ option.label }}
                                    </SelectItem>
                                </SelectContent>
                            </Select>
                            <p v-if="form.errors.load_strategy" class="text-sm text-destructive">{{ form.errors.load_strategy }}</p>
                        </div>

                        <div class="space-y-2">
                            <Label for="script-sort-order">Order</Label>
                            <Input id="script-sort-order" v-model="form.sort_order" type="number" min="0" />
                            <p v-if="form.errors.sort_order" class="text-sm text-destructive">{{ form.errors.sort_order }}</p>
                        </div>
                    </div>

                    <div class="space-y-2">
                        <Label for="script-notes">Notes</Label>
                        <Textarea
                            id="script-notes"
                            v-model="form.notes"
                            rows="3"
                            placeholder="Optional reminder about where this script came from or what it powers."
                        />
                        <p v-if="form.errors.notes" class="text-sm text-destructive">{{ form.errors.notes }}</p>
                    </div>

                    <label class="flex items-center gap-2 text-sm text-muted-foreground">
                        <input v-model="form.is_enabled" type="checkbox" class="h-4 w-4 rounded border-input text-primary" />
                        Enable this script on the public site
                    </label>

                    <div class="flex flex-wrap gap-2 pt-2">
                        <Button :disabled="form.processing" @click="saveScript">
                            <Save class="mr-2 h-4 w-4" />
                            {{ editingId ? 'Save Script' : 'Create Script' }}
                        </Button>
                        <Button v-if="editingId" variant="outline" @click="resetForm">
                            Cancel
                        </Button>
                        <Button variant="ghost" as-child>
                            <Link href="/admin/settings">
                                Back to Settings
                            </Link>
                        </Button>
                    </div>
                </CardContent>
            </Card>
        </div>
    </AppLayout>
</template>
