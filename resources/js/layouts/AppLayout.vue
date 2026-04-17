<script setup lang="ts">
import { usePage } from '@inertiajs/vue3';
import AppSidebar from '@/components/AppSidebar.vue';
import Breadcrumbs from '@/components/Breadcrumbs.vue';
import { SidebarInset, SidebarProvider, SidebarTrigger } from '@/components/ui/sidebar';
import { Separator } from '@/components/ui/separator';
import { Toaster } from '@/components/ui/sonner';
import type { BreadcrumbItem } from '@/types';

defineProps<{
    breadcrumbs?: BreadcrumbItem[];
}>();

const isOpen = usePage().props.sidebarOpen;
</script>

<template>
    <SidebarProvider :default-open="isOpen">
        <AppSidebar />
        <SidebarInset>
            <header class="flex h-12 shrink-0 items-center gap-2 border-b px-4 transition-[width,height] ease-linear group-has-[[data-collapsible=icon]]/sidebar-wrapper:h-12">
                <SidebarTrigger class="-ml-1" />
                <Separator orientation="vertical" class="mr-2 h-4" />
                <Breadcrumbs v-if="breadcrumbs?.length" :breadcrumbs="breadcrumbs" />
            </header>
            <main class="flex flex-1 flex-col gap-4 p-4">
                <slot />
            </main>
        </SidebarInset>
        <Toaster />
    </SidebarProvider>
</template>
