<script setup lang="ts">
</script>

<template>
    <div class="flex h-screen flex-col overflow-hidden bg-background">
        <div class="flex h-12 shrink-0 items-center border-b bg-background px-2">
            <slot name="header" />
        </div>
        <div class="flex min-h-0 flex-1 overflow-hidden">
            <slot />
        </div>
    </div>
</template>
