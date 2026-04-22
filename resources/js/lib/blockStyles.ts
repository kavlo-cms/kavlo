import type { CSSProperties } from 'vue';

export type BlockWidth = 'full' | 'wide' | 'medium' | 'narrow';
export type TextTone =
    | 'default'
    | 'muted'
    | 'primary'
    | 'accent'
    | 'inverse';
export type ButtonTone = 'brand' | 'neutral' | 'success' | 'danger';
export type ButtonRadius = 'rounded' | 'soft' | 'pill' | 'square';

export interface ColorPreset {
    label: string;
    value: string;
}

export interface GradientConfig {
    start: string;
    end: string;
    angle: number;
}

export interface GradientPreset {
    label: string;
    value: GradientConfig;
}

export const textToneColors: Record<TextTone, string> = {
    default: '#111827',
    muted: '#6b7280',
    primary: '#2563eb',
    accent: '#0284c7',
    inverse: '#ffffff',
};

export const buttonToneColors: Record<ButtonTone, string> = {
    brand: '#2563eb',
    neutral: '#0f172a',
    success: '#059669',
    danger: '#dc2626',
};

export function isHexColor(value: unknown): value is string {
    return (
        typeof value === 'string' &&
        /^#(?:[0-9a-fA-F]{3}|[0-9a-fA-F]{6})$/.test(value.trim())
    );
}

export function normalizeHexColor(value: string): string {
    const trimmed = value.trim();

    if (trimmed.length === 4) {
        return `#${trimmed[1]}${trimmed[1]}${trimmed[2]}${trimmed[2]}${trimmed[3]}${trimmed[3]}`.toLowerCase();
    }

    return trimmed.toLowerCase();
}

function hexToRgb(value: string): [number, number, number] | null {
    if (!isHexColor(value)) {
        return null;
    }

    const normalized = normalizeHexColor(value);
    const red = Number.parseInt(normalized.slice(1, 3), 16);
    const green = Number.parseInt(normalized.slice(3, 5), 16);
    const blue = Number.parseInt(normalized.slice(5, 7), 16);

    return [red, green, blue];
}

function hexToRgba(value: string, alpha: number): string | undefined {
    const rgb = hexToRgb(value);

    return rgb
        ? `rgba(${rgb[0]}, ${rgb[1]}, ${rgb[2]}, ${alpha})`
        : undefined;
}

function readableTextColor(background: string): string {
    const rgb = hexToRgb(background);

    if (!rgb) {
        return '#ffffff';
    }

    const [red, green, blue] = rgb.map((channel) => channel / 255);
    const luminance = 0.2126 * red + 0.7152 * green + 0.0722 * blue;

    return luminance > 0.6 ? '#0f172a' : '#ffffff';
}

export { readableTextColor };

export function isGradientConfig(value: unknown): value is GradientConfig {
    return (
        typeof value === 'object' &&
        value !== null &&
        isHexColor((value as GradientConfig).start) &&
        isHexColor((value as GradientConfig).end) &&
        Number.isFinite((value as GradientConfig).angle)
    );
}

function normalizeGradientAngle(value: number): number {
    const normalized = Math.round(value % 360);

    return normalized < 0 ? normalized + 360 : normalized;
}

export function normalizeGradientConfig(
    value: unknown,
    fallback?: GradientConfig,
): GradientConfig | undefined {
    if (isGradientConfig(value)) {
        return {
            start: normalizeHexColor(value.start),
            end: normalizeHexColor(value.end),
            angle: normalizeGradientAngle(value.angle),
        };
    }

    if (fallback && isGradientConfig(fallback)) {
        return normalizeGradientConfig(fallback);
    }

    return undefined;
}

export function gradientCss(value: GradientConfig): string {
    return `linear-gradient(${value.angle}deg, ${value.start}, ${value.end})`;
}

export function gradientTextStyle(
    value: unknown,
    fallback?: GradientConfig,
): CSSProperties | undefined {
    const gradient = normalizeGradientConfig(value, fallback);

    if (!gradient) {
        return undefined;
    }

    return {
        backgroundImage: gradientCss(gradient),
        backgroundClip: 'text',
        WebkitBackgroundClip: 'text',
        color: 'transparent',
        WebkitTextFillColor: 'transparent',
        caretColor: gradient.start,
    };
}

