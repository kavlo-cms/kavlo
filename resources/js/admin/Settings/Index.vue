<script setup lang="ts">
import { Link, useForm } from '@inertiajs/vue3';
import { Code, Save } from 'lucide-vue-next';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { Textarea } from '@/components/ui/textarea';
import AppLayout from '@/layouts/AppLayout.vue';
import admin from '@/routes/admin';
import type { BreadcrumbItem } from '@/types';

interface Page {
    id: number;
    title: string;
}

interface Settings {
    site_name?: string;
    site_tagline?: string;
    admin_email?: string;
    meta_title_format?: string;
    meta_description?: string;
    homepage_id?: string | null;
    favicon?: string;
}

const props = defineProps<{
    settings: Settings;
    pages: Page[];
}>();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Settings', href: admin.settings.index.url() },
    { title: 'General', href: admin.settings.index.url() },
];

const form = useForm({
    site_name: props.settings.site_name ?? '',
    site_tagline: props.settings.site_tagline ?? '',
    admin_email: props.settings.admin_email ?? '',
    meta_title_format:
        props.settings.meta_title_format ?? '%page_title% | %site_name%',
    meta_description: props.settings.meta_description ?? '',
    homepage_id: props.settings.homepage_id ?? null,
    favicon: props.settings.favicon ?? '',
});

function save() {
    form.put(admin.settings.update.url(), { preserveScroll: true });
}
</script>

<template>
    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex items-center justify-between">
            <h1 class="text-2xl font-semibold tracking-tight">
                General Settings
            </h1>
            <Button :disabled="form.processing" @click="save">
                <Save class="mr-2 h-4 w-4" />
                Save Changes
            </Button>
        </div>

        <div class="grid gap-6 lg:grid-cols-3">
            <!-- Main column -->
            <div class="space-y-6 lg:col-span-2">
                <!-- Site Identity -->
                <Card>
                    <CardHeader>
                        <CardTitle>Site Identity</CardTitle>
                        <CardDescription
                            >Basic information about your site.</CardDescription
                        >
                    </CardHeader>
                    <CardContent class="space-y-4">
                        <div class="space-y-1.5">
                            <Label for="site_name">Site Name</Label>
                            <Input
                                id="site_name"
                                v-model="form.site_name"
                                placeholder="My Awesome Site"
                            />
                            <p
                                v-if="form.errors.site_name"
                                class="text-sm text-destructive"
                            >
                                {{ form.errors.site_name }}
                            </p>
                        </div>

                        <div class="space-y-1.5">
                            <Label for="site_tagline">Tagline</Label>
                            <Input
                                id="site_tagline"
                                v-model="form.site_tagline"
                                placeholder="Just another CMS site"
                            />
                            <p class="text-xs text-muted-foreground">
                                A short phrase that describes your site.
                            </p>
                        </div>

                        <div class="space-y-1.5">
                            <Label for="admin_email"
                                >Administration Email</Label
                            >
                            <Input
                                id="admin_email"
                                v-model="form.admin_email"
                                type="email"
                                placeholder="admin@example.com"
                            />
                            <p
                                v-if="form.errors.admin_email"
                                class="text-sm text-destructive"
                            >
                                {{ form.errors.admin_email }}
                            </p>
                            <p class="text-xs text-muted-foreground">
                                Used for system notifications.
                            </p>
                        </div>

                        <div class="space-y-1.5">
                            <Label for="favicon">Favicon URL</Label>
                            <Input
                                id="favicon"
                                v-model="form.favicon"
                                placeholder="/favicon.ico"
                            />
                            <p class="text-xs text-muted-foreground">
                                Relative path or full URL to your favicon image.
                            </p>
                        </div>
                    </CardContent>
                </Card>

                <!-- SEO -->
                <Card>
                    <CardHeader>
                        <CardTitle>SEO &amp; Metadata</CardTitle>
                        <CardDescription
                            >Default SEO settings applied across your
                            site.</CardDescription
                        >
                    </CardHeader>
                    <CardContent class="space-y-4">
                        <div class="space-y-1.5">
                            <Label for="meta_title_format">Title Format</Label>
                            <Input
                                id="meta_title_format"
                                v-model="form.meta_title_format"
                                placeholder="%page_title% | %site_name%"
                            />
                            <p class="text-xs text-muted-foreground">
                                Available tokens:
                                <code class="rounded bg-muted px-1 text-xs"
                                    >%page_title%</code
                                >,
                                <code class="rounded bg-muted px-1 text-xs"
                                    >%site_name%</code
                                >
                            </p>
                        </div>

                        <div class="space-y-1.5">
                            <Label for="meta_description"
                                >Default Meta Description</Label
                            >
                            <Textarea
                                id="meta_description"
                                v-model="form.meta_description"
                                placeholder="A brief description of your site shown in search results."
                                rows="3"
                            />
                            <p class="text-xs text-muted-foreground">
                                Used on pages without a custom meta description.
                                Aim for 150–160 characters.
                            </p>
                        </div>
                    </CardContent>
                </Card>
            </div>

            <!-- Side column -->
            <div class="space-y-6">
                <!-- Reading -->
                <Card>
                    <CardHeader>
                        <CardTitle>Reading</CardTitle>
                        <CardDescription
                            >Control what visitors see on the front
                            page.</CardDescription
                        >
                    </CardHeader>
                    <CardContent class="space-y-4">
                        <div class="space-y-1.5">
                            <Label for="homepage_id">Homepage</Label>
                            <Select
                                :model-value="form.homepage_id ?? '__none__'"
                                @update:model-value="
                                    (val) =>
                                        (form.homepage_id =
                                            typeof val === 'string' &&
                                            val !== '__none__'
                                                ? val
                                                : null)
                                "
                            >
                                <SelectTrigger id="homepage_id">
                                    <SelectValue
                                        placeholder="— default (latest posts) —"
                                    />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem value="__none__"
                                        >— default (latest posts) —</SelectItem
                                    >
                                    <SelectItem
                                        v-for="page in props.pages"
                                        :key="page.id"
                                        :value="String(page.id)"
                                    >
                                        {{ page.title }}
                                    </SelectItem>
                                </SelectContent>
                            </Select>
                            <p class="text-xs text-muted-foreground">
                                The page displayed at your site root.
                            </p>
                        </div>
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader>
                        <CardTitle>Scripts &amp; Tracking</CardTitle>
                        <CardDescription
                            >Manage inline snippets, external URLs, and uploaded
                            script files from one place.</CardDescription
                        >
                    </CardHeader>
                    <CardContent class="space-y-4">
                        <p class="text-sm text-muted-foreground">
                            Use the dedicated script manager for analytics tags,
                            chat widgets, and custom JavaScript placement.
                        </p>
                        <Button
                            as-child
                            variant="outline"
                            class="w-full justify-start"
                        >
                            <Link href="/admin/scripts">
                                <Code class="mr-2 h-4 w-4" />
                                Open Script Manager
                            </Link>
                        </Button>
                    </CardContent>
                </Card>
            </div>
        </div>
    </AppLayout>
</template>
