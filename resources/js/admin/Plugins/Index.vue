<script setup lang="ts">
import { router, useForm, usePage } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import { Loader2, Plug, Upload } from 'lucide-vue-next';
import AlertError from '@/components/AlertError.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import AppLayout from '@/layouts/AppLayout.vue';
import admin from '@/routes/admin';
import type { BreadcrumbItem } from '@/types';

interface Plugin {
    id: number;
    slug: string;
    name: string;
    version: string | null;
    description: string | null;
    author: string | null;
    is_enabled: boolean;
    scopes?: string[];
    update_url?: string | null;
}

interface PluginUpdateCheck {
    enabled: boolean;
    currentVersion: string | null;
    latestVersion: string | null;
    releaseUrl: string | null;
    checkedAt: string | null;
    available: boolean;
}

const props = defineProps<{
    plugins: Plugin[];
    pluginUpdateChecks: Record<string, PluginUpdateCheck>;
}>();
const page = usePage<{
    errors?: Record<string, string | string[]>;
    flash?: {
        success?: string | null;
        error?: string | null;
    };
}>();
const togglingPluginId = ref<number | null>(null);

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Settings', href: admin.settings.index.url() },
    { title: 'Plugins', href: admin.plugins.index.url() },
];

const uploadForm = useForm({
    archive: null as File | null,
});

const pluginErrors = computed(() => {
    const error = page.props.errors?.plugin;

    if (Array.isArray(error)) {
        return error.filter(
            (value): value is string =>
                typeof value === 'string' && value.length > 0,
        );
    }

    return typeof error === 'string' && error.length > 0 ? [error] : [];
});

const flashError = computed(() => {
    const error = page.props.flash?.error;

    return typeof error === 'string' && error.length > 0 ? [error] : [];
});

function toggle(plugin: Plugin, enabled?: boolean) {
    const nextEnabled = enabled ?? !plugin.is_enabled;

    router.post(
        admin.plugins.toggle.url({ plugin: plugin.id }),
        {},
        {
            preserveScroll: true,
            onStart: () => {
                togglingPluginId.value = plugin.id;
            },
            onFinish: () => {
                togglingPluginId.value = null;
            },
            onSuccess: () => {
                plugin.is_enabled = nextEnabled;
            },
        },
    );
}

function onArchiveChange(event: Event) {
    const target = event.target as HTMLInputElement;
    uploadForm.archive = target.files?.[0] ?? null;
}

function uploadArchive() {
    if (!uploadForm.archive) return;

    uploadForm.post(admin.plugins.upload.url(), {
        preserveScroll: true,
        forceFormData: true,
        onSuccess: () => {
            uploadForm.reset();
            const input = document.getElementById(
                'plugin-archive',
            ) as HTMLInputElement | null;
            if (input) {
                input.value = '';
            }
        },
    });
}
</script>

