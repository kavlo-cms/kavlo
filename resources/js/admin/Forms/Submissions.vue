<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { router } from '@inertiajs/vue3';
import { ArrowLeft, Download, Trash2 } from 'lucide-vue-next';
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
import admin from '@/routes/admin';
import type { BreadcrumbItem } from '@/types';

interface FormField {
    id?: number;
    label: string;
    key: string;
    sort_order: number;
}

interface Form {
    id: number;
    name: string;
    slug: string;
    fields: FormField[];
}

interface Submission {
    id: number;
    data: Record<string, unknown>;
    ip_address: string | null;
    created_at: string;
}

interface Paginator {
    data: Submission[];
    current_page: number;
    last_page: number;
    from: number | null;
    to: number | null;
    total: number;
    prev_page_url: string | null;
    next_page_url: string | null;
}

const props = defineProps<{
    form: Form;
    submissions: Paginator;
}>();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Forms', href: admin.forms.index.url() },
    { title: props.form.name, href: admin.forms.edit.url(props.form.id) },
    { title: 'Submissions', href: '#' },
];

const sortedFields = [...(props.form.fields ?? [])].sort(
    (a, b) => a.sort_order - b.sort_order,
);

function deleteSubmission(sub: Submission) {
    if (!confirm('Delete this submission? This cannot be undone.')) {
        return;
    }

    router.delete(
        admin.forms.submissions.destroy.url({
            form: props.form.id,
            submission: sub.id,
        }),
        { preserveScroll: true },
    );
}

function displaySubmissionValue(value: unknown): string {
    if (Array.isArray(value)) {
        return value.map((entry) => String(entry)).join(', ');
    }

    return value == null ? '—' : String(value);
}
</script>

<template>
    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex items-center justify-between">
            <div class="flex items-center gap-3">
                <Button variant="ghost" size="icon" as-child>
                    <Link :href="admin.forms.index.url()">
                        <ArrowLeft class="h-4 w-4" />
                    </Link>
                </Button>
                <div>
                    <h1 class="text-2xl font-semibold tracking-tight">
                        {{ form.name }} — Submissions
                    </h1>
                    <p class="text-sm text-muted-foreground">
                        {{ submissions.total }} total
                    </p>
                </div>
            </div>
            <Button variant="outline" as-child>
                <a :href="admin.forms.submissions.export.url(form.id)">
                    <Download class="mr-2 h-4 w-4" />
                    Export CSV
                </a>
            </Button>
        </div>

        <Table>
            <TableHeader>
                <TableRow>
                    <TableHead>Submitted</TableHead>
                    <TableHead v-for="field in sortedFields" :key="field.id">
                        {{ field.label }}
                    </TableHead>
                    <TableHead class="w-px" />
                </TableRow>
            </TableHeader>
            <TableBody>
                <TableEmpty
                    v-if="!submissions.data.length"
                    :colspan="sortedFields.length + 2"
                >
                    No submissions yet.
                </TableEmpty>
                <TableRow v-for="sub in submissions.data" :key="sub.id">
                    <TableCell
                        class="text-sm whitespace-nowrap text-muted-foreground"
                    >
                        {{ new Date(sub.created_at).toLocaleString() }}
                    </TableCell>
                    <TableCell
                        v-for="field in sortedFields"
                        :key="field.id"
                        class="max-w-xs truncate"
                    >
                        {{ displaySubmissionValue(sub.data[field.key]) }}
                    </TableCell>
                    <TableCell>
                        <Button
                            variant="ghost"
                            size="icon"
                            class="text-destructive hover:text-destructive"
                            @click="deleteSubmission(sub)"
                        >
                            <Trash2 class="h-4 w-4" />
                        </Button>
                    </TableCell>
                </TableRow>
            </TableBody>
        </Table>

        <!-- Pagination -->
        <div
            v-if="submissions.last_page > 1"
            class="flex items-center justify-between text-sm text-muted-foreground"
        >
            <span
                >Showing {{ submissions.from }}–{{ submissions.to }} of
                {{ submissions.total }}</span
            >
            <div class="flex gap-2">
                <Button
                    variant="outline"
                    size="sm"
                    :disabled="!submissions.prev_page_url"
                    @click="router.visit(submissions.prev_page_url!)"
                >
                    Previous
                </Button>
                <Button
                    variant="outline"
                    size="sm"
                    :disabled="!submissions.next_page_url"
                    @click="router.visit(submissions.next_page_url!)"
                >
                    Next
                </Button>
            </div>
        </div>
    </AppLayout>
</template>
