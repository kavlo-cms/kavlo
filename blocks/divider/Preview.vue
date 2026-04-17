<script setup lang="ts">
import { computed } from 'vue';

const props = defineProps<{ data: Record<string, unknown> }>();

const spacingClass = computed(() =>
    ({ sm: 'py-4', md: 'py-8', lg: 'py-16' }[(props.data.spacing as string) ?? 'md'] ?? 'py-8'),
);
const style = computed(() => (props.data.style as string) ?? 'line');
</script>

<template>
    <div class="px-8" :class="spacingClass">
        <hr v-if="style === 'line'" class="border-border" />
        <div v-else-if="style === 'dots'" class="flex items-center justify-center gap-2">
            <span v-for="i in 3" :key="i" class="h-1.5 w-1.5 rounded-full bg-muted-foreground/40" />
        </div>
        <!-- style === 'none': renders just the padding space -->
    </div>
</template>
