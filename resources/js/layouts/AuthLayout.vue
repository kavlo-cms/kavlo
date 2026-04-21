<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import AppLogo from '@/components/AppLogo.vue';

defineProps<{
    title: string;
    description?: string;
}>();
</script>

<template>
    <div class="flex min-h-screen items-center justify-center bg-muted/30 px-4 py-10">
        <div class="w-full max-w-md space-y-6">
            <div class="flex justify-center">
                <Link href="/" class="inline-flex items-center">
                    <AppLogo />
                </Link>
            </div>

            <div class="rounded-xl border bg-background p-6 shadow-sm">
                <div class="mb-6 space-y-2 text-center">
                    <h1 class="text-2xl font-semibold tracking-tight">{{ title }}</h1>
                    <p v-if="description" class="text-sm text-muted-foreground">
                        {{ description }}
                    </p>
                </div>

                <slot />
            </div>
        </div>
    </div>
</template>
