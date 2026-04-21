<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import AppLayout from '@/layouts/AppLayout.vue';
import { Button } from '@/components/ui/button';
import admin from '@/routes/admin';
import type { BreadcrumbItem } from '@/types';
import PageForm from './partials/PageForm.vue';

interface PageType {
    type: string;
    label: string;
    view: string;
}

defineProps<{ pages?: unknown[]; pageTypes?: PageType[] }>();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Pages', href: admin.pages.index.url() },
    { title: 'New Page', href: admin.pages.create.url() },
];
</script>

<template>
    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex items-center justify-between">
            <h1 class="text-2xl font-semibold tracking-tight">New Page</h1>
            <Button variant="outline" as-child>
                <Link :href="admin.pages.index.url()">Cancel</Link>
            </Button>
        </div>

        <PageForm :page-types="pageTypes" />
    </AppLayout>
</template>
