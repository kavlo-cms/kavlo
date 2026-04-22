<script setup lang="ts">
import { computed } from 'vue';
import ColorPicker from '@/components/ColorPicker.vue';
import { Input } from '@/components/ui/input';
import {
    gradientBackgroundStyle,
    normalizeGradientConfig,
    type GradientConfig,
} from '@/lib/blockStyles';
import type { BlockPreset } from '../schema';

const props = withDefaults(
    defineProps<{
        modelValue?: unknown;
        defaultValue?: unknown;
        presets?: BlockPreset[];
    }>(),
    {
        modelValue: undefined,
        defaultValue: undefined,
        presets: () => [],
    },
);

const emit = defineEmits<{
    'update:modelValue': [value: GradientConfig | null];
}>();

const fallbackGradient: GradientConfig = {
    start: '#2563eb',
    end: '#7c3aed',
    angle: 135,
};

const resolvedValue = computed(
    () =>
        normalizeGradientConfig(props.modelValue) ??
        normalizeGradientConfig(props.defaultValue) ??
        fallbackGradient,
);

const previewStyle = computed(
    () =>
        gradientBackgroundStyle(props.modelValue, props.defaultValue as GradientConfig) ??
        gradientBackgroundStyle(resolvedValue.value),
);

const gradientPresets = computed(() =>
    (props.presets ?? []).filter(
        (preset): preset is { label: string; value: GradientConfig } =>
            normalizeGradientConfig(preset.value) !== undefined,
    ),
);

function updateGradient(patch: Partial<GradientConfig>) {
    emit('update:modelValue', { ...resolvedValue.value, ...patch });
}

function applyPreset(preset: GradientConfig) {
    emit('update:modelValue', preset);
}

function clearGradient() {
    emit('update:modelValue', null);
}
</script>

<template>
    <div class="rounded-lg border bg-muted/20 p-3">
        <div class="flex items-center gap-3">
            <div class="h-12 flex-1 rounded-md border" :style="previewStyle" />
            <button
                type="button"
                class="rounded-md border px-2.5 py-2 text-xs text-muted-foreground transition-colors hover:bg-accent hover:text-foreground"
                @click="clearGradient"
            >
                None
            </button>
        </div>

        <div v-if="gradientPresets.length" class="mt-3 space-y-1.5">
            <div class="text-xs text-muted-foreground">Presets</div>
            <div class="flex flex-wrap gap-2">
                <button
                    v-for="preset in gradientPresets"
                    :key="preset.label"
                    type="button"
                    class="flex items-center gap-2 rounded-full border bg-background px-2 py-1 text-xs transition-colors hover:bg-accent/40"
                    @click="applyPreset(preset.value)"
                >
                    <span
                        class="h-4 w-4 rounded-full border"
                        :style="gradientBackgroundStyle(preset.value)"
                    />
                    <span>{{ preset.label }}</span>
                </button>
            </div>
        </div>

        <div class="mt-3 space-y-3">
            <div class="rounded-md border bg-background/50 p-3">
                <div class="mb-2 flex items-center justify-between gap-3">
                    <div class="text-xs font-medium text-foreground">Start</div>
                    <div
                        class="h-5 w-5 shrink-0 rounded-full border"
                        :style="{ backgroundColor: resolvedValue.start }"
                    />
                </div>
                <ColorPicker
                    :model-value="resolvedValue.start"
                    :default-value="resolvedValue.start"
                    @update:model-value="
                        updateGradient({ start: String($event) })
                    "
                />
            </div>
            <div class="rounded-md border bg-background/50 p-3">
                <div class="mb-2 flex items-center justify-between gap-3">
                    <div class="text-xs font-medium text-foreground">End</div>
                    <div
                        class="h-5 w-5 shrink-0 rounded-full border"
                        :style="{ backgroundColor: resolvedValue.end }"
                    />
                </div>
                <ColorPicker
                    :model-value="resolvedValue.end"
                    :default-value="resolvedValue.end"
                    @update:model-value="updateGradient({ end: String($event) })"
                />
            </div>
        </div>

        <div class="mt-3 space-y-1.5">
            <div class="flex items-center justify-between text-xs text-muted-foreground">
                <span>Angle</span>
                <span>{{ resolvedValue.angle }}&deg;</span>
            </div>
            <input
                type="range"
                min="0"
                max="360"
                step="1"
                :value="resolvedValue.angle"
                class="h-2 w-full cursor-pointer appearance-none rounded-full bg-muted"
                @input="
                    updateGradient({
                        angle: Number(
                            ($event.target as HTMLInputElement).value,
                        ),
                    })
                "
            />
            <Input
                type="number"
                min="0"
                max="360"
                :model-value="String(resolvedValue.angle)"
                @update:model-value="
                    updateGradient({ angle: Number($event) || 0 })
                "
            />
        </div>
    </div>
</template>
