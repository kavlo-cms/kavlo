<script setup lang="ts">
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs';
import AppLayout from '@/layouts/AppLayout.vue';
import admin from '@/routes/admin';
import type { BreadcrumbItem } from '@/types';

interface Summary {
    channels: number;
    resources: number;
    routes: number;
}

interface QueryDefinition {
    key: string;
    description: string;
}

interface Channel {
    key: string;
    label: string;
    type: string;
    endpoint: string;
    path: string;
    ide_path?: string | null;
    ide_url?: string | null;
    description: string;
    visibility: string;
    queries: QueryDefinition[];
}

interface Resource {
    key: string;
    label: string;
    source: string;
    model: string;
    graphql_type: string;
    description: string;
    record_count: number;
    generated_routes: number;
    supports: string[];
    fields: string[];
}

interface RouteEntry {
    type: string;
    label: string;
    key: string;
    path: string | null;
    published: boolean;
    updated_at: string | null;
}

interface HookDefinition {
    hook: string;
    description: string;
}

const props = defineProps<{
    summary: Summary;
    channels: Channel[];
    resources: Resource[];
    routes: RouteEntry[];
    hooks: HookDefinition[];
    commands: string[];
}>();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'DataHub', href: admin.datahub.index.url() },
];

function relativeTime(value: string | null): string {
    if (!value) return '—';

    const diff = Math.floor((Date.now() - new Date(value).getTime()) / 1000);
    if (diff < 60) return `${diff}s ago`;
    if (diff < 3600) return `${Math.floor(diff / 60)}m ago`;
    if (diff < 86400) return `${Math.floor(diff / 3600)}h ago`;
    return `${Math.floor(diff / 86400)}d ago`;
}

function supportVariant(value: string): 'default' | 'secondary' | 'outline' {
    if (value === 'routes') return 'default';
    if (value === 'graphql') return 'secondary';
    return 'outline';
}
</script>

