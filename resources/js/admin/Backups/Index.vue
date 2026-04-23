<script setup lang="ts">
import { useForm } from '@inertiajs/vue3';
import {
    AlertTriangle,
    Archive,
    CheckCircle2,
    Database,
    Download,
    FolderOpen,
    Loader2,
    Package,
    Paintbrush,
    ShieldAlert,
    Upload,
} from 'lucide-vue-next';
import { computed, ref } from 'vue';
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
import { Label } from '@/components/ui/label';
import AppLayout from '@/layouts/AppLayout.vue';
import admin from '@/routes/admin';
import type { BreadcrumbItem } from '@/types';

const props = defineProps<{
    stats: {
        database_tables: number;
        public_files: number;
        plugins: number;
        themes: number;
    };
    readiness: {
        status: 'ok' | 'warning' | 'fail';
        checked_at: string;
        summary: {
            ok: number;
            warning: number;
            fail: number;
        };
        checks: Array<{
            key: string;
            label: string;
            status: 'ok' | 'warning' | 'fail';
            message: string;
            meta: Record<string, string | number | boolean | null>;
        }>;
    };
    checkpoints: Array<{
        filename: string;
        label: string;
        created_at: string;
        size_bytes: number;
        purpose: string;
    }>;
}>();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Backups', href: admin.backups.index.url() },
];

const restoreForm = useForm({
    archive: null as File | null,
    confirmation: '',
});
const checkpointForm = useForm({
    label: '',
});

interface BackupInspection {
    manifest: {
        filename: string;
        created_at: string;
        app_name: string;
        app_url: string;
        laravel: string;
        php: string;
        stats: {
            database_tables: number;
            public_files: number;
            plugins: number;
            themes: number;
        };
    };
    database: {
        archived_tables: number;
        restorable_tables: number;
        missing_tables: string[];
        extra_tables: string[];
    };
    public_files: number;
}

const inspection = ref<BackupInspection | null>(null);
const inspectError = ref('');
const inspecting = ref(false);
const confirmationReady = computed(
    () => restoreForm.confirmation === 'RESTORE',
);

const sections = [
    {
        title: 'Database export',
        description:
            'Exports every current database table as JSON inside the archive.',
        icon: Database,
        value: `${props.stats.database_tables} table${props.stats.database_tables === 1 ? '' : 's'}`,
    },
    {
        title: 'Uploaded public files',
        description:
            'Includes media, uploaded scripts, and anything stored on the public disk.',
        icon: FolderOpen,
        value: `${props.stats.public_files} file${props.stats.public_files === 1 ? '' : 's'}`,
    },
    {
        title: 'Plugin manifests',
        description:
            'Captures installed plugin database records and plugin.json manifests.',
        icon: Package,
        value: `${props.stats.plugins} plugin${props.stats.plugins === 1 ? '' : 's'}`,
    },
    {
        title: 'Theme manifests',
        description:
            'Captures installed theme database records and theme.json manifests.',
        icon: Paintbrush,
        value: `${props.stats.themes} theme${props.stats.themes === 1 ? '' : 's'}`,
    },
];

function downloadBackup() {
    window.location.href = admin.backups.export.url();
}

function createCheckpoint() {
    checkpointForm.post('/admin/backups/checkpoints', {
        preserveScroll: true,
        onSuccess: () => checkpointForm.reset('label'),
    });
}

function downloadCheckpoint(filename: string) {
    window.location.href = `/admin/backups/checkpoints/download?file=${encodeURIComponent(filename)}`;
}

function onArchiveChange(event: Event) {
    const target = event.target as HTMLInputElement;
    restoreForm.archive = target.files?.[0] ?? null;
    restoreForm.clearErrors('archive');
    inspection.value = null;
    inspectError.value = '';
    restoreForm.confirmation = '';
}

function csrfToken() {
    return (
        document
            .querySelector('meta[name="csrf-token"]')
            ?.getAttribute('content') ?? ''
    );
}

async function inspectBackup() {
    if (!restoreForm.archive) {
        return;
    }

    inspectError.value = '';
    inspection.value = null;
    inspecting.value = true;

    try {
        const formData = new FormData();
        formData.append('archive', restoreForm.archive);

        const response = await fetch('/admin/backups/inspect', {
            method: 'POST',
            headers: {
                'X-CSRF-TOKEN': csrfToken(),
                Accept: 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
            },
            body: formData,
        });

        const payload = await response.json();

        if (!response.ok) {
            inspectError.value = payload.message ?? 'Backup inspection failed.';

            return;
        }

        inspection.value = payload as BackupInspection;
    } catch {
        inspectError.value = 'Backup inspection failed.';
    } finally {
        inspecting.value = false;
    }
}

