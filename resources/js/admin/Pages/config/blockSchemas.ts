import {
    alignmentOptions,
    commonButtonColorField,
    commonButtonGradientField,
    commonHeroHeadlineGradientField,
    commonTextColorField,
    commonTextGradientField,
    commonWidthField,
    mediaField,
    pageLinkField,
    selectField,
    textField,
    toggleField,
    urlField,
} from '@/block-kit';
export type { BlockField, BlockSchema } from '@/block-kit';
import type { BlockSchema } from '@/block-kit';

export const blockGroups: Record<string, string> = {
    text: 'Text',
    layout: 'Layout',
    media: 'Media',
    components: 'Components',
    form: 'Form',
};

export const blockGroupOrder = [
    'text',
    'layout',
    'media',
    'components',
    'form',
];

export const blockSchemas: Record<string, BlockSchema> = {
    // ── Text ──────────────────────────────────────────────────────────────────
    heading: {
        label: 'Heading',
        description: 'H1–H4 heading with optional alignment',
        group: 'text',
        icon: 'Heading1',
        inlineEditable: true,
        fields: [
            {
                ...selectField('level', 'Level', [
                    { value: 'h1', label: 'H1 – Page title' },
                    { value: 'h2', label: 'H2 – Section heading' },
                    { value: 'h3', label: 'H3 – Sub-heading' },
                    { value: 'h4', label: 'H4 – Small heading' },
                ]),
            },
            selectField('align', 'Alignment', alignmentOptions),
            commonWidthField,
            commonTextColorField,
            commonTextGradientField,
        ],
    },
    text: {
        label: 'Paragraph',
        description: 'Rich text paragraph',
        group: 'text',
        icon: 'AlignLeft',
        inlineEditable: true,
        fields: [
            commonWidthField,
            commonTextColorField,
            commonTextGradientField,
        ],
    },
    content: {
        label: 'Content',
        description: 'Renders the page content editor output as a draggable block',
        group: 'text',
        icon: 'AlignLeft',
        fields: [],
    },
    quote: {
        label: 'Quote',
        description: 'Blockquote with author attribution',
        group: 'text',
        icon: 'Quote',
        inlineEditable: true,
        fields: [commonWidthField, commonTextColorField],
    },
    list: {
        label: 'List',
        description: 'Bullet or numbered list',
        group: 'text',
        icon: 'List',
        inlineEditable: true,
        fields: [
            {
                ...selectField('style', 'List style', [
                    { value: 'bullet', label: 'Bullet' },
                    { value: 'numbered', label: 'Numbered' },
                ]),
            },
            commonWidthField,
            commonTextColorField,
        ],
    },

    // ── Layout ────────────────────────────────────────────────────────────────
    section: {
        label: 'Section',
        description: 'Container that holds other blocks',
        group: 'layout',
        icon: 'Square',
        acceptsChildren: true,
        fields: [
            {
                ...textField('background', 'Background', {
                    placeholder: '#ffffff or bg-slate-950',
                }),
            },
            {
                ...textField('padding', 'Padding class', {
                    placeholder: 'py-12 px-6',
                }),
            },
        ],
    },
    columns: {
        label: 'Columns',
        description: '2, 3, or 4 equal column layout',
        group: 'layout',
        icon: 'LayoutGrid',
        acceptsChildren: true,
        fields: [
            {
                ...selectField('count', 'Number of columns', [
                    { value: '2', label: '2 columns' },
                    { value: '3', label: '3 columns' },
                    { value: '4', label: '4 columns' },
                ]),
            },
            {
                ...selectField('gap', 'Column gap', [
                    { value: 'sm', label: 'Small' },
                    { value: 'md', label: 'Medium' },
                    { value: 'lg', label: 'Large' },
                ]),
            },
        ],
    },
    divider: {
        label: 'Divider',
        description: 'Horizontal separator line',
        group: 'layout',
        icon: 'Minus',
        fields: [
            {
                ...selectField('style', 'Style', [
                    { value: 'line', label: 'Line' },
                    { value: 'dots', label: 'Dots' },
                    { value: 'none', label: 'None (space only)' },
                ]),
            },
            {
                ...selectField('spacing', 'Spacing', [
                    { value: 'sm', label: 'Small' },
                    { value: 'md', label: 'Medium' },
                    { value: 'lg', label: 'Large' },
                ]),
            },
        ],
    },
    spacer: {
        label: 'Spacer',
        description: 'Adjustable vertical space',
        group: 'layout',
        icon: 'ArrowUpDown',
        fields: [
            {
                ...selectField('size', 'Height', [
                    { value: 'xs', label: 'XS – 1rem' },
                    { value: 'sm', label: 'SM – 2rem' },
                    { value: 'md', label: 'MD – 4rem' },
                    { value: 'lg', label: 'LG – 8rem' },
                    { value: 'xl', label: 'XL – 16rem' },
                ]),
            },
        ],
    },

    // ── Media ─────────────────────────────────────────────────────────────────
    image: {
        label: 'Image',
        description: 'Single image with alt text and caption',
        group: 'media',
        icon: 'Image',
        fields: [
            mediaField('src', 'Image'),
            {
                ...textField('alt', 'Alt text', {
                    placeholder: 'Describe the image',
                }),
            },
            {
                ...textField('caption', 'Caption', {
                    placeholder: 'Optional caption below image',
                }),
            },
            {
                ...selectField('width', 'Width', [
                    { value: 'full', label: 'Full width' },
                    { value: 'wide', label: 'Wide' },
                    { value: 'medium', label: 'Medium' },
                    { value: 'small', label: 'Small' },
                ]),
            },
        ],
    },
    video: {
        label: 'Video',
        description: 'YouTube or Vimeo embed',
        group: 'media',
        icon: 'Video',
        fields: [
            {
                ...urlField('url', 'Video URL', {
                    placeholder:
                        'https://youtube.com/watch?v=... or vimeo.com/...',
                }),
            },
            {
                ...textField('caption', 'Caption', {
                    placeholder: 'Optional caption',
                }),
            },
        ],
    },

    // ── Components ────────────────────────────────────────────────────────────
    hero: {
        label: 'Hero',
        description: 'Full-width hero section with headline and CTA',
        group: 'components',
        icon: 'Sparkles',
        inlineEditable: true,
        acceptsChildren: true,
        allowedChildren: ['button'],
        fields: [
            mediaField('background_image', 'Background Image'),
            {
                ...selectField('width_mode', 'Layout Width', [
                    {
                        value: 'full-page-constrained',
                        label: 'Full page width, constrained content',
                    },
                    {
                        value: 'full-page-unconstrained',
                        label: 'Full page width, unconstrained content',
                    },
                    {
                        value: 'full-content-width',
                        label: 'Full content width',
                    },
                ]),
            },
            commonHeroHeadlineGradientField,
        ],
    },
    button: {
        label: 'Button',
        description: 'Call-to-action button with link',
        group: 'components',
        icon: 'MousePointer2',
        inlineEditable: true,
        fields: [
            pageLinkField('url', 'Link'),
            {
                ...selectField('variant', 'Style', [
                    { value: 'primary', label: 'Primary' },
                    { value: 'secondary', label: 'Secondary' },
                    { value: 'outline', label: 'Outline' },
                    { value: 'ghost', label: 'Ghost' },
                ]),
            },
            {
                ...selectField('size', 'Size', [
                    { value: 'sm', label: 'Small' },
                    { value: 'md', label: 'Medium' },
                    { value: 'lg', label: 'Large' },
                ]),
            },
            selectField('align', 'Alignment', alignmentOptions),
            commonButtonColorField,
            commonButtonGradientField,
            {
                ...selectField('radius', 'Corner style', [
                    { value: 'rounded', label: 'Rounded' },
                    { value: 'soft', label: 'Soft' },
                    { value: 'pill', label: 'Pill' },
                    { value: 'square', label: 'Square' },
                ]),
            },
            {
                ...selectField('width', 'Button width', [
                    { value: 'auto', label: 'Auto' },
                    { value: 'full', label: 'Full width' },
                ]),
            },
            toggleField('new_tab', 'Open in new tab'),
        ],
    },
    callout: {
        label: 'Callout',
        description: 'Highlighted notice or alert box',
        group: 'components',
        icon: 'AlertCircle',
        inlineEditable: true,
        fields: [
            {
                ...selectField('type', 'Type', [
                    { value: 'info', label: 'Info' },
                    { value: 'success', label: 'Success' },
                    { value: 'warning', label: 'Warning' },
                    { value: 'error', label: 'Error' },
                ]),
            },
        ],
    },
};
