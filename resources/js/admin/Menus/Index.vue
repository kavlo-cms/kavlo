<script setup lang="ts">
import { router } from '@inertiajs/vue3';
import { Link } from '@inertiajs/vue3';
import { LayoutList, Pencil, Plus, Trash2 } from 'lucide-vue-next';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Table,
    TableBody,
    TableCell,
    TableEmpty,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import AppLayout from '@/layouts/AppLayout.vue';
import type { BreadcrumbItem } from '@/types';

interface Menu {
    id: number;
    name: string;
    slug: string;
    items_count: number;
    created_at: string;
}

defineProps<{ menus: Menu[] }>();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Menus', href: '/admin/menus' },
];

function deleteMenu(menu: Menu) {
    if (!confirm(`Delete "${menu.name}"? This cannot be undone.`)) return;
    router.delete(`/admin/menus/${menu.id}`);
}
</script>

<template>
    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex items-center justify-between">
            <h1 class="text-2xl font-semibold tracking-tight">Menus</h1>
            <Button as-child>
                <Link href="/admin/menus/create">
                    <Plus class="mr-2 h-4 w-4" />
                    New Menu
                </Link>
            </Button>
        </div>

        <div class="rounded-lg border">
            <Table>
                <TableHeader>
                    <TableRow>
                        <TableHead>Name</TableHead>
                        <TableHead>Slug</TableHead>
                        <TableHead>Items</TableHead>
                        <TableHead class="w-0" />
                    </TableRow>
                </TableHeader>
                <TableBody>
                    <TableEmpty v-if="menus.length === 0" :colspan="4">
                        <LayoutList
                            class="mx-auto mb-2 h-8 w-8 text-muted-foreground/40"
                        />
                        No menus yet. Create one to get started.
                    </TableEmpty>
                    <TableRow v-for="menu in menus" :key="menu.id">
                        <TableCell class="font-medium">
                            <Link
                                :href="`/admin/menus/${menu.id}/edit`"
                                class="rounded-sm transition-colors hover:text-primary hover:underline"
                            >
                                {{ menu.name }}
                            </Link>
                        </TableCell>
                        <TableCell
                            class="font-mono text-sm text-muted-foreground"
                            >{{ menu.slug }}</TableCell
                        >
                        <TableCell>
                            <Badge variant="secondary">{{
                                menu.items_count
                            }}</Badge>
                        </TableCell>
                        <TableCell>
                            <div class="flex items-center justify-end gap-1">
                                <Button
                                    variant="ghost"
                                    size="icon"
                                    class="h-8 w-8"
                                    as-child
                                    title="Edit"
                                >
                                    <Link
                                        :href="`/admin/menus/${menu.id}/edit`"
                                    >
                                        <Pencil class="h-4 w-4" />
                                    </Link>
                                </Button>
                                <Button
                                    variant="ghost"
                                    size="icon"
                                    class="h-8 w-8 text-muted-foreground hover:text-destructive"
                                    title="Delete"
                                    @click="deleteMenu(menu)"
                                >
                                    <Trash2 class="h-4 w-4" />
                                </Button>
                            </div>
                        </TableCell>
                    </TableRow>
                </TableBody>
            </Table>
        </div>
    </AppLayout>
</template>
