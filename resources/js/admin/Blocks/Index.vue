<script setup lang="ts">
import {
    AlertCircle,
    AlignLeft,
    ArrowUpDown,
    Heading1,
    Image,
    LayoutGrid,
    LayoutTemplate,
    List,
    Minus,
    MousePointer2,
    Quote,
    Sparkles,
    Square,
    type LucideIcon,
    Video,
} from 'lucide-vue-next';
import { Badge } from '@/components/ui/badge';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import AppLayout from '@/layouts/AppLayout.vue';
import admin from '@/routes/admin';
import type { BreadcrumbItem } from '@/types';

interface Block {
    type: string;
    label: string;
    description?: string;
    group?: string;
    icon?: string;
    source?: string;
}

const props = defineProps<{
    grouped: Record<string, Block[]>;
    activeTheme: string;
    totalBlocks: number;
}>();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Settings', href: admin.settings.index.url() },
    { title: 'Blocks', href: admin.blocks.index.url() },
];

const groupLabels: Record<string, string> = {
    text: 'Text',
    layout: 'Layout',
    media: 'Media',
    components: 'Components',
};

const iconMap: Record<string, LucideIcon> = {
    Heading1,
    AlignLeft,
    Quote,
    List,
    Square,
    LayoutGrid,
    Minus,
    ArrowUpDown,
    Image,
    Video,
    Sparkles,
    MousePointer2,
    AlertCircle,
    LayoutTemplate,
};

function resolveIcon(name?: string): LucideIcon {
    return name && iconMap[name] ? iconMap[name] : LayoutTemplate;
}

const sourceVariant: Record<string, 'default' | 'secondary' | 'outline'> = {
    core: 'secondary',
    theme: 'outline',
    plugin: 'default',
};
</script>

<template>
    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex items-center justify-between">
            <h1 class="text-2xl font-semibold tracking-tight">Blocks</h1>
            <p class="text-sm text-muted-foreground">
                {{ props.totalBlocks }} block{{
                    props.totalBlocks === 1 ? '' : 's'
                }}
                available
                <span
                    v-if="props.activeTheme"
                    class="ml-1 text-muted-foreground/60"
                    >&middot; {{ props.activeTheme }}</span
                >
            </p>
        </div>

        <div class="space-y-8">
            <section v-for="(blocks, group) in props.grouped" :key="group">
                <h2
                    class="mb-3 text-xs font-semibold tracking-widest text-muted-foreground uppercase"
                >
                    {{ groupLabels[group] ?? group }}
                </h2>
                <div
                    class="grid gap-3 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4"
                >
                    <Card
                        v-for="block in blocks"
                        :key="block.type"
                        class="group transition-colors hover:border-primary/40"
                    >
                        <CardHeader class="pb-2">
                            <div class="flex items-start gap-3">
                                <div
                                    class="flex h-9 w-9 shrink-0 items-center justify-center rounded-md bg-muted text-muted-foreground group-hover:bg-primary/10 group-hover:text-primary"
                                >
                                    <component
                                        :is="resolveIcon(block.icon)"
                                        class="h-4 w-4"
                                    />
                                </div>
                                <div class="min-w-0">
                                    <CardTitle class="text-sm">{{
                                        block.label
                                    }}</CardTitle>
                                    <p
                                        v-if="block.description"
                                        class="mt-0.5 line-clamp-2 text-xs text-muted-foreground"
                                    >
                                        {{ block.description }}
                                    </p>
                                </div>
                            </div>
                        </CardHeader>
                        <CardContent class="pb-3">
                            <div class="flex items-center justify-between">
                                <code
                                    class="rounded bg-muted px-1.5 py-0.5 text-xs text-muted-foreground"
                                    >{{ block.type }}</code
                                >
                                <Badge
                                    :variant="
                                        sourceVariant[block.source ?? 'core'] ??
                                        'secondary'
                                    "
                                    class="text-xs"
                                >
                                    {{ block.source ?? 'core' }}
                                </Badge>
                            </div>
                        </CardContent>
                    </Card>
                </div>
            </section>

            <div
                v-if="Object.keys(props.grouped).length === 0"
                class="flex flex-col items-center justify-center rounded-lg border border-dashed py-16 text-center"
            >
                <LayoutTemplate
                    class="mb-3 h-10 w-10 text-muted-foreground/40"
                />
                <p class="text-sm text-muted-foreground">No blocks found.</p>
            </div>
        </div>
    </AppLayout>
</template>
