<script setup lang="ts">
import { router } from '@inertiajs/vue3';
import { Link } from '@inertiajs/vue3';
import { ClipboardList, Pencil, Plus, Trash2 } from 'lucide-vue-next';
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
import {
    Tooltip,
    TooltipContent,
    TooltipProvider,
    TooltipTrigger,
} from '@/components/ui/tooltip';
import AppLayout from '@/layouts/AppLayout.vue';
import admin from '@/routes/admin';
import type { BreadcrumbItem } from '@/types';

interface Form {
    id: number;
    name: string;
    slug: string;
    fields_count: number;
    form_submissions_count: number;
    created_at: string;
}

defineProps<{ forms: Form[] }>();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Forms', href: admin.forms.index.url() },
];

function deleteForm(form: Form) {
    if (
        !confirm(`Delete "${form.name}"? All submissions will also be deleted.`)
    )
        return;
    router.delete(admin.forms.destroy.url(form.id));
}
</script>

<template>
    <AppLayout :breadcrumbs="breadcrumbs">
        <TooltipProvider :delay-duration="0">
            <div class="flex items-center justify-between">
                <h1 class="text-2xl font-semibold tracking-tight">Forms</h1>
                <Tooltip>
                    <TooltipTrigger as-child>
                        <Button as-child>
                            <Link :href="admin.forms.create.url()">
                                <Plus class="mr-2 h-4 w-4" />
                                New Form
                            </Link>
                        </Button>
                    </TooltipTrigger>
                    <TooltipContent
                        >Create a new form with blocks, actions, and submissions
                        tracking.</TooltipContent
                    >
                </Tooltip>
            </div>

            <Table>
                <TableHeader>
                    <TableRow>
                        <TableHead>Name</TableHead>
                        <TableHead>Slug</TableHead>
                        <TableHead class="text-center">Fields</TableHead>
                        <TableHead class="text-center">Submissions</TableHead>
                        <TableHead class="w-px" />
                    </TableRow>
                </TableHeader>
                <TableBody>
                    <TableEmpty v-if="!forms.length" :colspan="5">
                        No forms yet. Create one to get started.
                    </TableEmpty>
                    <TableRow v-for="form in forms" :key="form.id">
                        <TableCell class="font-medium">
                            <Link
                                :href="admin.forms.edit.url(form.id)"
                                class="rounded-sm transition-colors hover:text-primary hover:underline"
                            >
                                {{ form.name }}
                            </Link>
                        </TableCell>
                        <TableCell
                            class="font-mono text-xs text-muted-foreground"
                            >{{ form.slug }}</TableCell
                        >
                        <TableCell class="text-center">{{
                            form.fields_count
                        }}</TableCell>
                        <TableCell class="text-center">
                            <Tooltip>
                                <TooltipTrigger as-child>
                                    <Link
                                        :href="
                                            admin.forms.submissions.index.url(
                                                form.id,
                                            )
                                        "
                                        class="inline-flex items-center gap-1 rounded-md px-2 py-1 text-primary transition-colors hover:bg-accent hover:no-underline"
                                    >
                                        <ClipboardList class="h-3.5 w-3.5" />
                                        View submissions
                                        <span
                                            class="rounded-full bg-muted px-1.5 py-0.5 text-[10px] leading-none text-foreground"
                                        >
                                            {{ form.form_submissions_count }}
                                        </span>
                                    </Link>
                                </TooltipTrigger>
                                <TooltipContent
                                    >Open the submission list and export or
                                    delete entries.</TooltipContent
                                >
                            </Tooltip>
                        </TableCell>
                        <TableCell>
                            <div class="flex items-center justify-end gap-2">
                                <Tooltip>
                                    <TooltipTrigger as-child>
                                        <Button
                                            variant="ghost"
                                            size="icon"
                                            as-child
                                        >
                                            <Link
                                                :href="
                                                    admin.forms.edit.url(
                                                        form.id,
                                                    )
                                                "
                                            >
                                                <Pencil class="h-4 w-4" />
                                            </Link>
                                        </Button>
                                    </TooltipTrigger>
                                    <TooltipContent
                                        >Edit this form’s fields, layout, and
                                        submission action.</TooltipContent
                                    >
                                </Tooltip>
                                <Tooltip>
                                    <TooltipTrigger as-child>
                                        <Button
                                            variant="ghost"
                                            size="icon"
                                            as-child
                                        >
                                            <Link
                                                :href="
                                                    admin.forms.submissions.index.url(
                                                        form.id,
                                                    )
                                                "
                                            >
                                                <ClipboardList
                                                    class="h-4 w-4"
                                                />
                                            </Link>
                                        </Button>
                                    </TooltipTrigger>
                                    <TooltipContent
                                        >Go straight to this form’s
                                        submissions.</TooltipContent
                                    >
                                </Tooltip>
                                <Tooltip>
                                    <TooltipTrigger as-child>
                                        <Button
                                            variant="ghost"
                                            size="icon"
                                            class="text-destructive hover:text-destructive"
                                            @click="deleteForm(form)"
                                        >
                                            <Trash2 class="h-4 w-4" />
                                        </Button>
                                    </TooltipTrigger>
                                    <TooltipContent
                                        >Delete this form and its
                                        submissions.</TooltipContent
                                    >
                                </Tooltip>
                            </div>
                        </TableCell>
                    </TableRow>
                </TableBody>
            </Table>
        </TooltipProvider>
    </AppLayout>
</template>
