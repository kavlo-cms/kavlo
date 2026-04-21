<script setup lang="ts">
import { router } from '@inertiajs/vue3';
import { CheckCircle2, Paintbrush } from 'lucide-vue-next';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardFooter,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import AppLayout from '@/layouts/AppLayout.vue';
import admin from '@/routes/admin';
import type { BreadcrumbItem } from '@/types';

interface Theme {
    id: number;
    name: string;
    slug: string;
    version: string | null;
    is_active: boolean;
    description: string | null;
    author: string | null;
    preview: string | null;
}

const props = defineProps<{ themes: Theme[] }>();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Settings', href: admin.settings.index.url() },
    { title: 'Themes', href: admin.themes.index.url() },
];

const previewColors: Record<string, { bg: string; accent: string }> = {
    'midnight-blue': { bg: '#0f172a', accent: '#38bdf8' },
};

function getPreviewStyle(theme: Theme) {
    const colors = previewColors[theme.slug] ?? {
        bg: '#1e293b',
        accent: '#6366f1',
    };
    return {
        background: `linear-gradient(135deg, ${colors.bg} 60%, ${colors.accent}33 100%)`,
        borderBottom: `3px solid ${colors.accent}`,
    };
}

function activate(theme: Theme) {
    router.post(
        admin.themes.activate.url({ theme: theme.id }),
        {},
        { preserveScroll: true },
    );
}
</script>

<template>
    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex items-center justify-between">
            <h1 class="text-2xl font-semibold tracking-tight">Themes</h1>
        </div>

        <div
            v-if="props.themes.length === 0"
            class="flex flex-col items-center justify-center rounded-lg border border-dashed py-16 text-center"
        >
            <Paintbrush class="mb-3 h-10 w-10 text-muted-foreground/40" />
            <p class="text-sm text-muted-foreground">No themes found.</p>
            <p class="mt-1 text-xs text-muted-foreground">
                Add a theme folder under
                <code class="rounded bg-muted px-1">resources/themes/</code>.
            </p>
        </div>

        <div v-else class="grid gap-6 sm:grid-cols-2 lg:grid-cols-3">
            <Card
                v-for="theme in props.themes"
                :key="theme.id"
                :class="theme.is_active ? 'ring-2 ring-primary' : ''"
                class="overflow-hidden"
            >
                <!-- Visual preview swatch -->
                <div class="h-32 w-full" :style="getPreviewStyle(theme)">
                    <div
                        class="flex h-full flex-col items-start justify-end p-4"
                    >
                        <span
                            class="rounded px-2 py-0.5 text-xs font-semibold"
                            :style="{
                                background:
                                    previewColors[theme.slug]?.accent ??
                                    '#6366f1',
                                color: '#fff',
                            }"
                        >
                            {{ theme.name }}
                        </span>
                    </div>
                </div>

                <CardHeader class="pb-2">
                    <div class="flex items-start justify-between gap-2">
                        <CardTitle class="text-base">{{
                            theme.name
                        }}</CardTitle>
                        <Badge
                            v-if="theme.is_active"
                            variant="default"
                            class="shrink-0"
                        >
                            <CheckCircle2 class="mr-1 h-3 w-3" />
                            Active
                        </Badge>
                    </div>
                    <CardDescription v-if="theme.description">{{
                        theme.description
                    }}</CardDescription>
                </CardHeader>

                <CardContent class="pb-2 text-xs text-muted-foreground">
                    <span v-if="theme.version">v{{ theme.version }}</span>
                    <span v-if="theme.version && theme.author"> &middot; </span>
                    <span v-if="theme.author">by {{ theme.author }}</span>
                    <span v-if="!theme.version && !theme.author" class="italic"
                        >No metadata</span
                    >
                </CardContent>

                <CardFooter>
                    <Button
                        v-if="!theme.is_active"
                        variant="outline"
                        size="sm"
                        class="w-full"
                        @click="activate(theme)"
                    >
                        Activate
                    </Button>
                    <p
                        v-else
                        class="w-full text-center text-xs text-muted-foreground"
                    >
                        Currently active
                    </p>
                </CardFooter>
            </Card>
        </div>
    </AppLayout>
</template>
