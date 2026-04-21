<script setup lang="ts">
import { useForm } from '@inertiajs/vue3';
import { Link } from '@inertiajs/vue3';
import { ArrowLeft, Loader2 } from 'lucide-vue-next';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import AppLayout from '@/layouts/AppLayout.vue';
import type { BreadcrumbItem } from '@/types';

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Menus', href: '/admin/menus' },
    { title: 'New Menu', href: '/admin/menus/create' },
];

const form = useForm({ name: '', slug: '' });

function autoSlug() {
    if (!form.slug) {
        form.slug = form.name
            .toLowerCase()
            .replace(/[^a-z0-9]+/g, '-')
            .replace(/^-|-$/g, '');
    }
}

function submit() {
    form.post('/admin/menus');
}
</script>

<template>
    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex items-center gap-3">
            <Button variant="ghost" size="icon" class="h-8 w-8" as-child>
                <Link href="/admin/menus"><ArrowLeft class="h-4 w-4" /></Link>
            </Button>
            <h1 class="text-2xl font-semibold tracking-tight">New Menu</h1>
        </div>

        <form class="max-w-md space-y-4" @submit.prevent="submit">
            <div class="space-y-1.5">
                <Label for="name">Name</Label>
                <Input
                    id="name"
                    v-model="form.name"
                    placeholder="Main Navigation"
                    @blur="autoSlug"
                />
                <p v-if="form.errors.name" class="text-sm text-destructive">
                    {{ form.errors.name }}
                </p>
            </div>

            <div class="space-y-1.5">
                <Label for="slug">Slug</Label>
                <Input
                    id="slug"
                    v-model="form.slug"
                    placeholder="main-navigation"
                    class="font-mono"
                />
                <p v-if="form.errors.slug" class="text-sm text-destructive">
                    {{ form.errors.slug }}
                </p>
            </div>

            <Button type="submit" :disabled="form.processing">
                <Loader2
                    v-if="form.processing"
                    class="mr-2 h-4 w-4 animate-spin"
                />
                Create &amp; Edit
            </Button>
        </form>
    </AppLayout>
</template>
