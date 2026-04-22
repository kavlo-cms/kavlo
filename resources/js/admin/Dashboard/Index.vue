<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import {
    AlertTriangle,
    ArrowRightLeft,
    ArrowUpRight,
    CheckCircle2,
    Clock,
    FileText,
    HardDrive,
    Image,
    Mail,
    Users,
} from 'lucide-vue-next';
import { Badge } from '@/components/ui/badge';
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import AppLayout from '@/layouts/AppLayout.vue';
import type { BreadcrumbItem } from '@/types';

interface Stats {
    total_pages: number;
    published_pages: number;
    draft_pages: number;
    media_files: number;
    menus: number;
    total_users: number;
    total_redirects: number;
    active_redirects: number;
}

interface Revision {
    id: number;
    label: string;
    created_at: string;
    page: { id: number; title: string; slug: string } | null;
    user: { id: number; name: string } | null;
}

interface HealthCheck {
    key: string;
    label: string;
    status: 'ok' | 'warning' | 'fail';
    message: string;
    meta: Record<string, unknown>;
}

interface SystemHealth {
    status: 'ok' | 'warning' | 'fail';
    checked_at: string;
    summary: {
        ok: number;
        warning: number;
        fail: number;
    };
    checks: HealthCheck[];
}

interface UpdateCheck {
    enabled: boolean;
    currentVersion: string;
    latestVersion: string | null;
    releaseUrl: string | null;
    publishedAt: string | null;
    checkedAt: string | null;
    available: boolean;
}

const props = defineProps<{
    stats: Stats;
    recentRevisions: Revision[];
    systemHealth: SystemHealth;
    updateCheck: UpdateCheck;
}>();

const breadcrumbs: BreadcrumbItem[] = [{ title: 'Dashboard', href: '/admin' }];

function timeAgo(dateStr: string): string {
    const diff = Math.floor((Date.now() - new Date(dateStr).getTime()) / 1000);

    if (diff < 60) {
        return `${diff}s ago`;
    }

    if (diff < 3600) {
        return `${Math.floor(diff / 60)}m ago`;
    }

    if (diff < 86400) {
        return `${Math.floor(diff / 3600)}h ago`;
    }

    return `${Math.floor(diff / 86400)}d ago`;
}

function healthVariant(status: HealthCheck['status'] | SystemHealth['status']) {
    if (status === 'fail') {
        return 'destructive';
    }

    if (status === 'warning') {
        return 'outline';
    }

    return 'default';
}

function healthIcon(check: HealthCheck) {
    if (check.key === 'storage') {
        return HardDrive;
    }

    if (check.key === 'mail') {
        return Mail;
    }

    if (check.status === 'ok') {
        return CheckCircle2;
    }

    return AlertTriangle;
}
</script>

