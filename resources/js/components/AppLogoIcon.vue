<script setup lang="ts">
import type { HTMLAttributes } from 'vue';

defineOptions({
    inheritAttrs: false,
});

type Props = {
    className?: HTMLAttributes['class'];
};

defineProps<Props>();
</script>

<template>
    <img
        src="/kavlo.png"
        alt="Kavlo CMS"
        :class="className"
        class="rounded-md object-cover"
        v-bind="$attrs"
    />
</template>
