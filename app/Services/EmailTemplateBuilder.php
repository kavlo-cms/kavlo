<?php

namespace App\Services;

use App\Facades\Hook;
use App\Models\EmailTemplate;
use App\Models\Form;
use App\Models\Setting;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

class EmailTemplateBuilder
{
    public const GENERIC_CONTEXT = 'generic';

    public const TEST_EMAIL_CONTEXT = 'core.test-email';

    public const FORM_NOTIFICATION_CONTEXT = 'core.form-notification';

    public function availableBlocks(): array
    {
        return Hook::applyFilters('email_builder.blocks', [
            [
                'type' => 'heading',
                'label' => 'Heading',
                'description' => 'Email heading with configurable level and alignment',
                'group' => 'text',
                'icon' => 'Heading1',
                'fields' => [
                    [
                        'key' => 'level',
                        'label' => 'Level',
                        'type' => 'select',
                        'options' => [
                            ['value' => 'h1', 'label' => 'H1'],
                            ['value' => 'h2', 'label' => 'H2'],
                            ['value' => 'h3', 'label' => 'H3'],
                            ['value' => 'h4', 'label' => 'H4'],
                        ],
                    ],
                    [
                        'key' => 'align',
                        'label' => 'Alignment',
                        'type' => 'select',
                        'options' => [
                            ['value' => 'left', 'label' => 'Left'],
                            ['value' => 'center', 'label' => 'Center'],
                            ['value' => 'right', 'label' => 'Right'],
                        ],
                    ],
                ],
                'defaultData' => [
                    'text' => 'Email heading',
                    'level' => 'h2',
                    'align' => 'left',
                ],
            ],
            [
                'type' => 'text',
                'label' => 'Paragraph',
                'description' => 'Text paragraph for email body copy',
                'group' => 'text',
                'icon' => 'AlignLeft',
                'fields' => [
                    [
                        'key' => 'content',
                        'label' => 'Content',
                        'type' => 'textarea',
                        'placeholder' => 'Write your message…',
                    ],
                ],
                'defaultData' => [
                    'content' => 'Write your email content here.',
                ],
            ],
            [
                'type' => 'image',
                'label' => 'Image',
                'description' => 'Email image with URL, alt text, and caption',
                'group' => 'media',
                'icon' => 'Image',
                'fields' => [
                    ['key' => 'src', 'label' => 'Image URL', 'type' => 'url', 'placeholder' => 'https://example.com/image.jpg'],
                    ['key' => 'alt', 'label' => 'Alt text', 'type' => 'text', 'placeholder' => 'Describe the image'],
                    ['key' => 'caption', 'label' => 'Caption', 'type' => 'text', 'placeholder' => 'Optional caption'],
                    [
                        'key' => 'width',
                        'label' => 'Width',
                        'type' => 'select',
                        'options' => [
                            ['value' => 'full', 'label' => 'Full width'],
                            ['value' => 'wide', 'label' => 'Wide'],
                            ['value' => 'medium', 'label' => 'Medium'],
                            ['value' => 'small', 'label' => 'Small'],
                        ],
                    ],
                ],
                'defaultData' => [
                    'src' => '',
                    'alt' => '',
                    'caption' => '',
                    'width' => 'full',
                ],
            ],
            [
                'type' => 'button',
                'label' => 'Button',
                'description' => 'Call-to-action button with email-safe link styling',
                'group' => 'components',
                'icon' => 'MousePointer2',
                'fields' => [
                    ['key' => 'text', 'label' => 'Text', 'type' => 'text', 'placeholder' => 'View details'],
                    ['key' => 'url', 'label' => 'URL', 'type' => 'url', 'placeholder' => 'https://example.com'],
                    [
                        'key' => 'variant',
                        'label' => 'Style',
                        'type' => 'select',
                        'options' => [
                            ['value' => 'primary', 'label' => 'Primary'],
                            ['value' => 'secondary', 'label' => 'Secondary'],
                            ['value' => 'outline', 'label' => 'Outline'],
                            ['value' => 'ghost', 'label' => 'Ghost'],
                        ],
                    ],
                    [
                        'key' => 'size',
                        'label' => 'Size',
                        'type' => 'select',
                        'options' => [
                            ['value' => 'sm', 'label' => 'Small'],
                            ['value' => 'md', 'label' => 'Medium'],
                            ['value' => 'lg', 'label' => 'Large'],
                        ],
                    ],
                    [
                        'key' => 'align',
                        'label' => 'Alignment',
                        'type' => 'select',
                        'options' => [
                            ['value' => 'left', 'label' => 'Left'],
                            ['value' => 'center', 'label' => 'Center'],
                            ['value' => 'right', 'label' => 'Right'],
                        ],
                    ],
                    ['key' => 'new_tab', 'label' => 'Open in new tab', 'type' => 'toggle'],
                ],
                'defaultData' => [
                    'text' => 'View details',
                    'url' => '',
                    'variant' => 'primary',
                    'size' => 'md',
                    'align' => 'center',
                    'new_tab' => false,
                ],
            ],
            [
                'type' => 'divider',
                'label' => 'Divider',
                'description' => 'Separator line between content sections',
                'group' => 'layout',
                'icon' => 'Minus',
                'fields' => [
                    [
                        'key' => 'style',
                        'label' => 'Style',
                        'type' => 'select',
                        'options' => [
                            ['value' => 'line', 'label' => 'Line'],
                            ['value' => 'dots', 'label' => 'Dots'],
                            ['value' => 'none', 'label' => 'None'],
                        ],
                    ],
                    [
                        'key' => 'spacing',
                        'label' => 'Spacing',
                        'type' => 'select',
                        'options' => [
                            ['value' => 'sm', 'label' => 'Small'],
                            ['value' => 'md', 'label' => 'Medium'],
                            ['value' => 'lg', 'label' => 'Large'],
                        ],
                    ],
                ],
                'defaultData' => [
                    'style' => 'line',
                    'spacing' => 'md',
                ],
            ],
            [
                'type' => 'spacer',
                'label' => 'Spacer',
                'description' => 'Vertical spacing between email sections',
                'group' => 'layout',
                'icon' => 'ArrowUpDown',
                'fields' => [
                    [
                        'key' => 'size',
                        'label' => 'Height',
                        'type' => 'select',
                        'options' => [
                            ['value' => 'xs', 'label' => 'XS'],
                            ['value' => 'sm', 'label' => 'SM'],
                            ['value' => 'md', 'label' => 'MD'],
                            ['value' => 'lg', 'label' => 'LG'],
                            ['value' => 'xl', 'label' => 'XL'],
                        ],
                    ],
                ],
                'defaultData' => [
                    'size' => 'md',
                ],
            ],
            [
                'type' => 'columns',
                'label' => 'Columns',
                'description' => 'Two, three, or four column email layout',
                'group' => 'layout',
                'icon' => 'LayoutGrid',
                'fields' => [
                    [
                        'key' => 'count',
                        'label' => 'Columns',
                        'type' => 'select',
                        'options' => [
                            ['value' => '2', 'label' => '2 columns'],
                            ['value' => '3', 'label' => '3 columns'],
                            ['value' => '4', 'label' => '4 columns'],
                        ],
                    ],
                    [
                        'key' => 'gap',
                        'label' => 'Gap',
                        'type' => 'select',
                        'options' => [
                            ['value' => 'sm', 'label' => 'Small'],
                            ['value' => 'md', 'label' => 'Medium'],
                            ['value' => 'lg', 'label' => 'Large'],
                        ],
                    ],
                ],
                'defaultData' => [
                    'count' => '2',
                    'gap' => 'md',
                    'col_0' => [],
                    'col_1' => [],
                ],
            ],
        ]);
    }

