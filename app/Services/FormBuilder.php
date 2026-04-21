<?php

namespace App\Services;

use App\Facades\Hook;
use App\Mail\KavloTemplateMail;
use App\Models\Form;
use App\Models\FormSubmission;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class FormBuilder
{
    public const DEFAULT_ACTION = 'core.store-submission';

    public static function availableBlocks(): array
    {
        return Hook::applyFilters('form_builder.blocks', [
            [
                'type' => 'input',
                'label' => 'Input',
                'description' => 'Single-line text, email, phone, number, date, or file field',
                'group' => 'form',
                'icon' => 'AlignLeft',
                'fields' => [
                    [
                        'key' => 'input_type',
                        'label' => 'Input Type',
                        'type' => 'select',
                        'options' => array_map(
                            fn (string $value, string $label) => ['value' => $value, 'label' => $label],
                            array_keys(self::inputTypeOptions()),
                            self::inputTypeOptions(),
                        ),
                    ],
                    ['key' => 'label', 'label' => 'Label', 'type' => 'text'],
                    ['key' => 'key', 'label' => 'Field Key', 'type' => 'text'],
                    ['key' => 'placeholder', 'label' => 'Placeholder', 'type' => 'text'],
                    ['key' => 'required', 'label' => 'Required', 'type' => 'toggle'],
                ],
                'defaultData' => [
                    'input_type' => 'text',
                    'label' => '',
                    'key' => '',
                    'placeholder' => '',
                    'required' => false,
                ],
            ],
            [
                'type' => 'textarea',
                'label' => 'Textarea',
                'description' => 'Multi-line text field',
                'group' => 'form',
                'icon' => 'AlignLeft',
                'fields' => [
                    ['key' => 'label', 'label' => 'Label', 'type' => 'text'],
                    ['key' => 'key', 'label' => 'Field Key', 'type' => 'text'],
                    ['key' => 'placeholder', 'label' => 'Placeholder', 'type' => 'text'],
                    ['key' => 'required', 'label' => 'Required', 'type' => 'toggle'],
                ],
                'defaultData' => [
                    'label' => '',
                    'key' => '',
                    'placeholder' => '',
                    'required' => false,
                ],
            ],
            [
                'type' => 'select',
                'label' => 'Select',
                'description' => 'Dropdown with configurable options',
                'group' => 'form',
                'icon' => 'List',
                'fields' => [
                    ['key' => 'label', 'label' => 'Label', 'type' => 'text'],
                    ['key' => 'key', 'label' => 'Field Key', 'type' => 'text'],
                    ['key' => 'placeholder', 'label' => 'Placeholder', 'type' => 'text'],
                    ['key' => 'required', 'label' => 'Required', 'type' => 'toggle'],
                ],
                'defaultData' => [
                    'label' => '',
                    'key' => '',
                    'placeholder' => 'Select an option',
                    'required' => false,
                    'options' => [
                        ['label' => 'Option 1', 'value' => 'option_1'],
                    ],
                ],
            ],
            [
                'type' => 'checkbox',
                'label' => 'Checkboxes',
                'description' => 'Checkbox group with one or more selectable options',
                'group' => 'form',
                'icon' => 'Square',
                'fields' => [
                    ['key' => 'label', 'label' => 'Label', 'type' => 'text'],
                    ['key' => 'key', 'label' => 'Field Key', 'type' => 'text'],
                    ['key' => 'required', 'label' => 'Required', 'type' => 'toggle'],
                ],
                'defaultData' => [
                    'label' => '',
                    'key' => '',
                    'required' => false,
                    'options' => [
                        ['label' => 'Option 1', 'value' => 'option_1'],
                    ],
                ],
            ],
            [
                'type' => 'radio',
                'label' => 'Radio',
                'description' => 'Single-choice option group',
                'group' => 'form',
                'icon' => 'List',
                'fields' => [
                    ['key' => 'label', 'label' => 'Label', 'type' => 'text'],
                    ['key' => 'key', 'label' => 'Field Key', 'type' => 'text'],
                    ['key' => 'required', 'label' => 'Required', 'type' => 'toggle'],
                ],
                'defaultData' => [
                    'label' => '',
                    'key' => '',
                    'required' => false,
                    'options' => [
                        ['label' => 'Option 1', 'value' => 'option_1'],
                    ],
                ],
            ],
            [
                'type' => 'columns',
                'label' => 'Columns',
                'description' => 'Place form fields side-by-side in 2, 3, or 4 columns',
                'group' => 'layout',
                'icon' => 'LayoutGrid',
                'defaultData' => [
                    'count' => '2',
                    'gap' => 'md',
                    'col_0' => [],
                    'col_1' => [],
                ],
            ],
            [
                'type' => 'button',
                'label' => 'Button',
                'description' => 'Submit button for the form',
                'group' => 'form',
                'icon' => 'MousePointer2',
                'fields' => [
                    ['key' => 'label', 'label' => 'Button Label', 'type' => 'text'],
                ],
                'defaultData' => [
                    'label' => 'Submit',
                ],
            ],
        ]);
    }

    public static function availableActions(): array
    {
        return Hook::applyFilters('form_actions', [
            [
                'key' => self::DEFAULT_ACTION,
                'label' => 'Store submission',
                'description' => 'Save submissions in the CMS and optionally send a notification email.',
                'source' => 'core',
                'fields' => [
                    [
                        'key' => 'success_message',
                        'label' => 'Success Message',
                        'type' => 'textarea',
                        'placeholder' => 'Thank you for your submission!',
                    ],
                    [
                        'key' => 'redirect_url',
                        'label' => 'Redirect URL',
                        'type' => 'url',
                        'placeholder' => '/thank-you',
                    ],
                    [
                        'key' => 'notify_email',
                        'label' => 'Notify Email',
                        'type' => 'email',
                        'placeholder' => 'you@example.com',
                    ],
                    [
                        'key' => 'email_template_id',
                        'label' => 'Notification Template',
                        'type' => 'select',
                        'options' => app(EmailTemplateBuilder::class)->templateOptionsFor(EmailTemplateBuilder::FORM_NOTIFICATION_CONTEXT),
                    ],
                ],
                'handler' => function (Form $form, array $data, Request $request, array $config): array {
                    FormSubmission::create([
                        'form_id' => $form->id,
                        'data' => $data,
                        'ip_address' => $request->ip(),
                        'user_agent' => $request->userAgent(),
                    ]);

                    $notifyEmail = trim((string) ($config['notify_email'] ?? ''));

                    if ($notifyEmail !== '') {
                        $mailDelivery = app(KavloMailDelivery::class);
                        $templateBuilder = app(EmailTemplateBuilder::class);
                        $templateRenderer = app(EmailTemplateRenderer::class);
                        $template = $templateBuilder->findTemplateForContext($config['email_template_id'] ?? null, EmailTemplateBuilder::FORM_NOTIFICATION_CONTEXT);

                        if ($template) {
                            $mailDelivery->queue(
                                $notifyEmail,
                                new KavloTemplateMail($templateRenderer->render(
                                    $template,
                                    $templateBuilder->formNotificationData($form, $data, $notifyEmail),
                                )),
                            );
                        } else {
                            $body = "New submission for form: {$form->name}\n\n";

                            foreach (self::submissionFields($form) as $field) {
                                $body .= "{$field['label']}: ".self::formatSubmissionValue($data[$field['key']] ?? null)."\n";
                            }

                            $mailDelivery->queuePlainText($notifyEmail, "New form submission: {$form->name}", $body);
                        }
                    }

                    return [
                        'message' => $config['success_message'] ?? 'Thank you for your submission!',
                    ];
                },
            ],
        ]);
    }

    public static function publicActions(): array
    {
        return array_map(
            fn (array $action) => Arr::except($action, ['handler']),
            self::availableActions(),
        );
    }

    public static function inputTypeOptions(): array
    {
        return [
            'text' => 'Text',
            'email' => 'Email',
            'tel' => 'Phone',
            'number' => 'Number',
            'date' => 'Date',
            'file' => 'File',
        ];
    }

    public static function editorBlocks(Form $form): array
    {
        $blocks = is_array($form->blocks) ? $form->blocks : [];

        if ($blocks !== []) {
            return self::normalizeBlocks($blocks);
        }

        $fields = $form->relationLoaded('fields')
            ? $form->fields
            : $form->fields()->orderBy('sort_order')->get();

        return self::legacyFieldsToBlocks($fields);
    }

    public static function submissionFields(Form $form): array
    {
        $blocks = self::flattenBlocks(self::editorBlocks($form));

        return array_values(array_filter(array_map(
            function (array $block, int $index): ?array {
                $type = $block['type'] ?? '';
                $data = is_array($block['data'] ?? null) ? $block['data'] : [];

                if (in_array($type, ['button', 'columns'], true)) {
                    return null;
                }

                $fieldType = $type === 'input'
                    ? ($data['input_type'] ?? 'text')
                    : $type;

                return [
                    'type' => (string) $fieldType,
                    'label' => trim((string) ($data['label'] ?? '')),
                    'key' => trim((string) ($data['key'] ?? '')),
                    'placeholder' => trim((string) ($data['placeholder'] ?? '')),
                    'required' => (bool) ($data['required'] ?? false),
                    'options' => self::normalizeOptions($data['options'] ?? []),
                    'sort_order' => $index,
                ];
            },
            $blocks,
            array_keys($blocks),
        )));
    }

    public static function fieldCount(Form $form): int
    {
        return count(self::submissionFields($form));
    }

    public static function resolvedAction(Form $form): array
    {
        $actions = collect(self::availableActions())->keyBy('key');
        $selected = trim((string) ($form->submission_action ?: self::DEFAULT_ACTION));

        return $actions->get($selected)
            ?? $actions->get(self::DEFAULT_ACTION)
            ?? $actions->first()
            ?? [];
    }

    public static function resolvedActionKey(Form $form): string
    {
        return (string) (self::resolvedAction($form)['key'] ?? self::DEFAULT_ACTION);
    }

    public static function resolvedActionConfig(Form $form): array
    {
        $config = is_array($form->action_config) ? $form->action_config : [];

        foreach ([
            'success_message' => $form->success_message,
            'redirect_url' => $form->redirect_url,
            'notify_email' => $form->notify_email,
        ] as $key => $value) {
            if (! array_key_exists($key, $config) && filled($value)) {
                $config[$key] = $value;
            }
        }

        if (! array_key_exists('success_message', $config) || blank($config['success_message'])) {
            $config['success_message'] = 'Thank you for your submission!';
        }

        return $config;
    }

    public static function validationRules(Form $form): array
    {
        $rules = [];

        foreach (self::submissionFields($form) as $field) {
            $key = $field['key'];

            if ($key === '') {
                continue;
            }

            $required = $field['required'] ? ['required'] : ['nullable'];

            switch ($field['type']) {
                case 'file':
                    $rules[$key] = array_merge($required, ['file', 'max:5120']);
                    break;

                case 'email':
                    $rules[$key] = array_merge($required, ['email']);
                    break;

                case 'number':
                    $rules[$key] = array_merge($required, ['numeric']);
                    break;

                case 'date':
                    $rules[$key] = array_merge($required, ['date']);
                    break;

                case 'select':
                case 'radio':
                    $rules[$key] = array_merge($required, ['string', Rule::in(self::optionValues($field))]);
                    break;

                case 'checkbox':
                    $rules[$key] = array_merge(
                        $field['required'] ? ['required'] : ['nullable'],
                        ['array'],
                        $field['required'] ? ['min:1'] : [],
                    );
                    $rules["{$key}.*"] = ['string', Rule::in(self::optionValues($field))];
                    break;

                default:
                    $rules[$key] = array_merge($required, ['string', 'max:5000']);
                    break;
            }
        }

        return $rules;
    }

    public static function collectSubmissionData(Form $form, Request $request, array $validated): array
    {
        $data = [];

        foreach (self::submissionFields($form) as $field) {
            $key = $field['key'];

            if ($key === '') {
                continue;
            }

            if ($field['type'] === 'file' && $request->hasFile($key)) {
                $data[$key] = $request->file($key)->store('form-uploads', 'public');

                continue;
            }

            if ($field['type'] === 'checkbox') {
                $data[$key] = array_values($validated[$key] ?? []);

                continue;
            }

            $data[$key] = $validated[$key] ?? null;
        }

        return $data;
    }

    public static function runAction(Form $form, array $data, Request $request): array
    {
        $action = self::resolvedAction($form);
        $handler = $action['handler'] ?? null;

        if (! is_callable($handler)) {
            return [
                'message' => self::resolvedActionConfig($form)['success_message'] ?? 'Thank you for your submission!',
            ];
        }

        return $handler($form, $data, $request, self::resolvedActionConfig($form));
    }

    public static function validateBlocks(array $blocks): array
    {
        $errors = [];
        $allowedTypes = collect(self::availableBlocks())->pluck('type')->all();
        $keys = [];
        $fieldCount = 0;
        $buttonCount = 0;

        foreach (self::flattenBlocks(self::normalizeBlocks($blocks)) as $index => $block) {
            $type = $block['type'] ?? '';
            $data = is_array($block['data'] ?? null) ? $block['data'] : [];
            $position = $index + 1;

            if (! in_array($type, $allowedTypes, true)) {
                $errors[] = "Block {$position} uses unsupported type [{$type}].";

                continue;
            }

            if ($type === 'button') {
                $buttonCount++;

                continue;
            }

            if ($type === 'columns') {
                continue;
            }

            $fieldCount++;

            $label = trim((string) ($data['label'] ?? ''));
            $key = trim((string) ($data['key'] ?? ''));

            if ($label === '') {
                $errors[] = "Block {$position} is missing a label.";
            }

            if ($key === '') {
                $errors[] = "Block {$position} is missing a field key.";
            } elseif (isset($keys[$key])) {
                $errors[] = "Field key [{$key}] is used more than once.";
            } else {
                $keys[$key] = true;
            }

            if ($type === 'input') {
                $inputType = (string) ($data['input_type'] ?? 'text');

                if (! array_key_exists($inputType, self::inputTypeOptions())) {
                    $errors[] = "Block {$position} uses unsupported input type [{$inputType}].";
                }
            }

            if (in_array($type, ['select', 'checkbox', 'radio'], true) && self::normalizeOptions($data['options'] ?? []) === []) {
                $errors[] = "Block {$position} needs at least one option.";
            }
        }

        if ($fieldCount === 0) {
            $errors[] = 'Add at least one field block to the form.';
        }

        if ($buttonCount === 0) {
            $errors[] = 'Add a button block so the form can be submitted.';
        }

        return $errors;
    }

    public static function normalizeBlocks(array $blocks): array
    {
        return array_values(array_map(function (array $block, int $index) {
            $data = is_array($block['data'] ?? null) ? $block['data'] : [];

            foreach (array_keys($data) as $key) {
                if (preg_match('/^col_\d+$/', $key) !== 1 || ! is_array($data[$key])) {
                    continue;
                }

                $data[$key] = self::normalizeBlocks($data[$key]);
            }

            return [
                'id' => (string) ($block['id'] ?? Str::uuid()->toString()),
                'type' => (string) ($block['type'] ?? ''),
                'data' => $data,
                'order' => $index,
            ];
        }, $blocks, array_keys($blocks)));
    }

    public static function legacyFieldsToBlocks(EloquentCollection|array $fields): array
    {
        $blocks = [];
        $items = $fields instanceof EloquentCollection ? $fields->all() : $fields;

        foreach ($items as $index => $field) {
            $type = $field->type ?? 'text';
            $blockType = in_array($type, ['textarea', 'select', 'checkbox', 'radio'], true) ? $type : 'input';
            $data = [
                'label' => $field->label ?? '',
                'key' => $field->key ?? '',
                'placeholder' => $field->placeholder ?? '',
                'required' => (bool) ($field->required ?? false),
            ];

            if ($blockType === 'input') {
                $data['input_type'] = $type;
            }

            if (in_array($blockType, ['select', 'checkbox', 'radio'], true)) {
                $data['options'] = self::normalizeOptions($field->options ?? []);
            }

            $blocks[] = [
                'id' => (string) Str::uuid(),
                'type' => $blockType,
                'data' => $data,
                'order' => $index,
            ];
        }

        $blocks[] = [
            'id' => (string) Str::uuid(),
            'type' => 'button',
            'data' => ['label' => 'Submit'],
            'order' => count($blocks),
        ];

        return $blocks;
    }

    public static function formatSubmissionValue(mixed $value): string
    {
        if (is_array($value)) {
            return implode(', ', array_map(fn ($item) => (string) $item, $value));
        }

        return $value === null ? '' : (string) $value;
    }

    /**
     * @param  array<string, mixed>  $field
     * @return list<string>
     */
    private static function optionValues(array $field): array
    {
        return array_values(array_filter(array_map(
            fn (array $option) => (string) ($option['value'] ?? ''),
            self::normalizeOptions($field['options'] ?? []),
        )));
    }

    /**
     * @return list<array{label: string, value: string}>
     */
    private static function normalizeOptions(mixed $options): array
    {
        if (! is_array($options)) {
            return [];
        }

        return array_values(array_filter(array_map(function (mixed $option) {
            if (! is_array($option)) {
                return null;
            }

            $label = trim((string) ($option['label'] ?? ''));
            $value = trim((string) ($option['value'] ?? ''));

            if ($label === '' && $value === '') {
                return null;
            }

            if ($value === '') {
                $value = Str::snake($label);
            }

            return [
                'label' => $label !== '' ? $label : Str::headline($value),
                'value' => $value,
            ];
        }, $options)));
    }

    private static function flattenBlocks(array $blocks): array
    {
        $flat = [];

        foreach ($blocks as $block) {
            $flat[] = $block;

            if (($block['type'] ?? null) !== 'columns') {
                continue;
            }

            $data = is_array($block['data'] ?? null) ? $block['data'] : [];
            $count = max(2, min(4, (int) ($data['count'] ?? 2)));

            for ($i = 0; $i < $count; $i++) {
                $children = $data["col_{$i}"] ?? [];

                if (! is_array($children)) {
                    continue;
                }

                array_push($flat, ...self::flattenBlocks(self::normalizeBlocks($children)));
            }
        }

        return $flat;
    }
}
