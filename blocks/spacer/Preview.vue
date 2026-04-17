<script setup lang="ts">
import { computed } from 'vue';

const props = defineProps<{ data: Record<string, unknown> }>();

const heightClass = computed(() =>
    ({ xs: 'h-4', sm: 'h-8', md: 'h-16', lg: 'h-32', xl: 'h-64' }[(props.data.size as string) ?? 'md'] ?? 'h-16'),
);
</script>

<template>
    <div class="relative w-full select-none">
        <div :class="['w-full', heightClass]" />
        <div class="pointer-events-none absolute inset-x-8 inset-y-0 flex items-center justify-center border-x border-dashed border-muted-foreground/20">
            <span class="rounded bg-muted px-2 py-0.5 text-xs text-muted-foreground">Spacer</span>
        </div>
    </div>
</template>
