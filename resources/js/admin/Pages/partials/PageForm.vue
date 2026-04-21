<script setup lang="ts">
import { router, useForm, usePage } from '@inertiajs/vue3';
import { Trash2 } from 'lucide-vue-next';
import { ref, watch } from 'vue';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { Separator } from '@/components/ui/separator';
import { Switch } from '@/components/ui/switch';
import { Textarea } from '@/components/ui/textarea';
import admin from '@/routes/admin';

interface PageType {
    type: string;
    label: string;
    view: string;
    source?: string;
    source_label?: string;
}

interface PageData {
    id?: number;
    title: string;
    slug: string;
    type: string;
    content: string;
    is_published: boolean;
    is_homepage: boolean;
}

const props = defineProps<{
    page?: PageData;
    pageTypes?: PageType[];
}>();

const pageProps = usePage<{ pageTypes?: PageType[] }>().props;
const resolvedTypes: PageType[] = props.pageTypes ??
    pageProps.pageTypes ?? [
        { type: 'page', label: 'Standard Page', view: 'pages.show' },
    ];

const isEditing = !!props.page?.id;

const form = useForm({
    title: props.page?.title ?? '',
    slug: props.page?.slug ?? '',
    type: props.page?.type ?? 'page',
    content: props.page?.content ?? '',
    is_published: props.page?.is_published ?? false,
    is_homepage: props.page?.is_homepage ?? false,
});

function slugify(value: string) {
    return value
        .toLowerCase()
        .trim()
        .replace(/[^a-z0-9\s-]/g, '')
        .replace(/\s+/g, '-')
        .replace(/-+/g, '-');
}

const slugTouched = ref(isEditing);

watch(
    () => form.title,
    (val) => {
        if (!slugTouched.value) {
            form.slug = slugify(val);
        }
    },
);

function submit() {
    if (isEditing) {
        form.put(admin.pages.update(props.page!.id!).url);
    } else {
        form.post(admin.pages.store().url);
    }
}

function deletePage() {
    if (confirm('Are you sure you want to delete this page?')) {
        router.delete(admin.pages.destroy(props.page!.id!).url);
    }
}
</script>

<template>
    <form @submit.prevent="submit" class="flex flex-col gap-6">
        <div class="grid gap-6 lg:grid-cols-3">
            <!-- Main content -->
            <div class="flex flex-col gap-4 lg:col-span-2">
                <div class="flex flex-col gap-4 rounded-lg border p-6">
                    <div class="grid gap-2">
                        <Label for="title">Title</Label>
                        <Input
                            id="title"
                            v-model="form.title"
                            placeholder="Page title"
                            :class="{ 'border-destructive': form.errors.title }"
                        />
                        <p
                            v-if="form.errors.title"
                            class="text-sm text-destructive"
                        >
                            {{ form.errors.title }}
                        </p>
                    </div>

                    <div class="grid gap-2">
                        <Label for="slug">Slug</Label>
                        <div
                            class="flex items-center rounded-md border focus-within:ring-2 focus-within:ring-ring"
                        >
                            <span class="pl-3 text-sm text-muted-foreground"
                                >/</span
                            >
                            <input
                                id="slug"
                                v-model="form.slug"
                                @input="slugTouched = true"
                                class="flex-1 bg-transparent py-2 pr-3 pl-1 font-mono text-sm outline-none placeholder:text-muted-foreground"
                                placeholder="page-slug"
                            />
                        </div>
                        <p
                            v-if="form.errors.slug"
                            class="text-sm text-destructive"
                        >
                            {{ form.errors.slug }}
                        </p>
                    </div>

                    <div class="grid gap-2">
                        <Label for="content">Content</Label>
                        <Textarea
                            id="content"
                            v-model="form.content"
                            placeholder="Page content…"
                            class="min-h-48 font-mono text-sm"
                        />
                    </div>
                </div>
            </div>

            <!-- Sidebar -->
            <div class="flex flex-col gap-4">
                <div class="flex flex-col gap-4 rounded-lg border p-6">
                    <h3 class="text-sm font-medium">Settings</h3>
                    <Separator />

                    <div class="grid gap-2">
                        <Label for="type">Page Type</Label>
                        <Select v-model="form.type">
                            <SelectTrigger id="type">
                                <SelectValue placeholder="Select type" />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem
                                    v-for="pt in resolvedTypes"
                                    :key="pt.type"
                                    :value="pt.type"
                                >
                                    {{ pt.label }} ({{
                                        pt.source_label ?? pt.source ?? 'Core'
                                    }})
                                </SelectItem>
                            </SelectContent>
                        </Select>
                    </div>

                    <Separator />

                    <div class="flex items-center justify-between gap-4">
                        <div>
                            <Label for="is_published" class="text-sm"
                                >Published</Label
                            >
                            <p class="text-xs text-muted-foreground">
                                Make this page visible
                            </p>
                        </div>
                        <Switch id="is_published" v-model="form.is_published" />
                    </div>

                    <div class="flex items-center gap-2">
                        <Checkbox id="is_homepage" v-model="form.is_homepage" />
                        <div>
                            <Label
                                for="is_homepage"
                                class="cursor-pointer text-sm"
                                >Set as homepage</Label
                            >
                            <p class="text-xs text-muted-foreground">
                                Replaces the current homepage
                            </p>
                        </div>
                    </div>
                </div>

                <div class="flex flex-col gap-2">
                    <Button type="submit" :disabled="form.processing">
                        {{ isEditing ? 'Save changes' : 'Create page' }}
                    </Button>
                    <Button
                        v-if="isEditing"
                        type="button"
                        variant="destructive"
                        @click="deletePage"
                    >
                        <Trash2 class="mr-2 h-4 w-4" />
                        Delete page
                    </Button>
                </div>
            </div>
        </div>
    </form>
</template>
