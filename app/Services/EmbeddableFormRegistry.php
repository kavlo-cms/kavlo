<?php

namespace App\Services;

use App\Facades\Hook;
use App\Models\Form;

class EmbeddableFormRegistry
{
    public static function editorOptions(): array
    {
        $databaseForms = Form::query()
            ->select('name', 'slug')
            ->orderBy('name')
            ->get()
            ->map(fn (Form $form) => [
                'value' => $form->slug,
                'label' => $form->name ?: $form->slug,
                'source' => 'forms',
            ])
            ->all();

        $registeredForms = collect(self::registeredForms())
            ->map(fn (array $form) => [
                'value' => $form['key'],
                'label' => $form['label'],
                'source' => $form['source'],
            ])
            ->all();

        return [...$databaseForms, ...$registeredForms];
    }

    public static function editorPreviews(): array
    {
        $databaseForms = Form::query()
            ->with(['fields' => fn ($query) => $query->orderBy('sort_order')])
            ->select('id', 'name', 'slug', 'blocks')
            ->orderBy('name')
            ->get()
            ->map(fn (Form $form) => [
                'value' => $form->slug,
                'label' => $form->name ?: $form->slug,
                'source' => 'forms',
                'blocks' => FormBuilder::editorBlocks($form),
            ])
            ->all();

        $registeredForms = collect(self::registeredForms())
            ->map(fn (array $form) => [
                'value' => $form['key'],
                'label' => $form['label'],
                'source' => $form['source'],
                'blocks' => is_array($form['preview_blocks'] ?? null) ? $form['preview_blocks'] : [],
                'preview_html' => $form['preview_html'] ?? null,
            ])
            ->all();

        return [...$databaseForms, ...$registeredForms];
    }

    public static function decorateAvailableBlocks(array $blocks): array
    {
        $options = array_map(function (array $option) {
            $label = $option['label'];

            if (($option['source'] ?? 'forms') !== 'forms') {
                $label .= ' (' . ucfirst((string) $option['source']) . ')';
            }

            return [
                'label' => $label,
                'value' => $option['value'],
            ];
        }, self::editorOptions());

        array_unshift($options, [
            'label' => '— Select a form —',
            'value' => '',
        ]);

        return array_map(function (array $block) use ($options) {
            if (($block['type'] ?? null) !== 'form') {
                return $block;
            }

            $fields = array_map(function (array $field) use ($options) {
                if (($field['key'] ?? null) !== 'form_slug') {
                    return $field;
                }

                $field['type'] = 'select';
                $field['options'] = $options;

                return $field;
            }, $block['fields'] ?? []);

            $block['fields'] = $fields;

            return $block;
        }, $blocks);
    }

    public static function resolve(string $reference): array|null
    {
        $reference = trim($reference);

        if ($reference === '') {
            return null;
        }

        $form = Form::query()->where('slug', $reference)->first();

        if ($form) {
            return [
                'type' => 'database',
                'source' => 'forms',
                'key' => $form->slug,
                'label' => $form->name ?: $form->slug,
                'form' => $form,
            ];
        }

        foreach (self::registeredForms() as $registeredForm) {
            if ($registeredForm['key'] !== $reference) {
                continue;
            }

            return [
                'type' => 'registered',
                ...$registeredForm,
            ];
        }

        return null;
    }

    public static function renderRegistered(array $form, array $blockData = []): string|null
    {
        if (($form['type'] ?? null) !== 'registered') {
            return null;
        }

        $payload = [
            'registration' => $form,
            'block' => $blockData,
        ];

        if (isset($form['render']) && is_callable($form['render'])) {
            return (string) call_user_func($form['render'], $payload);
        }

        if (! empty($form['view']) && view()->exists($form['view'])) {
            return view($form['view'], $payload)->render();
        }

        return null;
    }

    public static function registeredForms(): array
    {
        $forms = Hook::applyFilters('embeddable_forms', []);

        if (! is_array($forms)) {
            return [];
        }

        return array_values(array_filter(array_map(function (mixed $form) {
            if (! is_array($form)) {
                return null;
            }

            $key = trim((string) ($form['key'] ?? $form['slug'] ?? ''));
            $label = trim((string) ($form['label'] ?? $form['name'] ?? ''));

            if ($key === '' || $label === '') {
                return null;
            }

            return [
                'key' => $key,
                'label' => $label,
                'source' => (string) ($form['source'] ?? 'plugin'),
                'description' => (string) ($form['description'] ?? ''),
                'view' => $form['view'] ?? null,
                'render' => $form['render'] ?? null,
                'preview_blocks' => $form['preview_blocks'] ?? null,
                'preview_html' => $form['preview_html'] ?? null,
            ];
        }, $forms)));
    }
}
