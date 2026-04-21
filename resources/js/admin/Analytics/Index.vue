<script setup lang="ts">
import { BarChart3, ExternalLink, Eye, FileText, Users } from 'lucide-vue-next';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import AppLayout from '@/layouts/AppLayout.vue';
import type { BreadcrumbItem } from '@/types';

interface Summary {
    total_views: number;
    unique_visitors: number;
    views_today: number;
    unique_today: number;
}

interface TrendPoint {
    date: string;
    label: string;
    views: number;
    unique_visitors: number;
}

interface TopPage {
    page_id: number;
    title: string;
    slug: string | null;
    views: number;
    unique_visitors: number;
    last_viewed_at: string | null;
}

interface TopReferrer {
    host: string;
    visits: number;
}

const props = defineProps<{
    summary: Summary;
    trend: TrendPoint[];
    top_pages: TopPage[];
    top_referrers: TopReferrer[];
}>();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Analytics', href: '/admin/analytics' },
];

const maxViews = Math.max(...props.trend.map((point) => point.views), 1);

function formatDate(value: string | null) {
    return value ? new Date(value).toLocaleString() : '—';
}
</script>

<template>
    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex items-center justify-between">
            <div>
                <h1 class="text-2xl font-semibold tracking-tight">Analytics</h1>
                <p class="text-sm text-muted-foreground">
                    Track public page views, daily uniques, top content, and
                    referrer sources.
                </p>
            </div>
        </div>

        <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
            <Card>
                <CardHeader
                    class="flex flex-row items-center justify-between pb-2"
                >
                    <CardTitle class="text-sm font-medium text-muted-foreground"
                        >Total Views</CardTitle
                    >
                    <Eye class="h-4 w-4 text-muted-foreground" />
                </CardHeader>
                <CardContent
                    ><p class="text-3xl font-bold">
                        {{ props.summary.total_views }}
                    </p></CardContent
                >
            </Card>

            <Card>
                <CardHeader
                    class="flex flex-row items-center justify-between pb-2"
                >
                    <CardTitle class="text-sm font-medium text-muted-foreground"
                        >Unique Visitors</CardTitle
                    >
                    <Users class="h-4 w-4 text-muted-foreground" />
                </CardHeader>
                <CardContent
                    ><p class="text-3xl font-bold">
                        {{ props.summary.unique_visitors }}
                    </p></CardContent
                >
            </Card>

            <Card>
                <CardHeader
                    class="flex flex-row items-center justify-between pb-2"
                >
                    <CardTitle class="text-sm font-medium text-muted-foreground"
                        >Views Today</CardTitle
                    >
                    <BarChart3 class="h-4 w-4 text-muted-foreground" />
                </CardHeader>
                <CardContent
                    ><p class="text-3xl font-bold">
                        {{ props.summary.views_today }}
                    </p></CardContent
                >
            </Card>

            <Card>
                <CardHeader
                    class="flex flex-row items-center justify-between pb-2"
                >
                    <CardTitle class="text-sm font-medium text-muted-foreground"
                        >Unique Today</CardTitle
                    >
                    <Users class="h-4 w-4 text-muted-foreground" />
                </CardHeader>
                <CardContent
                    ><p class="text-3xl font-bold">
                        {{ props.summary.unique_today }}
                    </p></CardContent
                >
            </Card>
        </div>

        <div class="grid gap-6 xl:grid-cols-[1.2fr_0.8fr]">
            <Card>
                <CardHeader>
                    <CardTitle>Last 14 Days</CardTitle>
                    <CardDescription
                        >Daily page views and unique visitors across the public
                        site.</CardDescription
                    >
                </CardHeader>
                <CardContent>
                    <div class="space-y-3">
                        <div
                            v-for="point in props.trend"
                            :key="point.date"
                            class="grid grid-cols-[4rem_1fr_auto_auto] items-center gap-3 text-sm"
                        >
                            <div class="text-muted-foreground">
                                {{ point.label }}
                            </div>
                            <div class="h-2 rounded-full bg-muted">
                                <div
                                    class="h-2 rounded-full bg-primary"
                                    :style="{
                                        width: `${Math.max((point.views / maxViews) * 100, point.views ? 6 : 0)}%`,
                                    }"
                                />
                            </div>
                            <div class="w-16 text-right font-medium">
                                {{ point.views }}
                            </div>
                            <div class="w-16 text-right text-muted-foreground">
                                {{ point.unique_visitors }} unique
                            </div>
                        </div>
                    </div>
                </CardContent>
            </Card>

            <Card>
                <CardHeader>
                    <CardTitle>Top Referrers</CardTitle>
                    <CardDescription
                        >External domains sending traffic to the
                        site.</CardDescription
                    >
                </CardHeader>
                <CardContent>
                    <div
                        v-if="!props.top_referrers.length"
                        class="text-sm text-muted-foreground"
                    >
                        No external referrers recorded yet.
                    </div>
                    <div v-else class="space-y-3">
                        <div
                            v-for="referrer in props.top_referrers"
                            :key="referrer.host"
                            class="flex items-center justify-between gap-3 text-sm"
                        >
                            <div class="flex items-center gap-2 font-medium">
                                <ExternalLink
                                    class="h-3.5 w-3.5 text-muted-foreground"
                                />
                                {{ referrer.host }}
                            </div>
                            <div class="text-muted-foreground">
                                {{ referrer.visits }} visits
                            </div>
                        </div>
                    </div>
                </CardContent>
            </Card>
        </div>

        <Card>
            <CardHeader>
                <CardTitle>Top Pages</CardTitle>
                <CardDescription
                    >Most viewed public pages by total views and unique
                    visitors.</CardDescription
                >
            </CardHeader>
            <CardContent>
                <div
                    v-if="!props.top_pages.length"
                    class="text-sm text-muted-foreground"
                >
                    No page views recorded yet.
                </div>
                <div v-else class="space-y-3">
                    <div
                        v-for="page in props.top_pages"
                        :key="page.page_id"
                        class="grid grid-cols-[minmax(0,1fr)_6rem_6rem_11rem] items-center gap-4 rounded-lg border p-4 text-sm"
                    >
                        <div class="min-w-0">
                            <div class="flex items-center gap-2 font-medium">
                                <FileText
                                    class="h-4 w-4 text-muted-foreground"
                                />
                                <span class="truncate">{{ page.title }}</span>
                            </div>
                            <p
                                class="truncate font-mono text-xs text-muted-foreground"
                            >
                                {{ page.slug ?? '/' }}
                            </p>
                        </div>
                        <div class="text-right">{{ page.views }} views</div>
                        <div class="text-right text-muted-foreground">
                            {{ page.unique_visitors }} unique
                        </div>
                        <div class="text-right text-xs text-muted-foreground">
                            {{ formatDate(page.last_viewed_at) }}
                        </div>
                    </div>
                </div>
            </CardContent>
        </Card>
    </AppLayout>
</template>