<template>
    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="space-y-3">
            <AlertError
                v-if="pluginErrors.length > 0"
                :errors="pluginErrors"
                title="Plugin activation failed."
            />
            <AlertError
                v-if="flashError.length > 0"
                :errors="flashError"
                title="Plugin error."
            />
        </div>

        <div
            class="flex flex-col gap-3 lg:flex-row lg:items-end lg:justify-between"
        >
            <div>
                <h1 class="text-2xl font-semibold tracking-tight">Plugins</h1>
                <p class="text-sm text-muted-foreground">
                    {{ props.plugins.length }} plugin{{
                        props.plugins.length === 1 ? '' : 's'
                    }}
                    found
                </p>
            </div>

            <div
                class="flex w-full flex-col gap-2 rounded-lg border bg-card p-3 lg:w-auto lg:min-w-[28rem]"
            >
                <div class="flex items-center gap-2">
                    <Upload class="h-4 w-4 text-muted-foreground" />
                    <p class="text-sm font-medium">Upload plugin archive</p>
                </div>
                <div class="flex flex-col gap-2 sm:flex-row">
                    <Input
                        id="plugin-archive"
                        type="file"
                        accept=".zip,.tar,.tgz,.tar.gz,application/zip,application/x-tar,application/gzip"
                        @change="onArchiveChange"
                    />
                    <Button
                        :disabled="!uploadForm.archive || uploadForm.processing"
                        @click="uploadArchive"
                    >
                        <Loader2
                            v-if="uploadForm.processing"
                            class="mr-2 h-4 w-4 animate-spin"
                        />
                        <Upload v-else class="mr-2 h-4 w-4" />
                        Upload
                    </Button>
                </div>
                <p class="text-xs text-muted-foreground">
                    Supports .zip, .tar, .tar.gz, and .tgz archives. Activation
                    still runs the installer and migrations.
                </p>
                <p
                    v-if="uploadForm.errors.archive"
                    class="text-xs text-destructive"
                >
                    {{ uploadForm.errors.archive }}
                </p>
            </div>
        </div>

        <!-- Empty state -->
        <div
            v-if="props.plugins.length === 0"
            class="flex flex-col items-center justify-center rounded-lg border border-dashed py-16 text-center"
        >
            <Plug class="mb-3 h-10 w-10 text-muted-foreground/40" />
            <p class="text-sm text-muted-foreground">No plugins found.</p>
            <p class="mt-1 text-xs text-muted-foreground">
                Add a plugin folder with a
                <code class="rounded bg-muted px-1">plugin.json</code> under
                <code class="rounded bg-muted px-1">plugins/</code>.
            </p>
        </div>

        <!-- Plugin list -->
        <div v-else class="space-y-3">
            <Card v-for="plugin in props.plugins" :key="plugin.id">
                <CardHeader class="pb-2">
                    <div class="flex items-start justify-between gap-4">
                        <div class="min-w-0 flex-1">
                            <div class="flex flex-wrap items-center gap-2">
                                <CardTitle class="text-base">{{
                                    plugin.name
                                }}</CardTitle>
                                <Badge
                                    v-if="plugin.version"
                                    variant="outline"
                                    class="text-xs font-normal"
                                >
                                    v{{ plugin.version }}
                                </Badge>
                                <Badge
                                    :variant="
                                        plugin.is_enabled
                                            ? 'default'
                                            : 'secondary'
                                    "
                                    class="text-xs"
                                >
                                    {{
                                        plugin.is_enabled
                                            ? 'Active'
                                            : 'Inactive'
                                    }}
                                </Badge>
                                <Badge
                                    v-if="
                                        props.pluginUpdateChecks[plugin.slug]
                                            ?.available
                                    "
                                    variant="secondary"
                                    class="text-xs"
                                >
                                    Update available
                                </Badge>
                            </div>
                            <CardDescription
                                v-if="plugin.description"
                                class="mt-1"
                            >
                                {{ plugin.description }}
                            </CardDescription>
                        </div>
                        <div class="flex items-center gap-2">
                            <Loader2
                                v-if="togglingPluginId === plugin.id"
                                class="h-4 w-4 animate-spin text-muted-foreground"
                            />
                            <Button
                                type="button"
                                size="sm"
                                variant="outline"
                                :disabled="togglingPluginId === plugin.id"
                                @click.stop="toggle(plugin)"
                            >
                                {{ plugin.is_enabled ? 'Disable' : 'Activate' }}
                            </Button>
                        </div>
                    </div>
                </CardHeader>
                <CardContent class="pb-3 text-xs text-muted-foreground">
                    <div class="flex flex-wrap items-center gap-2">
                        <span v-if="plugin.author">By {{ plugin.author }}</span>
                        <span v-if="plugin.author">&middot;</span>
                        <code class="rounded bg-muted px-1">{{
                            plugin.slug
                        }}</code>
                    </div>

                    <div
                        v-if="props.pluginUpdateChecks[plugin.slug]?.available"
                        class="mt-2 flex flex-wrap items-center gap-2"
                    >
                        <span>
                            Version
                            {{
                                props.pluginUpdateChecks[plugin.slug]
                                    ?.latestVersion
                            }}
                            is available.
                        </span>
                        <a
                            v-if="
                                props.pluginUpdateChecks[plugin.slug]
                                    ?.releaseUrl
                            "
                            :href="
                                props.pluginUpdateChecks[plugin.slug]
                                    ?.releaseUrl ?? '#'
                            "
                            target="_blank"
                            rel="noreferrer"
                            class="font-medium text-primary hover:underline"
                        >
                            View release
                        </a>
                    </div>
                </CardContent>
                <CardContent class="pt-0">
                    <div class="flex flex-wrap gap-1">
                        <Badge
                            v-for="scope in plugin.scopes ?? []"
                            :key="scope"
                            variant="outline"
                            class="text-[11px] font-normal"
                        >
                            {{ scope }}
                        </Badge>
                        <span
                            v-if="(plugin.scopes ?? []).length === 0"
                            class="text-xs text-muted-foreground"
                        >
                            No scopes declared
                        </span>
                    </div>
                </CardContent>
            </Card>
        </div>

        <p class="text-xs text-muted-foreground">
            Enabled plugins load on every request. Changes take effect
            immediately on the next page load.
        </p>
    </AppLayout>
</template>
