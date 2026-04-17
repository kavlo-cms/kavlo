<script setup lang="ts">
import { useForm } from '@inertiajs/vue3';
import { Database, FileCode, Map, Settings, Trash2, Zap } from 'lucide-vue-next';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import AppLayout from '@/layouts/AppLayout.vue';
import admin from '@/routes/admin';
import type { BreadcrumbItem } from '@/types';

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Cache', href: admin.cache.index.url() },
];

const caches = [
    {
        type: 'application',
        icon: Database,
        title: 'Application Cache',
        description: 'Clears all cached data including settings, queries, and any manually cached values.',
    },
    {
        type: 'views',
        icon: FileCode,
        title: 'View Cache',
        description: 'Removes compiled Blade template files forcing them to be recompiled on next request.',
    },
    {
        type: 'routes',
        icon: Map,
        title: 'Route Cache',
        description: 'Clears the cached route manifest. Required after adding or modifying routes.',
    },
    {
        type: 'config',
        icon: Settings,
        title: 'Config Cache',
        description: 'Clears the cached configuration. Required after changing .env or config files.',
    },
];

function makeForm(type: string) {
    return useForm({ type });
}

const forms = Object.fromEntries(caches.map((c) => [c.type, makeForm(c.type)]));

function clearCache(type: string) {
    forms[type].post(admin.cache.clear.url());
}

const allForm = useForm({ type: 'all' });
function clearAll() {
    allForm.post(admin.cache.clear.url());
}
</script>

<template>
    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex items-center justify-between">
            <h1 class="text-2xl font-semibold tracking-tight">Cache Management</h1>
            <Button variant="destructive" size="sm" :disabled="allForm.processing" @click="clearAll">
                <Trash2 class="mr-2 h-3.5 w-3.5" />
                Clear All Caches
            </Button>
        </div>

        <div class="grid gap-4 sm:grid-cols-2">
            <Card v-for="cache in caches" :key="cache.type">
                <CardHeader class="pb-3">
                    <div class="flex items-center gap-3">
                        <div class="flex h-9 w-9 items-center justify-center rounded-lg bg-muted">
                            <component :is="cache.icon" class="h-4 w-4 text-muted-foreground" />
                        </div>
                        <div>
                            <CardTitle class="text-base">{{ cache.title }}</CardTitle>
                        </div>
                    </div>
                </CardHeader>
                <CardContent class="flex flex-col gap-3">
                    <CardDescription>{{ cache.description }}</CardDescription>
                    <Button
                        variant="outline" size="sm" class="self-start"
                        :disabled="forms[cache.type].processing"
                        @click="clearCache(cache.type)"
                    >
                        <Zap class="mr-2 h-3.5 w-3.5" />
                        Clear
                    </Button>
                </CardContent>
            </Card>
        </div>
    </AppLayout>
</template>