export function gradientBackgroundStyle(
    value: unknown,
    fallback?: GradientConfig,
): CSSProperties | undefined {
    const gradient = normalizeGradientConfig(value, fallback);

    if (!gradient) {
        return undefined;
    }

    return {
        backgroundImage: gradientCss(gradient),
        color: readableTextColor(gradient.start),
        borderColor: 'transparent',
    };
}

export const textColorPresets: ColorPreset[] = [
    { label: 'Default', value: textToneColors.default },
    { label: 'Muted', value: textToneColors.muted },
    { label: 'Primary', value: textToneColors.primary },
    { label: 'Accent', value: textToneColors.accent },
    { label: 'Inverse', value: textToneColors.inverse },
];

export const buttonColorPresets: ColorPreset[] = [
    { label: 'Brand', value: buttonToneColors.brand },
    { label: 'Neutral', value: buttonToneColors.neutral },
    { label: 'Success', value: buttonToneColors.success },
    { label: 'Danger', value: buttonToneColors.danger },
];

export const textGradientPresets: GradientPreset[] = [
    { label: 'Ocean', value: { start: '#38bdf8', end: '#818cf8', angle: 90 } },
    { label: 'Sunrise', value: { start: '#f97316', end: '#facc15', angle: 90 } },
    { label: 'Aurora', value: { start: '#22c55e', end: '#06b6d4', angle: 90 } },
];

export const heroHeadlineGradientPresets: GradientPreset[] = [
    {
        label: 'Moonlight',
        value: { start: '#ffffff', end: '#64748b', angle: 90 },
    },
    {
        label: 'Skyline',
        value: { start: '#67e8f9', end: '#a78bfa', angle: 90 },
    },
    {
        label: 'Solar',
        value: { start: '#fde68a', end: '#f97316', angle: 90 },
    },
];

export const buttonGradientPresets: GradientPreset[] = [
    { label: 'Brand', value: { start: '#2563eb', end: '#7c3aed', angle: 135 } },
    {
        label: 'Success',
        value: { start: '#22c55e', end: '#059669', angle: 135 },
    },
    {
        label: 'Sunset',
        value: { start: '#fb7185', end: '#f97316', angle: 135 },
    },
];

export function resolveBlockColorInputValue(
    key: string,
    value: unknown,
    fallback = '#000000',
): string {
    if (isHexColor(value)) {
        return normalizeHexColor(value);
    }

    if (key === 'text_color') {
        return textToneColors[(String(value ?? 'default') as TextTone)] ?? fallback;
    }

    if (key === 'tone') {
        return buttonToneColors[(String(value ?? 'brand') as ButtonTone)] ?? fallback;
    }

    return fallback;
}

export function blockWidthClass(value: unknown): string {
    const width = String(value ?? 'medium') as BlockWidth;

    return (
        {
            full: 'max-w-none',
            wide: 'max-w-5xl',
            medium: 'max-w-3xl',
            narrow: 'max-w-2xl',
        }[width] ?? 'max-w-3xl'
    );
}

export function textToneClass(value: unknown): string {
    if (isHexColor(value)) {
        return '';
    }

    const tone = String(value ?? 'default') as TextTone;

    return (
        {
            default: 'text-foreground',
            muted: 'text-muted-foreground',
            primary: 'text-primary',
            accent: 'text-sky-600 dark:text-sky-400',
            inverse: 'text-white',
        }[tone] ?? 'text-foreground'
    );
}

export function textToneStyle(value: unknown): CSSProperties | undefined {
    return isHexColor(value) ? { color: normalizeHexColor(value) } : undefined;
}

export function subtleTextToneClass(value: unknown): string {
    if (isHexColor(value)) {
        return '';
    }

    const tone = String(value ?? 'default') as TextTone;

    return (
        {
            default: 'text-muted-foreground',
            muted: 'text-muted-foreground/80',
            primary: 'text-primary/80',
            accent: 'text-sky-500 dark:text-sky-300',
            inverse: 'text-slate-300',
        }[tone] ?? 'text-muted-foreground'
    );
}