    public function availableContexts(): array
    {
        return Hook::applyFilters('email_template.contexts', [
            [
                'key' => self::GENERIC_CONTEXT,
                'label' => 'Generic',
                'description' => 'Reusable email building block for multiple mail flows.',
                'source' => 'core',
                'variables' => $this->baseVariables(),
            ],
            [
                'key' => self::TEST_EMAIL_CONTEXT,
                'label' => 'Test Email',
                'description' => 'Used by Settings -> Email when sending a test message.',
                'source' => 'core',
                'variables' => array_merge($this->baseVariables(), [
                    ['key' => 'recipient.email', 'label' => 'Recipient email', 'example' => 'admin@example.com'],
                    ['key' => 'sent_at', 'label' => 'Sent at timestamp', 'example' => '2026-04-17 06:00:00'],
                ]),
            ],
            [
                'key' => self::FORM_NOTIFICATION_CONTEXT,
                'label' => 'Form Notification',
                'description' => 'Used when a form submission sends a notification email.',
                'source' => 'core',
                'variables' => array_merge($this->baseVariables(), [
                    ['key' => 'recipient.email', 'label' => 'Notification recipient', 'example' => 'team@example.com'],
                    ['key' => 'form.name', 'label' => 'Form name', 'example' => 'Contact'],
                    ['key' => 'form.slug', 'label' => 'Form slug', 'example' => 'contact'],
                    ['key' => 'form.description', 'label' => 'Form description', 'example' => 'Main site contact form'],
                    ['key' => 'submission.*', 'label' => 'Submitted field values', 'example' => '{{ submission.email }}'],
                    ['key' => 'submitted_at', 'label' => 'Submission timestamp', 'example' => '2026-04-17 06:00:00'],
                ]),
            ],
        ]);
    }

    public function publicContexts(): array
    {
        return array_map(
            fn (array $context) => Arr::except($context, ['sample_data']),
            $this->availableContexts(),
        );
    }

    public function contextKeys(): array
    {
        return array_column($this->availableContexts(), 'key');
    }

