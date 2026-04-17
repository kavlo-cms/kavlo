<script setup lang="ts">
import { router } from '@inertiajs/vue3';
import { Plug } from 'lucide-vue-next';
import { Badge } from '@/components/ui/badge';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Switch } from '@/components/ui/switch';
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
}

const props = defineProps<{ plugins: Plugin[] }>();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Settings', href: admin.settings.index.url() },
    { title: 'Plugins', href: admin.plugins.index.url() },
];

function toggle(plugin: Plugin) {
    router.post(admin.plugins.toggle.url({ plugin: plugin.id }), {}, { preserveScroll: true });
}
</script>

<template>
    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex items-center justify-between">
            <h1 class="text-2xl font-semibold tracking-tight">Plugins</h1>
            <p class="text-sm text-muted-foreground">{{ props.plugins.length }} plugin{{ props.plugins.length === 1 ? '' : 's' }} found</p>
        </div>

        <!-- Empty state -->
        <div v-if="props.plugins.length === 0" class="flex flex-col items-center justify-center rounded-lg border border-dashed py-16 text-center">
            <Plug class="mb-3 h-10 w-10 text-muted-foreground/40" />
            <p class="text-sm text-muted-foreground">No plugins found.</p>
            <p class="mt-1 text-xs text-muted-foreground">
                Add a plugin folder with a <code class="rounded bg-muted px-1">plugin.json</code> under <code class="rounded bg-muted px-1">plugins/</code>.
            </p>
        </div>

        <!-- Plugin list -->
        <div v-else class="space-y-3">
            <Card v-for="plugin in props.plugins" :key="plugin.id">
                <CardHeader class="pb-2">
                    <div class="flex items-start justify-between gap-4">
                        <div class="min-w-0 flex-1">
                            <div class="flex flex-wrap items-center gap-2">
                                <CardTitle class="text-base">{{ plugin.name }}</CardTitle>
                                <Badge v-if="plugin.version" variant="outline" class="text-xs font-normal">
                                    v{{ plugin.version }}
                                </Badge>
                                <Badge :variant="plugin.is_enabled ? 'default' : 'secondary'" class="text-xs">
                                    {{ plugin.is_enabled ? 'Active' : 'Inactive' }}
                                </Badge>
                            </div>
                            <CardDescription v-if="plugin.description" class="mt-1">
                                {{ plugin.description }}
                            </CardDescription>
                        </div>
                        <Switch
                            :checked="plugin.is_enabled"
                            :aria-label="`Toggle ${plugin.name}`"
                            @update:checked="toggle(plugin)"
                        />
                    </div>
                </CardHeader>
                <CardContent v-if="plugin.author" class="pb-3 text-xs text-muted-foreground">
                    By {{ plugin.author }} &middot; <code class="rounded bg-muted px-1">{{ plugin.slug }}</code>
                </CardContent>
            </Card>
        </div>

        <p class="text-xs text-muted-foreground">
            Enabled plugins load on every request. Changes take effect immediately on the next page load.
        </p>
    </AppLayout>
</template>