function restoreBackup() {
    if (!restoreForm.archive) {
        return;
    }

    restoreForm.post(admin.backups.restore.url(), {
        preserveScroll: true,
        forceFormData: true,
        onSuccess: () => {
            restoreForm.reset();

            const input = document.getElementById(
                'backup-archive',
            ) as HTMLInputElement | null;

            if (input) {
                input.value = '';
            }

            inspection.value = null;
            inspectError.value = '';
        },
    });
}

function readinessVariant(status: 'ok' | 'warning' | 'fail') {
    if (status === 'fail') {
        return 'destructive';
    }

    if (status === 'warning') {
        return 'secondary';
    }

    return 'default';
}

function readinessIcon(status: 'ok' | 'warning' | 'fail') {
    if (status === 'fail') {
        return ShieldAlert;
    }

    if (status === 'warning') {
        return AlertTriangle;
    }

    return CheckCircle2;
}

function formatBytes(bytes: number) {
    if (bytes < 1024) {
        return `${bytes} B`;
    }

    if (bytes < 1024 * 1024) {
        return `${(bytes / 1024).toFixed(1)} KB`;
    }

    return `${(bytes / (1024 * 1024)).toFixed(1)} MB`;
}
</script>

<template>
    <AppLayout :breadcrumbs="breadcrumbs">
        <div
            class="flex flex-col gap-3 lg:flex-row lg:items-center lg:justify-between"
        >
            <div>
                <h1 class="text-2xl font-semibold tracking-tight">Backups</h1>
                <p class="text-sm text-muted-foreground">
                    Create a downloadable recovery archive of CMS data and
                    uploaded assets.
                </p>
            </div>

            <Button @click="downloadBackup">
                <Download class="mr-2 h-4 w-4" />
                Download Backup
            </Button>
        </div>

        <Card>
            <CardHeader>
                <div
                    class="flex flex-col gap-3 lg:flex-row lg:items-start lg:justify-between"
                >
                    <div class="space-y-2">
                        <div class="flex items-center gap-2">
                            <CardTitle>Deployment readiness</CardTitle>
                            <Badge
                                :variant="
                                    readinessVariant(props.readiness.status)
                                "
                            >
                                <component
                                    :is="readinessIcon(props.readiness.status)"
                                    class="mr-1 h-3 w-3"
                                />
                                {{
                                    props.readiness.status === 'ok'
                                        ? 'Ready'
                                        : props.readiness.status === 'warning'
                                          ? 'Review warnings'
                                          : 'Action required'
                                }}
                            </Badge>
                        </div>
                        <CardDescription>
                            Checks runtime health, pending migrations, rollback
                            coverage, writable backup storage, debug mode, and
                            maintenance state.
                        </CardDescription>
                    </div>

                    <div
                        class="flex flex-wrap gap-2 text-xs text-muted-foreground"
                    >
                        <span class="rounded-md border px-2 py-1"
                            >{{ props.readiness.summary.ok }} ok</span
                        >
                        <span class="rounded-md border px-2 py-1"
                            >{{
                                props.readiness.summary.warning
                            }}
                            warnings</span
                        >
                        <span class="rounded-md border px-2 py-1"
                            >{{ props.readiness.summary.fail }} failing</span
                        >
                    </div>
                </div>
            </CardHeader>
            <CardContent class="grid gap-3 md:grid-cols-2">
                <div
                    v-for="check in props.readiness.checks"
                    :key="check.key"
                    class="rounded-lg border p-4"
                >
                    <div class="flex items-start justify-between gap-3">
                        <div>
                            <p class="text-sm font-medium">{{ check.label }}</p>
                            <p class="mt-1 text-xs text-muted-foreground">
                                {{ check.message }}
                            </p>
                        </div>
                        <Badge :variant="readinessVariant(check.status)">{{
                            check.status
                        }}</Badge>
                    </div>

                    <dl
                        v-if="Object.keys(check.meta).length"
                        class="mt-3 space-y-1 text-xs text-muted-foreground"
                    >
                        <div
                            v-for="(value, key) in check.meta"
                            :key="`${check.key}-${key}`"
                            class="flex gap-2"
                        >
                            <dt class="font-medium text-foreground">
                                {{ key }}
                            </dt>
                            <dd class="min-w-0 break-words">{{ value }}</dd>
                        </div>
                    </dl>
                </div>
            </CardContent>
        </Card>

        <Card>
            <CardHeader>
                <div
                    class="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between"
                >
                    <div>
                        <CardTitle>Rollback checkpoints</CardTitle>
                        <CardDescription>
                            Create a named checkpoint before a deployment so you
                            have a known-good archive ready to download or
                            restore from the existing backup flow.
                        </CardDescription>
                    </div>

                    <form
                        class="flex flex-col gap-2 sm:flex-row"
                        @submit.prevent="createCheckpoint"
                    >
                        <Input
                            v-model="checkpointForm.label"
                            placeholder="Before 2026-04 release"
                            class="sm:w-72"
                        />
                        <Button
                            type="submit"
                            :disabled="
                                checkpointForm.processing ||
                                checkpointForm.label.trim().length === 0
                            "
                        >
                            <Archive class="mr-2 h-4 w-4" />
                            Create Checkpoint
                        </Button>
                    </form>
                </div>
            </CardHeader>
            <CardContent class="space-y-3">
                <p
                    v-if="checkpointForm.errors.label"
                    class="text-xs text-destructive"
                >
                    {{ checkpointForm.errors.label }}
                </p>

                <div v-if="props.checkpoints.length" class="space-y-3">
                    <div
                        v-for="checkpoint in props.checkpoints"
                        :key="checkpoint.filename"
                        class="flex flex-col gap-3 rounded-lg border p-4 lg:flex-row lg:items-center lg:justify-between"
                    >
                        <div class="min-w-0">
                            <p class="text-sm font-medium">
                                {{ checkpoint.label }}
                            </p>
                            <p class="mt-1 text-xs text-muted-foreground">
                                {{ checkpoint.created_at }} ·
                                {{ formatBytes(checkpoint.size_bytes) }}
                            </p>
                            <p
                                class="mt-1 font-mono text-xs text-muted-foreground"
                            >
                                {{ checkpoint.filename }}
                            </p>
                        </div>

                        <Button
                            variant="outline"
                            @click="downloadCheckpoint(checkpoint.filename)"
                        >
                            <Download class="mr-2 h-4 w-4" />
                            Download
                        </Button>
                    </div>
                </div>
                <p
                    v-else
                    class="rounded-lg border border-dashed p-4 text-sm text-muted-foreground"
                >
                    No rollback checkpoints have been stored yet.
                </p>
            </CardContent>
        </Card>

        <Card>
            <CardHeader>
                <div class="flex items-center gap-3">
                    <div
                        class="flex h-10 w-10 items-center justify-center rounded-lg bg-muted"
                    >
                        <Archive class="h-5 w-5 text-muted-foreground" />
                    </div>
                    <div>
                        <CardTitle>Manual recovery export</CardTitle>
                        <CardDescription>
                            The archive is generated on demand and includes
                            structured snapshots of the database plus public
                            storage files.
                        </CardDescription>
                    </div>
                </div>
            </CardHeader>
            <CardContent class="grid gap-4 md:grid-cols-2">
                <div
                    v-for="section in sections"
                    :key="section.title"
                    class="rounded-lg border p-4"
                >
                    <div class="flex items-start gap-3">
                        <div
                            class="mt-0.5 flex h-8 w-8 items-center justify-center rounded-md bg-muted"
                        >
                            <component
                                :is="section.icon"
                                class="h-4 w-4 text-muted-foreground"
                            />
                        </div>
                        <div class="min-w-0">
                            <p class="text-sm font-medium">
                                {{ section.title }}
                            </p>
                            <p class="mt-1 text-xs text-muted-foreground">
                                {{ section.description }}
                            </p>
                            <p class="mt-2 text-xs font-medium text-foreground">
                                {{ section.value }}
                            </p>
                        </div>
                    </div>
                </div>
            </CardContent>
        </Card>

        <Card>
            <CardHeader>
                <div class="flex items-center gap-3">
                    <div
                        class="flex h-10 w-10 items-center justify-center rounded-lg bg-muted"
                    >
                        <Upload class="h-5 w-5 text-muted-foreground" />
                    </div>
                    <div>
                        <CardTitle>Restore from backup ZIP</CardTitle>
                        <CardDescription>
                            Replaces current database rows and public storage
                            files with the contents of an exported CMS backup
                            archive.
                        </CardDescription>
                    </div>
                </div>
            </CardHeader>
            <CardContent class="space-y-4">
                <div
                    class="rounded-lg border border-amber-200 bg-amber-50 p-4 text-sm text-amber-900 dark:border-amber-900/60 dark:bg-amber-950/30 dark:text-amber-100"
                >
                    <div class="flex items-start gap-3">
                        <AlertTriangle class="mt-0.5 h-4 w-4 shrink-0" />
                        <p>
                            This is destructive. Current database content and
                            files in <code>storage/app/public</code> will be
                            replaced.
                        </p>
                    </div>
                </div>

                <div class="flex flex-col gap-2 sm:flex-row">
                    <Input
                        id="backup-archive"
                        type="file"
                        accept=".zip,application/zip,application/x-zip-compressed,application/octet-stream"
                        @change="onArchiveChange"
                    />
                    <Button
                        :disabled="
                            !restoreForm.archive ||
                            restoreForm.processing ||
                            inspecting
                        "
                        variant="outline"
                        @click="inspectBackup"
                    >
                        <Loader2
                            v-if="inspecting"
                            class="mr-2 h-4 w-4 animate-spin"
                        />
                        <Archive v-else class="mr-2 h-4 w-4" />
                        Inspect Archive
                    </Button>
                    <Button
                        :disabled="
                            !restoreForm.archive ||
                            restoreForm.processing ||
                            inspecting ||
                            !inspection ||
                            !confirmationReady
                        "
                        variant="destructive"
                        @click="restoreBackup"
                    >
                        <Loader2
                            v-if="restoreForm.processing"
                            class="mr-2 h-4 w-4 animate-spin"
                        />
                        <Upload v-else class="mr-2 h-4 w-4" />
                        Restore Backup
                    </Button>
                </div>

                <div v-if="inspection" class="space-y-4 rounded-lg border p-4">
                    <div>
                        <p class="text-sm font-medium">
                            {{ inspection.manifest.filename }}
                        </p>
                        <p class="text-xs text-muted-foreground">
                            Created {{ inspection.manifest.created_at }} from
                            {{ inspection.manifest.app_name }} ({{
                                inspection.manifest.app_url
                            }})
                        </p>
                    </div>

                    <div class="grid gap-3 md:grid-cols-3">
                        <div class="rounded-md border p-3">
                            <p class="text-xs text-muted-foreground">
                                Database tables in archive
                            </p>
                            <p class="mt-1 text-sm font-medium">
                                {{ inspection.database.archived_tables }}
                                archived /
                                {{ inspection.database.restorable_tables }}
                                restorable
                            </p>
                        </div>
                        <div class="rounded-md border p-3">
                            <p class="text-xs text-muted-foreground">
                                Public files
                            </p>
                            <p class="mt-1 text-sm font-medium">
                                {{ inspection.public_files }}
                            </p>
                        </div>
                        <div class="rounded-md border p-3">
                            <p class="text-xs text-muted-foreground">Runtime</p>
                            <p class="mt-1 text-sm font-medium">
                                {{ inspection.manifest.app_name }} / PHP
                                {{ inspection.manifest.php }}
                            </p>
                        </div>
                    </div>

                    <div
                        v-if="
                            inspection.database.missing_tables.length ||
                            inspection.database.extra_tables.length
                        "
                        class="grid gap-3 md:grid-cols-2"
                    >
                        <div
                            v-if="inspection.database.missing_tables.length"
                            class="rounded-md border border-amber-200 bg-amber-50 p-3 text-sm dark:border-amber-900/60 dark:bg-amber-950/30"
                        >
                            <p class="font-medium">
                                Current tables missing from archive
                            </p>
                            <p class="mt-2 text-xs text-muted-foreground">
                                {{
                                    inspection.database.missing_tables.join(
                                        ', ',
                                    )
                                }}
                            </p>
                        </div>
                        <div
                            v-if="inspection.database.extra_tables.length"
                            class="rounded-md border border-amber-200 bg-amber-50 p-3 text-sm dark:border-amber-900/60 dark:bg-amber-950/30"
                        >
                            <p class="font-medium">
                                Archive tables missing locally
                            </p>
                            <p class="mt-2 text-xs text-muted-foreground">
                                {{
                                    inspection.database.extra_tables.join(', ')
                                }}
                            </p>
                        </div>
                    </div>

                    <div class="space-y-2">
                        <Label for="restore-confirmation"
                            >Type RESTORE to confirm</Label
                        >
                        <Input
                            id="restore-confirmation"
                            v-model="restoreForm.confirmation"
                            placeholder="RESTORE"
                        />
                        <p class="text-xs text-muted-foreground">
                            Restore stays disabled until the archive has been
                            inspected and you type the confirmation phrase
                            exactly.
                        </p>
                    </div>
                </div>

                <p class="text-xs text-muted-foreground">
                    Upload a ZIP created from this CMS backup screen. Stored
                    rollback checkpoints can be downloaded above and then
                    inspected here before restore.
                </p>
                <p v-if="inspectError" class="text-xs text-destructive">
                    {{ inspectError }}
                </p>
                <p
                    v-if="restoreForm.errors.archive"
                    class="text-xs text-destructive"
                >
                    {{ restoreForm.errors.archive }}
                </p>
                <p
                    v-if="restoreForm.errors.confirmation"
                    class="text-xs text-destructive"
                >
                    {{ restoreForm.errors.confirmation }}
                </p>
            </CardContent>
        </Card>
    </AppLayout>
</template>