<template>
    <AppLayout :breadcrumbs="breadcrumbs">
        <h1 class="text-2xl font-semibold tracking-tight">Dashboard</h1>

        <Alert
            v-if="props.updateCheck.available"
            class="border-primary/30 bg-primary/5"
        >
            <AlertTriangle class="size-4 text-primary" />
            <AlertTitle>Update available</AlertTitle>
            <AlertDescription class="gap-2">
                <p>
                    Version
                    <strong>{{ props.updateCheck.latestVersion }}</strong> is
                    available. You are currently running
                    <strong>{{ props.updateCheck.currentVersion }}</strong
                    >.
                </p>
                <div class="flex flex-wrap items-center gap-3">
                    <a
                        v-if="props.updateCheck.releaseUrl"
                        :href="props.updateCheck.releaseUrl"
                        target="_blank"
                        rel="noreferrer"
                        class="inline-flex items-center gap-1 text-sm font-medium text-primary hover:underline"
                    >
                        View release
                        <ArrowUpRight class="size-4" />
                    </a>
                    <span
                        v-if="props.updateCheck.publishedAt"
                        class="text-xs text-muted-foreground"
                    >
                        Published {{ timeAgo(props.updateCheck.publishedAt) }}
                    </span>
                </div>
            </AlertDescription>
        </Alert>

        <!-- Stat cards -->
        <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
            <Card>
                <CardHeader
                    class="flex flex-row items-center justify-between pb-2"
                >
                    <CardTitle class="text-sm font-medium text-muted-foreground"
                        >Total Pages</CardTitle
                    >
                    <FileText class="h-4 w-4 text-muted-foreground" />
                </CardHeader>
                <CardContent>
                    <p class="text-3xl font-bold">
                        {{ props.stats.total_pages }}
                    </p>
                    <p class="mt-1 text-xs text-muted-foreground">
                        {{ props.stats.published_pages }} published &middot;
                        {{ props.stats.draft_pages }} drafts
                    </p>
                </CardContent>
            </Card>

            <Card>
                <CardHeader
                    class="flex flex-row items-center justify-between pb-2"
                >
                    <CardTitle class="text-sm font-medium text-muted-foreground"
                        >Media Files</CardTitle
                    >
                    <Image class="h-4 w-4 text-muted-foreground" />
                </CardHeader>
                <CardContent>
                    <p class="text-3xl font-bold">
                        {{ props.stats.media_files }}
                    </p>
                    <p class="mt-1 text-xs text-muted-foreground">
                        In media library
                    </p>
                </CardContent>
            </Card>

            <Card>
                <CardHeader
                    class="flex flex-row items-center justify-between pb-2"
                >
                    <CardTitle class="text-sm font-medium text-muted-foreground"
                        >Users</CardTitle
                    >
                    <Users class="h-4 w-4 text-muted-foreground" />
                </CardHeader>
                <CardContent>
                    <p class="text-3xl font-bold">
                        {{ props.stats.total_users }}
                    </p>
                    <p class="mt-1 text-xs text-muted-foreground">
                        Registered accounts
                    </p>
                </CardContent>
            </Card>

            <Card>
                <CardHeader
                    class="flex flex-row items-center justify-between pb-2"
                >
                    <CardTitle class="text-sm font-medium text-muted-foreground"
                        >Redirects</CardTitle
                    >
                    <ArrowRightLeft class="h-4 w-4 text-muted-foreground" />
                </CardHeader>
                <CardContent>
                    <p class="text-3xl font-bold">
                        {{ props.stats.total_redirects }}
                    </p>
                    <p class="mt-1 text-xs text-muted-foreground">
                        {{ props.stats.active_redirects }} active
                    </p>
                </CardContent>
            </Card>
        </div>

        <Card>
            <CardHeader
                class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between"
            >
                <div>
                    <CardTitle class="text-base font-semibold"
                        >System Health</CardTitle
                    >
                    <p class="text-sm text-muted-foreground">
                        Checked {{ timeAgo(props.systemHealth.checked_at) }}
                    </p>
                </div>

                <div class="flex flex-wrap items-center gap-2">
                    <Badge
                        :variant="healthVariant(props.systemHealth.status)"
                        class="capitalize"
                    >
                        {{ props.systemHealth.status }}
                    </Badge>
                    <Badge variant="outline"
                        >{{ props.systemHealth.summary.ok }} healthy</Badge
                    >
                    <Badge variant="outline"
                        >{{
                            props.systemHealth.summary.warning
                        }}
                        warnings</Badge
                    >
                    <Badge variant="outline"
                        >{{ props.systemHealth.summary.fail }} failures</Badge
                    >
                </div>
            </CardHeader>

            <CardContent class="grid gap-3 md:grid-cols-2 xl:grid-cols-3">
                <div
                    v-for="check in props.systemHealth.checks"
                    :key="check.key"
                    class="rounded-lg border p-4"
                >
                    <div class="flex items-start justify-between gap-3">
                        <div class="flex items-center gap-2">
                            <component
                                :is="healthIcon(check)"
                                class="h-4 w-4 text-muted-foreground"
                            />
                            <h3 class="font-medium">{{ check.label }}</h3>
                        </div>

                        <Badge
                            :variant="healthVariant(check.status)"
                            class="capitalize"
                        >
                            {{ check.status }}
                        </Badge>
                    </div>

                    <p class="mt-3 text-sm text-muted-foreground">
                        {{ check.message }}
                    </p>

                    <dl
                        v-if="Object.keys(check.meta ?? {}).length > 0"
                        class="mt-3 space-y-1 text-xs text-muted-foreground"
                    >
                        <div
                            v-for="(value, key) in check.meta"
                            :key="key"
                            class="flex gap-2"
                        >
                            <dt class="font-medium capitalize">
                                {{ String(key).replaceAll('_', ' ') }}:
                            </dt>
                            <dd class="break-all">{{ value }}</dd>
                        </div>
                    </dl>
                </div>
            </CardContent>
        </Card>

        <!-- Recent activity -->
        <Card>
            <CardHeader class="flex flex-row items-center gap-2 pb-3">
                <Clock class="h-4 w-4 text-muted-foreground" />
                <CardTitle class="text-base font-semibold"
                    >Recent Activity</CardTitle
                >
            </CardHeader>
            <CardContent class="p-0">
                <div
                    v-if="props.recentRevisions.length === 0"
                    class="px-6 py-8 text-center text-sm text-muted-foreground"
                >
                    No revisions yet.
                </div>
                <ul v-else class="divide-y">
                    <li
                        v-for="rev in props.recentRevisions"
                        :key="rev.id"
                        class="flex items-center justify-between gap-4 px-6 py-3 text-sm"
                    >
                        <div class="min-w-0">
                            <Link
                                v-if="rev.page"
                                :href="`/admin/pages/${rev.page.id}/edit`"
                                class="truncate font-medium hover:underline"
                            >
                                {{ rev.page.title }}
                            </Link>
                            <span
                                v-else
                                class="truncate font-medium text-muted-foreground"
                                >Deleted page</span
                            >
                            <p class="truncate text-xs text-muted-foreground">
                                {{ rev.label }}
                                <span v-if="rev.user"
                                    >&middot; {{ rev.user.name }}</span
                                >
                            </p>
                        </div>
                        <span class="shrink-0 text-xs text-muted-foreground">{{
                            timeAgo(rev.created_at)
                        }}</span>
                    </li>
                </ul>
            </CardContent>
        </Card>
    </AppLayout>
</template>
