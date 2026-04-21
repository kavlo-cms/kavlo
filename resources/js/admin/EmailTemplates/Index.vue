<script setup lang="ts">
import { router } from '@inertiajs/vue3';
import { Link } from '@inertiajs/vue3';
import { Mail, Pencil, Plus, Trash2 } from 'lucide-vue-next';
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

interface EmailTemplateRow {
    id: number;
    name: string;
    slug: string;
    context_key: string;
    context_label: string;
    subject: string;
    updated_at: string | null;
}

defineProps<{ templates: EmailTemplateRow[] }>();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Structure', href: '/admin/menus' },
    { title: 'Email Templates', href: '/admin/email-templates' },
];

function destroyTemplate(template: EmailTemplateRow) {
    if (!confirm(`Delete "${template.name}"?`)) {
        return;
    }

    router.delete(`/admin/email-templates/${template.id}`);
}

function formatDate(value: string | null) {
    return value ? new Date(value).toLocaleString() : '—';
}
</script>

<template>
    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex items-center justify-between">
            <div>
                <h1 class="text-2xl font-semibold tracking-tight">
                    Email Templates
                </h1>
                <p class="text-sm text-muted-foreground">
                    Builder-backed email layouts for test emails, form
                    notifications, and future plugin/theme mail flows.
                </p>
            </div>

            <Button as-child>
                <Link href="/admin/email-templates/create">
                    <Plus class="mr-2 h-4 w-4" />
                    New Template
                </Link>
            </Button>
        </div>

        <Table>
            <TableHeader>
                <TableRow>
                    <TableHead>Name</TableHead>
                    <TableHead>Usage</TableHead>
                    <TableHead>Subject</TableHead>
                    <TableHead>Updated</TableHead>
                    <TableHead class="w-px" />
                </TableRow>
            </TableHeader>
            <TableBody>
                <TableEmpty v-if="!templates.length" :colspan="5">
                    No email templates yet. Create one to start using
                    builder-backed emails.
                </TableEmpty>

                <TableRow v-for="template in templates" :key="template.id">
                    <TableCell class="font-medium">
                        <Link
                            :href="`/admin/email-templates/${template.id}/edit`"
                            class="rounded-sm transition-colors hover:text-primary hover:underline"
                        >
                            {{ template.name }}
                        </Link>
                        <p class="font-mono text-xs text-muted-foreground">
                            {{ template.slug }}
                        </p>
                    </TableCell>
                    <TableCell>
                        <span
                            class="inline-flex rounded-full bg-muted px-2 py-1 text-xs text-muted-foreground"
                        >
                            {{ template.context_label }}
                        </span>
                    </TableCell>
                    <TableCell class="max-w-[24rem] truncate">{{
                        template.subject
                    }}</TableCell>
                    <TableCell class="text-sm text-muted-foreground">{{
                        formatDate(template.updated_at)
                    }}</TableCell>
                    <TableCell>
                        <div class="flex items-center justify-end gap-2">
                            <Button variant="ghost" size="icon" as-child>
                                <Link
                                    :href="`/admin/email-templates/${template.id}/edit`"
                                >
                                    <Pencil class="h-4 w-4" />
                                </Link>
                            </Button>
                            <Button
                                variant="ghost"
                                size="icon"
                                class="text-destructive hover:text-destructive"
                                @click="destroyTemplate(template)"
                            >
                                <Trash2 class="h-4 w-4" />
                            </Button>
                        </div>
                    </TableCell>
                </TableRow>
            </TableBody>
        </Table>

        <div
            class="rounded-lg border bg-muted/20 p-4 text-sm text-muted-foreground"
        >
            <div class="flex items-start gap-3">
                <Mail class="mt-0.5 h-4 w-4 shrink-0" />
                <p>
                    Generic templates can be reused across multiple email flows.
                    Context-specific templates expose variables tailored to that
                    mail type.
                </p>
            </div>
        </div>
    </AppLayout>
</template>
