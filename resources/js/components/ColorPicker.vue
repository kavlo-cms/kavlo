<script setup lang="ts">
import { computed, ref, watch } from 'vue';
import { Input } from '@/components/ui/input';
import {
    isHexColor,
    normalizeHexColor,
    readableTextColor,
} from '@/lib/blockStyles';

interface ColorPreset {
    label: string;
    value: string;
}

const props = withDefaults(
    defineProps<{
        id?: string;
        modelValue?: string;
        defaultValue?: string;
        presets?: ColorPreset[];
    }>(),
    {
        id: undefined,
        modelValue: undefined,
        defaultValue: '#2563eb',
        presets: () => [],
    },
);

const emit = defineEmits<{
    'update:modelValue': [value: string];
}>();

const isOpen = ref(false);
const draftHex = ref('');
const hue = ref(0);
const saturation = ref(1);
const value = ref(1);
const draggingSaturation = ref(false);

const resolvedColor = computed(() => {
    if (isHexColor(props.modelValue)) {
        return normalizeHexColor(props.modelValue);
    }

    return normalizeHexColor(props.defaultValue);
});

const triggerTextColor = computed(() => readableTextColor(resolvedColor.value));
const saturationPointerStyle = computed(() => ({
    left: `${saturation.value * 100}%`,
    top: `${(1 - value.value) * 100}%`,
}));
const saturationBackgroundStyle = computed(() => ({
    backgroundColor: hsvToHex(hue.value, 1, 1),
}));
const hueBackgroundStyle = computed(() => ({
    background:
        'linear-gradient(to right, #ff0000 0%, #ffff00 17%, #00ff00 33%, #00ffff 50%, #0000ff 67%, #ff00ff 83%, #ff0000 100%)',
}));

watch(
    resolvedColor,
    (color) => {
        const hsv = hexToHsv(color);
        hue.value = hsv.h;
        saturation.value = hsv.s;
        value.value = hsv.v;
        draftHex.value = color;
    },
    { immediate: true },
);

function emitColor(nextColor: string) {
    emit('update:modelValue', normalizeHexColor(nextColor));
}

function applyHex(value: string) {
    const normalized = value.startsWith('#') ? value : `#${value}`;

    if (!isHexColor(normalized)) {
        draftHex.value = resolvedColor.value;
        return;
    }

    emitColor(normalized);
}

function selectPreset(color: string) {
    emitColor(color);
}

function updateFromCurrentHsv() {
    emitColor(hsvToHex(hue.value, saturation.value, value.value));
}

function updateSaturationFromEvent(event: PointerEvent) {
    const target = event.currentTarget as HTMLDivElement | null;

    if (!target) {
        return;
    }

    const rect = target.getBoundingClientRect();
    const nextSaturation = clamp(
        (event.clientX - rect.left) / rect.width,
        0,
        1,
    );
    const nextValue = clamp(1 - (event.clientY - rect.top) / rect.height, 0, 1);

    saturation.value = nextSaturation;
    value.value = nextValue;
    updateFromCurrentHsv();
}

function startSaturationDrag(event: PointerEvent) {
    draggingSaturation.value = true;
    (event.currentTarget as HTMLDivElement | null)?.setPointerCapture?.(
        event.pointerId,
    );
    updateSaturationFromEvent(event);
}

function moveSaturationDrag(event: PointerEvent) {
    if (!draggingSaturation.value) {
        return;
    }

    updateSaturationFromEvent(event);
}

function stopSaturationDrag(event: PointerEvent) {
    draggingSaturation.value = false;
    (event.currentTarget as HTMLDivElement | null)?.releasePointerCapture?.(
        event.pointerId,
    );
}

function clamp(value: number, min: number, max: number): number {
    return Math.min(Math.max(value, min), max);
}

function hexToRgb(hex: string) {
    const normalized = normalizeHexColor(hex);

    return {
        r: Number.parseInt(normalized.slice(1, 3), 16),
        g: Number.parseInt(normalized.slice(3, 5), 16),
        b: Number.parseInt(normalized.slice(5, 7), 16),
    };
}

function rgbToHsv(red: number, green: number, blue: number) {
    const r = red / 255;
    const g = green / 255;
    const b = blue / 255;
    const max = Math.max(r, g, b);
    const min = Math.min(r, g, b);
    const delta = max - min;

    let h = 0;

    if (delta !== 0) {
        if (max === r) {
            h = 60 * (((g - b) / delta) % 6);
        } else if (max === g) {
            h = 60 * ((b - r) / delta + 2);
        } else {
            h = 60 * ((r - g) / delta + 4);
        }
    }

    return {
        h: h < 0 ? h + 360 : h,
        s: max === 0 ? 0 : delta / max,
        v: max,
    };
}

function hexToHsv(hex: string) {
    const rgb = hexToRgb(hex);

    return rgbToHsv(rgb.r, rgb.g, rgb.b);
}

