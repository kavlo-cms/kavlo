export interface BlockField {
    key: string;
    label: string;
    type:
        | 'text'
        | 'textarea'
        | 'url'
        | 'select'
        | 'toggle'
        | 'number'
        | 'media'
        | 'page-link';
    placeholder?: string;
    options?: { value: string; label: string }[];
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
                key: 'level',
                label: 'Level',
                type: 'select',
                options: [
                    { value: 'h1', label: 'H1 – Page title' },
                    { value: 'h2', label: 'H2 – Section heading' },
                    { value: 'h3', label: 'H3 – Sub-heading' },
                    { value: 'h4', label: 'H4 – Small heading' },
                ],
            },
            {
                key: 'align',
                label: 'Alignment',
                type: 'select',
                options: [
                    { value: 'left', label: 'Left' },
                    { value: 'center', label: 'Center' },
                    { value: 'right', label: 'Right' },
                ],
            },
        ],
    },
    text: {
        label: 'Paragraph',
        description: 'Rich text paragraph',
        group: 'text',
        icon: 'AlignLeft',
        inlineEditable: true,
        fields: [],
    },
    quote: {
        label: 'Quote',
        description: 'Blockquote with author attribution',
        group: 'text',
        icon: 'Quote',
        inlineEditable: true,
        fields: [],
    },
    list: {
        label: 'List',
        description: 'Bullet or numbered list',
        group: 'text',
        icon: 'List',
        inlineEditable: true,
        fields: [
            {
                key: 'style',
                label: 'List style',
                type: 'select',
                options: [
                    { value: 'bullet', label: 'Bullet' },
                    { value: 'numbered', label: 'Numbered' },
                ],
            },
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
                key: 'background',
                label: 'Background',
                type: 'text',
                placeholder: '#ffffff or bg-slate-950',
            },
            {
                key: 'padding',
                label: 'Padding class',
                type: 'text',
                placeholder: 'py-12 px-6',
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
                key: 'count',
                label: 'Number of columns',
                type: 'select',
                options: [
                    { value: '2', label: '2 columns' },
                    { value: '3', label: '3 columns' },
                    { value: '4', label: '4 columns' },
                ],
            },
            {
                key: 'gap',
                label: 'Column gap',
                type: 'select',
                options: [
                    { value: 'sm', label: 'Small' },
                    { value: 'md', label: 'Medium' },
                    { value: 'lg', label: 'Large' },
                ],
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
                key: 'style',
                label: 'Style',
                type: 'select',
                options: [
                    { value: 'line', label: 'Line' },
                    { value: 'dots', label: 'Dots' },
                    { value: 'none', label: 'None (space only)' },
                ],
            },
            {
                key: 'spacing',
                label: 'Spacing',
                type: 'select',
                options: [
                    { value: 'sm', label: 'Small' },
                    { value: 'md', label: 'Medium' },
                    { value: 'lg', label: 'Large' },
                ],
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
                key: 'size',
                label: 'Height',
                type: 'select',
                options: [
                    { value: 'xs', label: 'XS – 1rem' },
                    { value: 'sm', label: 'SM – 2rem' },
                    { value: 'md', label: 'MD – 4rem' },
                    { value: 'lg', label: 'LG – 8rem' },
                    { value: 'xl', label: 'XL – 16rem' },
                ],
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
            { key: 'src', label: 'Image', type: 'media' },
            {
                key: 'alt',
                label: 'Alt text',
                type: 'text',
                placeholder: 'Describe the image',
            },
            {
                key: 'caption',
                label: 'Caption',
                type: 'text',
                placeholder: 'Optional caption below image',
            },
            {
                key: 'width',
                label: 'Width',
                type: 'select',
                options: [
                    { value: 'full', label: 'Full width' },
                    { value: 'wide', label: 'Wide' },
                    { value: 'medium', label: 'Medium' },
                    { value: 'small', label: 'Small' },
                ],
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
                key: 'url',
                label: 'Video URL',
                type: 'url',
                placeholder: 'https://youtube.com/watch?v=... or vimeo.com/...',
            },
            {
                key: 'caption',
                label: 'Caption',
                type: 'text',
                placeholder: 'Optional caption',
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
            {
                key: 'background_image',
                label: 'Background Image',
                type: 'media',
            },
            {
                key: 'width_mode',
                label: 'Layout Width',
                type: 'select',
                options: [
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
                ],
            },
        ],
    },
    button: {
        label: 'Button',
        description: 'Call-to-action button with link',
        group: 'components',
        icon: 'MousePointer2',
        inlineEditable: true,
        fields: [
            { key: 'url', label: 'Link', type: 'page-link' },
            {
                key: 'variant',
                label: 'Style',
                type: 'select',
                options: [
                    { value: 'primary', label: 'Primary' },
                    { value: 'secondary', label: 'Secondary' },
                    { value: 'outline', label: 'Outline' },
                    { value: 'ghost', label: 'Ghost' },
                ],
            },
            {
                key: 'size',
                label: 'Size',
                type: 'select',
                options: [
                    { value: 'sm', label: 'Small' },
                    { value: 'md', label: 'Medium' },
                    { value: 'lg', label: 'Large' },
                ],
            },
            {
                key: 'align',
                label: 'Alignment',
                type: 'select',
                options: [
                    { value: 'left', label: 'Left' },
                    { value: 'center', label: 'Center' },
                    { value: 'right', label: 'Right' },
                ],
            },
            { key: 'new_tab', label: 'Open in new tab', type: 'toggle' },
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
                key: 'type',
                label: 'Type',
                type: 'select',
                options: [
                    { value: 'info', label: 'Info' },
                    { value: 'success', label: 'Success' },
                    { value: 'warning', label: 'Warning' },
                    { value: 'error', label: 'Error' },
                ],
            },
        ],
    },
};
