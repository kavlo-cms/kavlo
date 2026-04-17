<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { ArrowRightLeft, Clock, FileText, Globe, Image, Navigation, Users } from 'lucide-vue-next';
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

const props = defineProps<{
    stats: Stats;
    recentRevisions: Revision[];
}>();

const breadcrumbs: BreadcrumbItem[] = [{ title: 'Dashboard', href: '/admin' }];

function timeAgo(dateStr: string): string {
    const diff = Math.floor((Date.now() - new Date(dateStr).getTime()) / 1000);
    if (diff < 60) return `${diff}s ago`;
    if (diff < 3600) return `${Math.floor(diff / 60)}m ago`;
    if (diff < 86400) return `${Math.floor(diff / 3600)}h ago`;
    return `${Math.floor(diff / 86400)}d ago`;
}
</script>

<template>
    <AppLayout :breadcrumbs="breadcrumbs">
        <h1 class="text-2xl font-semibold tracking-tight">Dashboard</h1>

        <!-- Stat cards -->
        <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
            <Card>
                <CardHeader class="flex flex-row items-center justify-between pb-2">
                    <CardTitle class="text-sm font-medium text-muted-foreground">Total Pages</CardTitle>
                    <FileText class="h-4 w-4 text-muted-foreground" />
                </CardHeader>
                <CardContent>
                    <p class="text-3xl font-bold">{{ props.stats.total_pages }}</p>
                    <p class="mt-1 text-xs text-muted-foreground">
                        {{ props.stats.published_pages }} published &middot; {{ props.stats.draft_pages }} drafts
                    </p>
                </CardContent>
            </Card>

            <Card>
                <CardHeader class="flex flex-row items-center justify-between pb-2">
                    <CardTitle class="text-sm font-medium text-muted-foreground">Media Files</CardTitle>
                    <Image class="h-4 w-4 text-muted-foreground" />
                </CardHeader>
                <CardContent>
                    <p class="text-3xl font-bold">{{ props.stats.media_files }}</p>
                    <p class="mt-1 text-xs text-muted-foreground">In media library</p>
                </CardContent>
            </Card>

            <Card>
                <CardHeader class="flex flex-row items-center justify-between pb-2">
                    <CardTitle class="text-sm font-medium text-muted-foreground">Users</CardTitle>
                    <Users class="h-4 w-4 text-muted-foreground" />
                </CardHeader>
                <CardContent>
                    <p class="text-3xl font-bold">{{ props.stats.total_users }}</p>
                    <p class="mt-1 text-xs text-muted-foreground">Registered accounts</p>
                </CardContent>
            </Card>

            <Card>
                <CardHeader class="flex flex-row items-center justify-between pb-2">
                    <CardTitle class="text-sm font-medium text-muted-foreground">Redirects</CardTitle>
                    <ArrowRightLeft class="h-4 w-4 text-muted-foreground" />
                </CardHeader>
                <CardContent>
                    <p class="text-3xl font-bold">{{ props.stats.total_redirects }}</p>
                    <p class="mt-1 text-xs text-muted-foreground">{{ props.stats.active_redirects }} active</p>
                </CardContent>
            </Card>
        </div>

        <!-- Recent activity -->
        <Card>
            <CardHeader class="flex flex-row items-center gap-2 pb-3">
                <Clock class="h-4 w-4 text-muted-foreground" />
                <CardTitle class="text-base font-semibold">Recent Activity</CardTitle>
            </CardHeader>
            <CardContent class="p-0">
                <div v-if="props.recentRevisions.length === 0" class="px-6 py-8 text-center text-sm text-muted-foreground">
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
                            <span v-else class="truncate font-medium text-muted-foreground">Deleted page</span>
                            <p class="truncate text-xs text-muted-foreground">
                                {{ rev.label }}
                                <span v-if="rev.user">&middot; {{ rev.user.name }}</span>
                            </p>
                        </div>
                        <span class="shrink-0 text-xs text-muted-foreground">{{ timeAgo(rev.created_at) }}</span>
                    </li>
                </ul>
            </CardContent>
        </Card>
    </AppLayout>
</template>