    public function templateOptionsFor(string $contextKey): array
    {
        if (! $this->emailTemplatesTableAvailable()) {
            return [];
        }

        return EmailTemplate::query()
            ->whereIn('context_key', [self::GENERIC_CONTEXT, $contextKey])
            ->orderBy('name')
            ->get()
            ->map(fn (EmailTemplate $template) => [
                'value' => (string) $template->id,
                'label' => $template->name,
            ])
            ->values()
            ->all();
    }

    public function findTemplateForContext(int|string|null $templateId, string $contextKey): ?EmailTemplate
    {
        $id = (int) $templateId;

        if ($id < 1 || ! $this->emailTemplatesTableAvailable()) {
            return null;
        }

        return EmailTemplate::query()
            ->whereKey($id)
            ->whereIn('context_key', [self::GENERIC_CONTEXT, $contextKey])
            ->first();
    }

    public function validateBlocks(array $blocks): array
    {
        $allowed = collect($this->availableBlocks())->pluck('type')->all();
        $errors = [];

        foreach ($blocks as $index => $block) {
            $type = trim((string) ($block['type'] ?? ''));

            if ($type === '' || ! in_array($type, $allowed, true)) {
                $errors[] = 'Block #'.($index + 1).' uses an unsupported email block.';

                continue;
            }

            if ($type === 'columns') {
                $data = is_array($block['data'] ?? null) ? $block['data'] : [];
                $count = max(2, min(4, (int) ($data['count'] ?? 2)));

                for ($column = 0; $column < $count; $column++) {
                    $errors = [
                        ...$errors,
                        ...$this->validateBlocks(is_array($data["col_{$column}"] ?? null) ? $data["col_{$column}"] : []),
                    ];
                }
            }
        }

        return $errors;
    }

    public function normalizeBlocks(array $blocks): array
    {
        return array_values(array_map(function (array $block, int $index) {
            $type = trim((string) ($block['type'] ?? ''));
            $definition = collect($this->availableBlocks())->firstWhere('type', $type) ?? [];
            $data = array_merge($definition['defaultData'] ?? [], is_array($block['data'] ?? null) ? $block['data'] : []);

            if ($type === 'columns') {
                $count = max(2, min(4, (int) ($data['count'] ?? 2)));
                $data['count'] = (string) $count;
                $data['gap'] = in_array($data['gap'] ?? 'md', ['sm', 'md', 'lg'], true) ? $data['gap'] : 'md';

                for ($column = 0; $column < $count; $column++) {
                    $data["col_{$column}"] = $this->normalizeBlocks(is_array($data["col_{$column}"] ?? null) ? $data["col_{$column}"] : []);
                }
            }

            return [
                'id' => (string) ($block['id'] ?? Str::uuid()),
                'type' => $type,
                'data' => $data,
                'order' => isset($block['order']) ? (int) $block['order'] : $index,
            ];
        }, $blocks, array_keys($blocks)));
    }

    public function testEmailData(string $recipientEmail): array
    {
        return array_merge($this->baseTemplateData($recipientEmail), [
            'sent_at' => now()->toDateTimeString(),
        ]);
    }

    public function formNotificationData(Form $form, array $submission, string $recipientEmail): array
    {
        return array_merge($this->baseTemplateData($recipientEmail), [
            'form' => [
                'name' => $form->name,
                'slug' => $form->slug,
                'description' => $form->description,
            ],
            'submission' => $this->stringifySubmissionData($submission),
            'submitted_at' => now()->toDateTimeString(),
        ]);
    }

    protected function baseTemplateData(?string $recipientEmail = null): array
    {
        return [
            'site' => [
                'name' => Setting::get('site_name', config('app.name')),
                'url' => url('/'),
            ],
            'recipient' => [
                'email' => $recipientEmail,
            ],
            'year' => now()->format('Y'),
        ];
    }

    protected function baseVariables(): array
    {
        return [
            ['key' => 'site.name', 'label' => 'Site name', 'example' => 'My Site'],
            ['key' => 'site.url', 'label' => 'Site URL', 'example' => 'https://example.com'],
            ['key' => 'year', 'label' => 'Current year', 'example' => '2026'],
        ];
    }

    protected function stringifySubmissionData(array $submission): array
    {
        $result = [];

        foreach ($submission as $key => $value) {
            $result[$key] = match (true) {
                is_array($value) => implode(', ', array_map(fn ($item) => $this->scalarToString($item), $value)),
                default => $this->scalarToString($value),
            };
        }

        return $result;
    }

    protected function scalarToString(mixed $value): string
    {
        return match (true) {
            is_bool($value) => $value ? 'Yes' : 'No',
            $value === null => '',
            default => (string) $value,
        };
    }

    protected function emailTemplatesTableAvailable(): bool
    {
        try {
            return Schema::hasTable('email_templates');
        } catch (\Throwable) {
            return false;
        }
    }
}
