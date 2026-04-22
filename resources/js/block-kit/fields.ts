import {
    buttonColorPresets,
    buttonGradientPresets,
    heroHeadlineGradientPresets,
    textColorPresets,
    textGradientPresets,
} from '@/lib/blockStyles';
import type { BlockField, BlockOption } from './schema';

type FieldOverrides<T extends BlockField> = Omit<
    Partial<T>,
    'key' | 'label' | 'type'
>;

export const widthOptions: BlockOption[] = [
    { value: 'full', label: 'Full width' },
    { value: 'wide', label: 'Wide' },
    { value: 'medium', label: 'Medium' },
    { value: 'narrow', label: 'Narrow' },
];

export const alignmentOptions: BlockOption[] = [
    { value: 'left', label: 'Left' },
    { value: 'center', label: 'Center' },
    { value: 'right', label: 'Right' },
];

export function textField(
    key: string,
    label: string,
    overrides: FieldOverrides<BlockField> = {},
): BlockField {
    return { key, label, type: 'text', ...overrides };
}

export function textareaField(
    key: string,
    label: string,
    overrides: FieldOverrides<BlockField> = {},
): BlockField {
    return { key, label, type: 'textarea', ...overrides };
}

export function urlField(
    key: string,
    label: string,
    overrides: FieldOverrides<BlockField> = {},
): BlockField {
    return { key, label, type: 'url', ...overrides };
}

export function numberField(
    key: string,
    label: string,
    overrides: FieldOverrides<BlockField> = {},
): BlockField {
    return { key, label, type: 'number', ...overrides };
}

export function toggleField(
    key: string,
    label: string,
    overrides: FieldOverrides<BlockField> = {},
): BlockField {
    return { key, label, type: 'toggle', ...overrides };
}

export function mediaField(
    key: string,
    label: string,
    overrides: FieldOverrides<BlockField> = {},
): BlockField {
    return { key, label, type: 'media', ...overrides };
}

export function pageLinkField(
    key: string,
    label: string,
    overrides: FieldOverrides<BlockField> = {},
): BlockField {
    return { key, label, type: 'page-link', ...overrides };
}

export function selectField(
    key: string,
    label: string,
    options: BlockOption[],
    overrides: FieldOverrides<BlockField> = {},
): BlockField {
    return { key, label, type: 'select', options, ...overrides };
}

export function colorField(
    key: string,
    label: string,
    overrides: FieldOverrides<BlockField> = {},
): BlockField {
    return { key, label, type: 'color', ...overrides };
}

export function gradientField(
    key: string,
    label: string,
    overrides: FieldOverrides<BlockField> = {},
): BlockField {
    return { key, label, type: 'gradient', ...overrides };
}

export const commonWidthField = selectField('width', 'Width', widthOptions);

export const commonTextColorField = colorField('text_color', 'Text color', {
    defaultValue: '#111827',
    presets: textColorPresets,
});

export const commonButtonColorField = colorField('tone', 'Color', {
    defaultValue: '#2563eb',
    presets: buttonColorPresets,
});

export const commonTextGradientField = gradientField(
    'text_gradient',
    'Text gradient',
    {
        defaultValue: textGradientPresets[0]?.value,
        presets: textGradientPresets,
    },
);

export const commonButtonGradientField = gradientField(
    'gradient',
    'Background gradient',
    {
        defaultValue: buttonGradientPresets[0]?.value,
        presets: buttonGradientPresets,
    },
);

export const commonHeroHeadlineGradientField = gradientField(
    'headline_gradient',
    'Headline gradient',
    {
        defaultValue: heroHeadlineGradientPresets[0]?.value,
        presets: heroHeadlineGradientPresets,
    },
);
