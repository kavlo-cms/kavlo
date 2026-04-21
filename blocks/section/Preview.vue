<script setup lang="ts">
import BlockSlot from '@/admin/Pages/partials/BlockSlot.vue';
import { blockSchemas } from '@/admin/Pages/config/blockSchemas';
import type { Block } from '@/types/blocks';

defineProps<{ data: Record<string, unknown> }>();
const emit = defineEmits<{ 'update:data': [Record<string, unknown>] }>();
</script>

<template>
    <div
        class="w-full"
        :class="(data.padding as string) || 'py-8 px-6'"
        :style="data.background ? { background: data.background as string } : {}"
    >
        <BlockSlot
            :blocks="(data.children as Block[]) ?? []"
            :allowed-types="blockSchemas.section?.allowedChildren"
            @update:blocks="emit('update:data', { ...data, children: $event })"
        />
    </div>
</template>