function hsvToHex(h: number, s: number, v: number) {
    const chroma = v * s;
    const hueSegment = h / 60;
    const x = chroma * (1 - Math.abs((hueSegment % 2) - 1));
    const match = v - chroma;

    let red = 0;
    let green = 0;
    let blue = 0;

    if (hueSegment >= 0 && hueSegment < 1) {
        red = chroma;
        green = x;
    } else if (hueSegment < 2) {
        red = x;
        green = chroma;
    } else if (hueSegment < 3) {
        green = chroma;
        blue = x;
    } else if (hueSegment < 4) {
        green = x;
        blue = chroma;
    } else if (hueSegment < 5) {
        red = x;
        blue = chroma;
    } else {
        red = chroma;
        blue = x;
    }

    const toHex = (channel: number) =>
        Math.round((channel + match) * 255)
            .toString(16)
            .padStart(2, '0');

    return `#${toHex(red)}${toHex(green)}${toHex(blue)}`;
}
</script>

<template>
    <div class="rounded-lg border bg-muted/20 p-3">
        <div class="flex items-center gap-3">
            <button
                type="button"
                class="flex min-w-0 flex-1 items-center gap-3 rounded-md border bg-background px-3 py-2 text-left transition-colors hover:bg-accent/40"
                @click="isOpen = !isOpen"
            >
                <span
                    class="h-8 w-8 shrink-0 rounded-md border shadow-sm"
                    :style="{ backgroundColor: resolvedColor }"
                />
                <span class="min-w-0 flex-1">
                    <span class="block truncate text-sm font-medium">
                        {{ resolvedColor.toUpperCase() }}
                    </span>
                    <span class="block text-xs text-muted-foreground">
                        Click to {{ isOpen ? 'collapse' : 'edit' }}
                    </span>
                </span>
            </button>
            <button
                type="button"
                class="rounded-md border px-2.5 py-2 text-xs text-muted-foreground transition-colors hover:bg-accent hover:text-foreground"
                @click="emitColor(defaultValue)"
            >
                Reset
            </button>
        </div>

        <div v-if="isOpen" class="mt-3 space-y-3">
            <div
                :id="id"
                class="relative h-40 cursor-crosshair overflow-hidden rounded-md border"
                :style="saturationBackgroundStyle"
                @pointerdown.prevent="startSaturationDrag"
                @pointermove.prevent="moveSaturationDrag"
                @pointerup.prevent="stopSaturationDrag"
                @pointercancel.prevent="stopSaturationDrag"
            >
                <div
                    class="absolute inset-0"
                    style="
                        background:
                            linear-gradient(to right, #ffffff, transparent),
                            linear-gradient(to top, #000000, transparent);
                    "
                />
                <div
                    class="absolute h-4 w-4 -translate-x-1/2 -translate-y-1/2 rounded-full border-2 border-white shadow"
                    :style="saturationPointerStyle"
                >
                    <div
                        class="h-full w-full rounded-full border border-black/15"
                        :style="{ backgroundColor: resolvedColor }"
                    />
                </div>
            </div>

            <div class="space-y-1.5">
                <div
                    class="flex items-center justify-between text-xs text-muted-foreground"
                >
                    <span>Hue</span>
                    <span>{{ Math.round(hue) }}&deg;</span>
                </div>
                <input
                    type="range"
                    min="0"
                    max="360"
                    step="1"
                    :value="hue"
                    class="h-2 w-full cursor-pointer appearance-none rounded-full border border-transparent"
                    :style="hueBackgroundStyle"
                    @input="
                        hue = Number(($event.target as HTMLInputElement).value);
                        updateFromCurrentHsv();
                    "
                />
            </div>

            <div class="flex items-end gap-3">
                <div class="flex-1 space-y-1.5">
                    <label class="text-xs text-muted-foreground">Hex</label>
                    <Input
                        :model-value="draftHex.toUpperCase()"
                        placeholder="#2563EB"
                        @update:model-value="draftHex = String($event)"
                        @blur="applyHex(draftHex)"
                        @keydown.enter.prevent="applyHex(draftHex)"
                    />
                </div>
                <div
                    class="flex h-10 w-16 items-center justify-center rounded-md border text-xs font-medium"
                    :style="{
                        backgroundColor: resolvedColor,
                        color: triggerTextColor,
                    }"
                >
                    Aa
                </div>
            </div>

            <div v-if="presets.length" class="space-y-1.5">
                <div class="text-xs text-muted-foreground">Presets</div>
                <div class="flex flex-wrap gap-2">
                    <button
                        v-for="preset in presets"
                        :key="preset.value"
                        type="button"
                        class="group flex items-center gap-2 rounded-full border bg-background px-2 py-1 text-xs transition-colors hover:bg-accent/40"
                        @click="selectPreset(preset.value)"
                    >
                        <span
                            class="h-4 w-4 rounded-full border"
                            :style="{ backgroundColor: preset.value }"
                        />
                        <span>{{ preset.label }}</span>
                    </button>
                </div>
            </div>
        </div>
    </div>
</template>
