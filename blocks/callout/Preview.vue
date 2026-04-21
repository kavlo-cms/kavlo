<script setup lang="ts">
import { AlertCircle, AlertTriangle, CheckCircle, Info } from 'lucide-vue-next';
import { computed } from 'vue';
import InlineEdit from '@/admin/Pages/partials/InlineEdit.vue';

const props = defineProps<{ data: Record<string, unknown> }>();
const emit = defineEmits<{ 'update:data': [Record<string, unknown>] }>();

const configs = {
    info:    { icon: Info,          bg: 'bg-blue-50 dark:bg-blue-950/30',   border: 'border-blue-200 dark:border-blue-800',   icon_color: 'text-blue-500' },
    success: { icon: CheckCircle,   bg: 'bg-green-50 dark:bg-green-950/30', border: 'border-green-200 dark:border-green-800', icon_color: 'text-green-500' },
    warning: { icon: AlertTriangle, bg: 'bg-amber-50 dark:bg-amber-950/30', border: 'border-amber-200 dark:border-amber-800', icon_color: 'text-amber-500' },
    error:   { icon: AlertCircle,   bg: 'bg-red-50 dark:bg-red-950/30',     border: 'border-red-200 dark:border-red-800',     icon_color: 'text-red-500' },
} as const;

type CalloutType = keyof typeof configs;
const cfg = computed(() => configs[(props.data.type as CalloutType) ?? 'info'] ?? configs.info);
</script>

<template>
    <div class="py-4 px-6 max-w-3xl mx-auto w-full">
        <div :class="['flex gap-3 rounded-lg border p-4', cfg.bg, cfg.border]">
            <component :is="cfg.icon" :class="['mt-0.5 h-5 w-5 shrink-0', cfg.icon_color]" />
            <div class="min-w-0 flex-1">
                <InlineEdit
                    tag="p"
                    :model-value="(data.title as string) ?? ''"
                    placeholder="Callout title (optional)…"
                    class="block text-sm font-semibold text-foreground"
                    @update:model-value="emit('update:data', { ...data, title: $event })"
                />
                <InlineEdit
                    tag="p"
                    :model-value="(data.text as string) ?? ''"
                    placeholder="Enter message…"
                    class="mt-1 block min-h-[1.2em] text-sm text-foreground/80"
                    @update:model-value="emit('update:data', { ...data, text: $event })"
                />
            </div>
        </div>
    </div>
</template>
