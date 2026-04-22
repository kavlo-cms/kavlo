<script setup lang="ts">
import { usePage } from '@inertiajs/vue3';

const name = usePage().props.name as string;
</script>

<template>
    <div class="flex min-w-0 items-center gap-3">
        <img
            src="/kavlo.png"
            alt="Kavlo CMS"
            class="size-9 rounded-md object-cover"
        />
        <div class="min-w-0 flex-1 text-left text-sm">
            <span class="block truncate leading-none font-semibold">{{
                name
            }}</span>
        </div>
    </div>
</template>
