<script setup lang="ts">
import { router } from '@inertiajs/vue3';
import { RefreshCw } from 'lucide-vue-next';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import AppLayout from '@/layouts/AppLayout.vue';
import admin from '@/routes/admin';
import type { BreadcrumbItem } from '@/types';

interface ActivityItem {
    id: number;
    log_name: string | null;
    description: string;
    subject_type: string | null;
    subject_id: number | null;
    subject_label: string | null;
    target: string | null;
    route_name: string | null;
    changed_fields: string[];
    causer: { id: number; name: string } | null;
    created_at: string;
}

interface Paginated {
    data: ActivityItem[];
    current_page: number;
    last_page: number;
    per_page: number;
    total: number;
    prev_page_url: string | null;
    next_page_url: string | null;
}

defineProps<{ log: Paginated }>();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Activity Log', href: admin.activity.index.url() },
];

function badgeVariant(description: string) {
    if (description.startsWith('created')) return 'default';
    if (description.startsWith('deleted')) return 'destructive';
    return 'secondary';
}

function timeAgo(dateStr: string): string {
    const diff = Math.floor((Date.now() - new Date(dateStr).getTime()) / 1000);
    if (diff < 60) return `${diff}s ago`;
    if (diff < 3600) return `${Math.floor(diff / 60)}m ago`;
    if (diff < 86400) return `${Math.floor(diff / 3600)}h ago`;
    return new Date(dateStr).toLocaleDateString();
}

function subjectText(item: ActivityItem): string {
    if (item.subject_label) return item.subject_label;
    if (item.target) return item.target;
    if (item.subject_type)
        return `${item.subject_type}${item.subject_id ? ` #${item.subject_id}` : ''}`;
    return '—';
}
</script>

<template>
    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex items-center justify-between">
            <h1 class="text-2xl font-semibold tracking-tight">Activity Log</h1>
            <Button variant="outline" size="sm" @click="router.reload()">
                <RefreshCw class="mr-2 h-3.5 w-3.5" />
                Refresh
            </Button>
        </div>

        <div
            v-if="log.data.length === 0"
            class="flex min-h-40 items-center justify-center rounded-lg border border-dashed text-sm text-muted-foreground"
        >
            No activity recorded yet.
        </div>

        <div v-else class="overflow-hidden rounded-lg border">
            <table class="w-full text-sm">
                <thead
                    class="bg-muted/50 text-xs tracking-wide text-muted-foreground uppercase"
                >
                    <tr>
                        <th class="w-28 px-4 py-2.5 text-left font-medium">
                            Action
                        </th>
                        <th class="px-4 py-2.5 text-left font-medium">
                            Subject
                        </th>
                        <th class="px-4 py-2.5 text-left font-medium">
                            Changed Fields
                        </th>
                        <th class="w-32 px-4 py-2.5 text-left font-medium">
                            By
                        </th>
                        <th class="w-28 px-4 py-2.5 text-left font-medium">
                            When
                        </th>
                    </tr>
                </thead>
                <tbody class="divide-y">
                    <tr
                        v-for="item in log.data"
                        :key="item.id"
                        class="transition-colors hover:bg-muted/30"
                    >
                        <td class="px-4 py-2.5">
                            <Badge
                                :variant="badgeVariant(item.description)"
                                class="text-xs capitalize"
                            >
                                {{ item.description }}
                            </Badge>
                        </td>
                        <td class="px-4 py-2.5 text-xs">
                            <div class="font-medium">
                                {{ subjectText(item) }}
                            </div>
                            <div
                                v-if="item.route_name"
                                class="text-muted-foreground"
                            >
                                {{ item.route_name }}
                            </div>
                        </td>
                        <td class="px-4 py-2.5">
                            <div class="flex flex-wrap gap-1">
                                <span
                                    v-for="key in item.changed_fields"
                                    :key="key"
                                    class="rounded bg-muted px-1.5 py-0.5 font-mono text-xs"
                                    >{{ key }}</span
                                >
                                <span
                                    v-if="item.changed_fields.length === 0"
                                    class="text-xs text-muted-foreground"
                                    >—</span
                                >
                            </div>
                        </td>
                        <td class="px-4 py-2.5 text-xs text-muted-foreground">
                            {{ item.causer?.name ?? 'System' }}
                        </td>
                        <td
                            class="px-4 py-2.5 text-xs whitespace-nowrap text-muted-foreground"
                        >
                            {{ timeAgo(item.created_at) }}
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>

        <!-- Pagination -->
        <div
            v-if="log.last_page > 1"
            class="flex items-center justify-between text-sm text-muted-foreground"
        >
            <span
                >{{ log.total }} entries · Page {{ log.current_page }} of
                {{ log.last_page }}</span
            >
            <div class="flex gap-2">
                <Button
                    variant="outline"
                    size="sm"
                    :disabled="!log.prev_page_url"
                    @click="router.visit(log.prev_page_url!)"
                    >Previous</Button
                >
                <Button
                    variant="outline"
                    size="sm"
                    :disabled="!log.next_page_url"
                    @click="router.visit(log.next_page_url!)"
                    >Next</Button
                >
            </div>
        </div>
    </AppLayout>
</template>