<template>
    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex items-start justify-between gap-4">
            <div class="space-y-1">
                <h1 class="text-2xl font-semibold tracking-tight">DataHub</h1>
                <p class="max-w-3xl text-sm text-muted-foreground">
                    Pimcore-style structure for delivery channels, routeable
                    resources, and the cached content manifest. Core models are
                    registered here first, and plugins/themes can extend the
                    registry later.
                </p>
            </div>
            <div class="flex shrink-0 gap-2">
                <Button variant="outline" as-child>
                    <a
                        href="/graphiql"
                        target="_blank"
                        rel="noopener noreferrer"
                        >Open GraphiQL</a
                    >
                </Button>
                <Button variant="outline" as-child>
                    <a href="/graphql" target="_blank" rel="noopener noreferrer"
                        >Open GraphQL</a
                    >
                </Button>
            </div>
        </div>

        <div class="grid gap-4 md:grid-cols-3">
            <Card>
                <CardHeader class="pb-2">
                    <CardTitle class="text-sm font-medium text-muted-foreground"
                        >Channels</CardTitle
                    >
                </CardHeader>
                <CardContent>
                    <p class="text-3xl font-bold">
                        {{ props.summary.channels }}
                    </p>
                    <p class="mt-1 text-xs text-muted-foreground">
                        Delivery endpoints registered in the DataHub.
                    </p>
                </CardContent>
            </Card>
            <Card>
                <CardHeader class="pb-2">
                    <CardTitle class="text-sm font-medium text-muted-foreground"
                        >Resources</CardTitle
                    >
                </CardHeader>
                <CardContent>
                    <p class="text-3xl font-bold">
                        {{ props.summary.resources }}
                    </p>
                    <p class="mt-1 text-xs text-muted-foreground">
                        Core models exposed through the registry.
                    </p>
                </CardContent>
            </Card>
            <Card>
                <CardHeader class="pb-2">
                    <CardTitle class="text-sm font-medium text-muted-foreground"
                        >Generated Routes</CardTitle
                    >
                </CardHeader>
                <CardContent>
                    <p class="text-3xl font-bold">{{ props.summary.routes }}</p>
                    <p class="mt-1 text-xs text-muted-foreground">
                        Entries currently present in the cached route manifest.
                    </p>
                </CardContent>
            </Card>
        </div>

        <Tabs default-value="overview">
            <TabsList>
                <TabsTrigger value="overview">Overview</TabsTrigger>
                <TabsTrigger value="resources">Resources</TabsTrigger>
                <TabsTrigger value="routes">Routes</TabsTrigger>
            </TabsList>

            <TabsContent value="overview" class="space-y-6 pt-4">
                <div class="grid gap-6 xl:grid-cols-[1.6fr_1fr]">
                    <div class="space-y-6">
                        <Card
                            v-for="channel in props.channels"
                            :key="channel.key"
                        >
                            <CardHeader>
                                <div class="flex items-center gap-2">
                                    <CardTitle>{{ channel.label }}</CardTitle>
                                    <Badge variant="secondary">{{
                                        channel.type
                                    }}</Badge>
                                </div>
                                <CardDescription>{{
                                    channel.description
                                }}</CardDescription>
                            </CardHeader>
                            <CardContent class="space-y-4">
                                <div class="grid gap-3 md:grid-cols-2">
                                    <div
                                        class="rounded-lg border bg-muted/20 p-3"
                                    >
                                        <p
                                            class="text-xs font-medium tracking-wide text-muted-foreground uppercase"
                                        >
                                            Endpoint
                                        </p>
                                        <p class="mt-1 font-mono text-sm">
                                            {{ channel.path }}
                                        </p>
                                        <p
                                            class="mt-1 text-xs text-muted-foreground"
                                        >
                                            {{ channel.endpoint }}
                                        </p>
                                    </div>
                                    <div
                                        class="rounded-lg border bg-muted/20 p-3"
                                    >
                                        <p
                                            class="text-xs font-medium tracking-wide text-muted-foreground uppercase"
                                        >
                                            Visibility
                                        </p>
                                        <p class="mt-1 text-sm">
                                            {{ channel.visibility }}
                                        </p>
                                    </div>
                                </div>

                                <div
                                    v-if="channel.ide_path"
                                    class="rounded-lg border bg-muted/20 p-3"
                                >
                                    <p
                                        class="text-xs font-medium tracking-wide text-muted-foreground uppercase"
                                    >
                                        IDE
                                    </p>
                                    <div
                                        class="mt-1 flex items-center justify-between gap-3"
                                    >
                                        <div class="min-w-0">
                                            <p class="font-mono text-sm">
                                                {{ channel.ide_path }}
                                            </p>
                                            <p
                                                class="mt-1 truncate text-xs text-muted-foreground"
                                            >
                                                {{ channel.ide_url }}
                                            </p>
                                        </div>
                                        <Button
                                            size="sm"
                                            variant="outline"
                                            as-child
                                        >
                                            <a
                                                :href="channel.ide_path"
                                                target="_blank"
                                                rel="noopener noreferrer"
                                                >Open</a
                                            >
                                        </Button>
                                    </div>
                                </div>

                                <div>
                                    <p
                                        class="mb-2 text-xs font-medium tracking-wide text-muted-foreground uppercase"
                                    >
                                        Available queries
                                    </p>
                                    <div class="flex flex-wrap gap-2">
                                        <Badge
                                            v-for="query in channel.queries"
                                            :key="query.key"
                                            variant="outline"
                                            class="font-mono"
                                        >
                                            {{ query.key }}
                                        </Badge>
                                    </div>
                                </div>

                                <Table>
                                    <TableHeader>
                                        <TableRow>
                                            <TableHead>Query</TableHead>
                                            <TableHead>Description</TableHead>
                                        </TableRow>
                                    </TableHeader>
                                    <TableBody>
                                        <TableRow
                                            v-for="query in channel.queries"
                                            :key="query.key"
                                        >
                                            <TableCell
                                                class="font-mono text-xs"
                                                >{{ query.key }}</TableCell
                                            >
                                            <TableCell>{{
                                                query.description
                                            }}</TableCell>
                                        </TableRow>
                                    </TableBody>
                                </Table>
                            </CardContent>
                        </Card>
                    </div>

                    <div class="space-y-6">
                        <Card>
                            <CardHeader>
                                <CardTitle>Commands</CardTitle>
                                <CardDescription
                                    >Useful operational commands for the
                                    DataHub.</CardDescription
                                >
                            </CardHeader>
                            <CardContent class="space-y-2">
                                <div
                                    v-for="command in props.commands"
                                    :key="command"
                                    class="rounded-md border bg-muted/20 px-3 py-2 font-mono text-xs"
                                >
                                    {{ command }}
                                </div>
                            </CardContent>
                        </Card>

                        <Card>
                            <CardHeader>
                                <CardTitle>Extension Hooks</CardTitle>
                                <CardDescription
                                    >Entry points for plugins and themes to add
                                    resources or channels.</CardDescription
                                >
                            </CardHeader>
                            <CardContent class="space-y-3">
                                <div
                                    v-for="hook in props.hooks"
                                    :key="hook.hook"
                                    class="rounded-lg border bg-muted/20 p-3"
                                >
                                    <p class="font-mono text-xs">
                                        {{ hook.hook }}
                                    </p>
                                    <p
                                        class="mt-1 text-sm text-muted-foreground"
                                    >
                                        {{ hook.description }}
                                    </p>
                                </div>
                            </CardContent>
                        </Card>

                        <Card>
                            <CardHeader>
                                <CardTitle>Next layer</CardTitle>
                                <CardDescription
                                    >What this structure is ready for
                                    next.</CardDescription
                                >
                            </CardHeader>
                            <CardContent
                                class="space-y-2 text-sm text-muted-foreground"
                            >
                                <p>
                                    Use the resource registry to add custom
                                    content types, product-like objects, or
                                    plugin models.
                                </p>
                                <p>
                                    Use the channel registry to split public,
                                    preview, or partner-facing GraphQL schemas
                                    later.
                                </p>
                            </CardContent>
                        </Card>
                    </div>
                </div>
            </TabsContent>

            <TabsContent value="resources" class="space-y-4 pt-4">
                <Card>
                    <CardHeader>
                        <CardTitle>Registered resources</CardTitle>
                        <CardDescription
                            >The first DataHub model registry, similar to
                            Pimcore object exposure.</CardDescription
                        >
                    </CardHeader>
                    <CardContent>
                        <Table>
                            <TableHeader>
                                <TableRow>
                                    <TableHead>Resource</TableHead>
                                    <TableHead>Model</TableHead>
                                    <TableHead>GraphQL</TableHead>
                                    <TableHead class="text-center"
                                        >Records</TableHead
                                    >
                                    <TableHead class="text-center"
                                        >Routes</TableHead
                                    >
                                    <TableHead>Supports</TableHead>
                                </TableRow>
                            </TableHeader>
                            <TableBody>
                                <TableRow
                                    v-for="resource in props.resources"
                                    :key="resource.key"
                                >
                                    <TableCell class="align-top">
                                        <div class="space-y-1">
                                            <div
                                                class="flex items-center gap-2"
                                            >
                                                <span class="font-medium">{{
                                                    resource.label
                                                }}</span>
                                                <Badge variant="outline">{{
                                                    resource.source
                                                }}</Badge>
                                            </div>
                                            <p
                                                class="text-sm text-muted-foreground"
                                            >
                                                {{ resource.description }}
                                            </p>
                                            <div
                                                class="flex flex-wrap gap-1 pt-1"
                                            >
                                                <Badge
                                                    v-for="field in resource.fields"
                                                    :key="field"
                                                    variant="secondary"
                                                    class="font-mono text-[10px]"
                                                >
                                                    {{ field }}
                                                </Badge>
                                            </div>
                                        </div>
                                    </TableCell>
                                    <TableCell class="font-mono text-xs">{{
                                        resource.model
                                    }}</TableCell>
                                    <TableCell class="font-mono text-xs">{{
                                        resource.graphql_type
                                    }}</TableCell>
                                    <TableCell class="text-center">{{
                                        resource.record_count
                                    }}</TableCell>
                                    <TableCell class="text-center">{{
                                        resource.generated_routes
                                    }}</TableCell>
                                    <TableCell>
                                        <div class="flex flex-wrap gap-1">
                                            <Badge
                                                v-for="support in resource.supports"
                                                :key="support"
                                                :variant="
                                                    supportVariant(support)
                                                "
                                                class="capitalize"
                                            >
                                                {{ support }}
                                            </Badge>
                                        </div>
                                    </TableCell>
                                </TableRow>
                            </TableBody>
                        </Table>
                    </CardContent>
                </Card>
            </TabsContent>

            <TabsContent value="routes" class="space-y-4 pt-4">
                <Card>
                    <CardHeader>
                        <CardTitle>Cached route manifest</CardTitle>
                        <CardDescription>
                            Generated public paths and registry keys currently
                            exposed to the CMS and GraphQL layer.
                        </CardDescription>
                    </CardHeader>
                    <CardContent>
                        <Table>
                            <TableHeader>
                                <TableRow>
                                    <TableHead>Type</TableHead>
                                    <TableHead>Label</TableHead>
                                    <TableHead>Key</TableHead>
                                    <TableHead>Path</TableHead>
                                    <TableHead>Status</TableHead>
                                    <TableHead>Updated</TableHead>
                                </TableRow>
                            </TableHeader>
                            <TableBody>
                                <TableRow
                                    v-for="route in props.routes"
                                    :key="`${route.type}:${route.key}:${route.path ?? 'none'}`"
                                >
                                    <TableCell>
                                        <Badge
                                            variant="outline"
                                            class="capitalize"
                                            >{{ route.type }}</Badge
                                        >
                                    </TableCell>
                                    <TableCell class="font-medium">{{
                                        route.label
                                    }}</TableCell>
                                    <TableCell class="font-mono text-xs">{{
                                        route.key
                                    }}</TableCell>
                                    <TableCell class="font-mono text-xs">{{
                                        route.path ?? '—'
                                    }}</TableCell>
                                    <TableCell>
                                        <Badge
                                            :variant="
                                                route.published
                                                    ? 'default'
                                                    : 'secondary'
                                            "
                                        >
                                            {{
                                                route.published
                                                    ? 'Published'
                                                    : 'Preview only'
                                            }}
                                        </Badge>
                                    </TableCell>
                                    <TableCell
                                        class="text-sm text-muted-foreground"
                                        >{{
                                            relativeTime(route.updated_at)
                                        }}</TableCell
                                    >
                                </TableRow>
                            </TableBody>
                        </Table>
                    </CardContent>
                </Card>
            </TabsContent>
        </Tabs>
    </AppLayout>
</template>