export function subtleTextToneStyle(value: unknown): CSSProperties | undefined {
    return isHexColor(value)
        ? { color: normalizeHexColor(value), opacity: 0.8 }
        : undefined;
}

export function buttonVariantClass(variantValue: unknown, toneValue: unknown): string {
    const variant = String(variantValue ?? 'primary');

    if (isHexColor(toneValue)) {
        return (
            {
                primary: 'border border-transparent',
                secondary: 'border border-transparent',
                outline: 'border bg-transparent',
                ghost: 'bg-transparent',
            }[variant] ?? 'border border-transparent'
        );
    }

    const tone = String(toneValue ?? 'brand') as ButtonTone;

    const classes: Record<string, Record<ButtonTone, string>> = {
        primary: {
            brand: 'bg-primary text-primary-foreground hover:bg-primary/90',
            neutral: 'bg-slate-900 text-white hover:bg-slate-800 dark:bg-slate-100 dark:text-slate-900 dark:hover:bg-slate-200',
            success: 'bg-emerald-600 text-white hover:bg-emerald-500',
            danger: 'bg-red-600 text-white hover:bg-red-500',
        },
        secondary: {
            brand: 'bg-secondary text-secondary-foreground hover:bg-secondary/80',
            neutral: 'bg-slate-100 text-slate-900 hover:bg-slate-200 dark:bg-slate-800 dark:text-slate-100 dark:hover:bg-slate-700',
            success: 'bg-emerald-100 text-emerald-800 hover:bg-emerald-200 dark:bg-emerald-950 dark:text-emerald-200 dark:hover:bg-emerald-900',
            danger: 'bg-red-100 text-red-800 hover:bg-red-200 dark:bg-red-950 dark:text-red-200 dark:hover:bg-red-900',
        },
        outline: {
            brand: 'border border-primary text-primary hover:bg-primary/10',
            neutral: 'border border-slate-300 text-slate-900 hover:bg-slate-100 dark:border-slate-700 dark:text-slate-100 dark:hover:bg-slate-800',
            success: 'border border-emerald-500 text-emerald-700 hover:bg-emerald-50 dark:text-emerald-300 dark:hover:bg-emerald-950',
            danger: 'border border-red-500 text-red-700 hover:bg-red-50 dark:text-red-300 dark:hover:bg-red-950',
        },
        ghost: {
            brand: 'text-primary hover:bg-primary/10',
            neutral: 'text-slate-900 hover:bg-slate-100 dark:text-slate-100 dark:hover:bg-slate-800',
            success: 'text-emerald-700 hover:bg-emerald-50 dark:text-emerald-300 dark:hover:bg-emerald-950',
            danger: 'text-red-700 hover:bg-red-50 dark:text-red-300 dark:hover:bg-red-950',
        },
    };

    return classes[variant]?.[tone] ?? classes.primary.brand;
}

export function buttonVariantStyle(
    variantValue: unknown,
    toneValue: unknown,
): CSSProperties | undefined {
    if (!isHexColor(toneValue)) {
        return undefined;
    }

    const variant = String(variantValue ?? 'primary');
    const color = normalizeHexColor(toneValue);

    switch (variant) {
        case 'secondary':
            return {
                backgroundColor: hexToRgba(color, 0.14),
                borderColor: hexToRgba(color, 0.22),
                color,
            };
        case 'outline':
            return { borderColor: color, color };
        case 'ghost':
            return { color };
        case 'primary':
        default:
            return {
                backgroundColor: color,
                borderColor: color,
                color: readableTextColor(color),
            };
    }
}

export function buttonGradientStyle(value: unknown): CSSProperties | undefined {
    return gradientBackgroundStyle(value);
}

export function buttonRadiusClass(value: unknown): string {
    const radius = String(value ?? 'rounded') as ButtonRadius;

    return (
        {
            rounded: 'rounded-lg',
            soft: 'rounded-md',
            pill: 'rounded-full',
            square: 'rounded-none',
        }[radius] ?? 'rounded-lg'
    );
}

export function buttonWidthClass(value: unknown): string {
    return String(value ?? 'auto') === 'full' ? 'w-full justify-center' : '';
}
