<script setup lang="ts">
import { Link, useForm } from '@inertiajs/vue3';
import { computed } from 'vue';
import { Code, Plus, Save, Trash2 } from 'lucide-vue-next';
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
import { Switch } from '@/components/ui/switch';
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

interface SiteLanguage {
    code: string;
    name: string;
    is_active: boolean;
    is_default?: boolean;
}

const props = defineProps<{
    settings: Settings;
    pages: Page[];
    languages: SiteLanguage[];
    defaultLocale: string;
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
    languages: props.languages.map((language) => ({
        code: language.code,
        name: language.name,
        is_active: language.is_active,
    })),
    default_locale: props.defaultLocale,
});

const defaultLanguageOptions = computed(() =>
    form.languages.filter(
        (language) => language.is_active && language.code.trim() !== '',
    ),
);

function addLanguage() {
    form.languages.push({
        code: '',
        name: '',
        is_active: true,
    });
}

function removeLanguage(index: number) {
    if (form.languages.length <= 1) {
        return;
    }

    const [removed] = form.languages.splice(index, 1);

    if (removed?.code === form.default_locale) {
        form.default_locale =
            defaultLanguageOptions.value[0]?.code ??
            form.languages[0]?.code ??
            '';
    }
}

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

                <Card>
                    <CardHeader>
                        <CardTitle>Site Languages</CardTitle>
                        <CardDescription
                            >Choose the default language and the translated
                            locales editors can add to pages.</CardDescription
                        >
                    </CardHeader>
                    <CardContent class="space-y-4">
                        <div class="space-y-3">
                            <div
                                v-for="(language, index) in form.languages"
                                :key="`${index}-${language.code}`"
                                class="rounded-lg border p-4"
                            >
                                <div
                                    class="grid gap-4 md:grid-cols-[1fr,12rem,auto]"
                                >
                                    <div class="space-y-1.5">
                                        <Label :for="`language-name-${index}`"
                                            >Language name</Label
                                        >
                                        <Input
                                            :id="`language-name-${index}`"
                                            v-model="language.name"
                                            placeholder="English"
                                        />
                                    </div>

                                    <div class="space-y-1.5">
                                        <Label :for="`language-code-${index}`"
                                            >Language code</Label
                                        >
                                        <Input
                                            :id="`language-code-${index}`"
                                            v-model="language.code"
                                            placeholder="en"
                                        />
                                    </div>

                                    <div class="flex items-end justify-end">
                                        <Button
                                            type="button"
                                            variant="ghost"
                                            size="icon"
                                            :disabled="
                                                form.languages.length <= 1
                                            "
                                            @click="removeLanguage(index)"
                                        >
                                            <Trash2 class="h-4 w-4" />
                                        </Button>
                                    </div>
                                </div>

                                <div
                                    class="mt-4 flex items-center justify-between gap-4"
                                >
                                    <div>
                                        <Label
                                            :for="`language-active-${index}`"
                                            class="text-sm"
                                            >Active</Label
                                        >
                                        <p
                                            class="text-xs text-muted-foreground"
                                        >
                                            Inactive languages stay stored but
                                            stop resolving on the public site.
                                        </p>
                                    </div>
                                    <Switch
                                        :id="`language-active-${index}`"
                                        v-model="language.is_active"
                                    />
                                </div>
                            </div>
                        </div>

                        <div class="flex items-center justify-between gap-4">
                            <div>
                                <Label for="default_locale"
                                    >Default language</Label
                                >
                                <p class="text-xs text-muted-foreground">
                                    The default locale uses unprefixed URLs like
                                    <code class="rounded bg-muted px-1 text-xs"
                                        >/about</code
                                    >. Other locales use
                                    <code class="rounded bg-muted px-1 text-xs"
                                        >/no/about</code
                                    >.
                                </p>
                            </div>
                            <div class="w-full max-w-xs">
                                <Select v-model="form.default_locale">
                                    <SelectTrigger id="default_locale">
                                        <SelectValue
                                            placeholder="Select language"
                                        />
                                    </SelectTrigger>
                                    <SelectContent>
                                        <SelectItem
                                            v-for="language in defaultLanguageOptions"
                                            :key="language.code"
                                            :value="language.code"
                                        >
                                            {{ language.name || language.code }}
                                            ({{ language.code }})
                                        </SelectItem>
                                    </SelectContent>
                                </Select>
                            </div>
                        </div>

                        <p
                            v-if="
                                form.errors.languages ||
                                form.errors.default_locale
                            "
                            class="text-sm text-destructive"
                        >
                            {{
                                form.errors.languages ??
                                form.errors.default_locale
                            }}
                        </p>

                        <Button
                            type="button"
                            variant="outline"
                            @click="addLanguage"
                        >
                            <Plus class="mr-2 h-4 w-4" />
                            Add language
                        </Button>
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
