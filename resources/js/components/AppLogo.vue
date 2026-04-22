<script setup lang="ts">
import { usePage } from '@inertiajs/vue3';

const name = usePage().props.name as string;
</script>

<template>
    <img
        src="/kavlo.png"
        alt="Kavlo CMS"
        class="size-9 rounded-md object-cover"
    />
    <div class="ml-1 grid flex-1 text-left text-sm">
        <span class="mb-0.5 truncate leading-tight font-semibold">{{
            name
        }}</span>
    </div>
</template>
