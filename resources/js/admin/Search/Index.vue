<script setup lang="ts">
import { Search as SearchIcon } from 'lucide-vue-next';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import AppLayout from '@/layouts/AppLayout.vue';
import type { BreadcrumbItem } from '@/types';

interface SearchItem {
    id: number;
    title: string;
    href: string;
    meta: string;
    excerpt: string;
    status?: string;
}

interface SearchResults {
    pages: SearchItem[];
    forms: SearchItem[];
    menus: SearchItem[];
    emailTemplates: SearchItem[];
    redirects: SearchItem[];
}

defineProps<{
    query: string;
    results: SearchResults;
}>();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Search', href: '/admin/search' },
];

const groups: {
    key: keyof SearchResults;
    title: string;
    description: string;
}[] = [
    { key: 'pages', title: 'Pages', description: 'Published and draft pages' },
    {
        key: 'forms',
        title: 'Forms',
        description: 'Forms and submission endpoints',
    },
    { key: 'menus', title: 'Menus', description: 'Navigation structures' },
    {
        key: 'emailTemplates',
        title: 'Email Templates',
        description: 'Transactional email content',
    },
    { key: 'redirects', title: 'Redirects', description: 'URL rewrite rules' },
];
</script>

<template>
    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex items-center justify-between gap-4">
            <div>
                <h1 class="text-2xl font-semibold tracking-tight">Search</h1>
                <p class="text-sm text-muted-foreground">
                    Find pages, forms, menus, email templates, and redirects
                    across the CMS.
                </p>
            </div>
        </div>

        <Card>
            <CardHeader>
                <CardTitle class="flex items-center gap-2">
                    <SearchIcon class="h-4 w-4" />
                    Global content search
                </CardTitle>
                <CardDescription
                    >Search by title, slug, URL, metadata, or content
                    text.</CardDescription
                >
            </CardHeader>
            <CardContent>
                <form action="/admin/search" method="get">
                    <Input
                        name="q"
                        :model-value="query"
                        placeholder="Search pages, forms, menus, redirects..."
                    />
                </form>
            </CardContent>
        </Card>

        <div
            v-if="query.length < 2"
            class="rounded-lg border border-dashed p-6 text-sm text-muted-foreground"
        >
            Enter at least two characters to search.
        </div>

        <div v-else class="grid gap-6">
            <Card v-for="group in groups" :key="group.key">
                <CardHeader>
                    <CardTitle>{{ group.title }}</CardTitle>
                    <CardDescription>{{ group.description }}</CardDescription>
                </CardHeader>
                <CardContent>
                    <div
                        v-if="results[group.key].length === 0"
                        class="text-sm text-muted-foreground"
                    >
                        No {{ group.title.toLowerCase() }} matched “{{
                            query
                        }}”.
                    </div>

                    <div v-else class="space-y-3">
                        <a
                            v-for="item in results[group.key]"
                            :key="`${group.key}-${item.id}`"
                            :href="item.href"
                            class="block rounded-lg border p-4 transition-colors hover:bg-accent/40"
                        >
                            <div class="flex items-start justify-between gap-3">
                                <div class="min-w-0">
                                    <p class="truncate font-medium">
                                        {{ item.title }}
                                    </p>
                                    <p
                                        class="truncate font-mono text-xs text-muted-foreground"
                                    >
                                        {{ item.meta }}
                                    </p>
                                </div>
                                <span
                                    v-if="item.status"
                                    class="rounded-full bg-muted px-2 py-0.5 text-xs text-muted-foreground"
                                >
                                    {{ item.status }}
                                </span>
                            </div>
                            <p class="mt-2 text-sm text-muted-foreground">
                                {{ item.excerpt }}
                            </p>
                        </a>
                    </div>
                </CardContent>
            </Card>
        </div>
    </AppLayout>
</template>
