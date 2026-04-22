import type { GradientConfig } from '@/lib/blockStyles';

export interface BlockOption {
    value: string;
    label: string;
}

export interface BlockPreset {
    label: string;
    value: string | GradientConfig;
}

export interface BlockField {
    key: string;
    label: string;
    type:
        | 'text'
        | 'textarea'
        | 'url'
        | 'select'
        | 'color'
        | 'gradient'
        | 'toggle'
        | 'number'
        | 'media'
        | 'page-link';
    placeholder?: string;
    options?: BlockOption[];
    presets?: BlockPreset[];
    defaultValue?: unknown;
}

export interface BlockSchema {
    label: string;
    description?: string;
    group: string;
    icon: string;
    inlineEditable?: boolean;
    acceptsChildren?: boolean;
    allowedChildren?: string[];
    fields: BlockField[];
}

export interface AvailableBlock {
    type: string;
    label: string;
    description?: string;
    group?: string;
    icon?: string;
    fields?: BlockSchema['fields'];
    defaultData?: Record<string, unknown>;
    source?: string;
}

export interface BlockPageOption {
    id: number;
    title: string;
    slug: string;
}
