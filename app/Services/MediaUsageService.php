<?php

namespace App\Services;

use App\Facades\Hook;
use App\Models\EmailTemplate;
use App\Models\Form;
use App\Models\Page;
use App\Models\Setting;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

class MediaUsageService
{
    public function forMedia(Media $media): array
    {
        $references = collect()
            ->merge($this->pageReferences($media))
            ->merge($this->formReferences($media))
            ->merge($this->emailTemplateReferences($media))
            ->merge($this->settingReferences($media))
            ->merge($this->pluginReferences($media))
            ->values();

        return [
            'count' => $references->count(),
            'references' => $references->take(8)->all(),
        ];
    }

    private function pluginReferences(Media $media): Collection
    {
        $references = Hook::applyFilters('media.usage.references', [], $media);

        if (! is_array($references)) {
            return collect();
        }

        return collect($references)
            ->filter(fn (mixed $reference) => is_array($reference))
            ->map(fn (array $reference) => [
                'type' => (string) ($reference['type'] ?? 'plugin'),
                'label' => (string) ($reference['label'] ?? ''),
                'href' => (string) ($reference['href'] ?? ''),
                'context' => (string) ($reference['context'] ?? ''),
            ])
            ->filter(fn (array $reference) => $reference['label'] !== '' && $reference['href'] !== '' && $reference['context'] !== '');
    }

    private function pageReferences(Media $media): Collection
    {
        return $this->pageQuery($media)
            ->limit(8)
            ->get()
            ->map(fn (Page $page) => [
                'type' => 'page',
                'label' => $page->title,
                'href' => route('admin.pages.edit', $page),
                'context' => $this->pageContext($page, $media),
            ]);
    }

    private function formReferences(Media $media): Collection
    {
        return $this->matchColumns(
            Form::query(),
            ['blocks'],
            $this->needles($media),
        )->limit(8)
            ->get()
            ->map(fn (Form $form) => [
                'type' => 'form',
                'label' => $form->name,
                'href' => route('admin.forms.edit', $form),
                'context' => 'Form builder blocks',
            ]);
    }

    private function emailTemplateReferences(Media $media): Collection
    {
        return $this->matchColumns(
            EmailTemplate::query(),
            ['blocks'],
            $this->needles($media),
        )->limit(8)
            ->get()
            ->map(fn (EmailTemplate $template) => [
                'type' => 'email_template',
                'label' => $template->name,
                'href' => route('admin.email-templates.edit', $template),
                'context' => 'Email template blocks',
            ]);
    }

    private function settingReferences(Media $media): Collection
    {
        return $this->matchColumns(
            Setting::query(),
            ['value'],
            $this->needles($media),
        )->limit(4)
            ->get()
            ->map(fn (Setting $setting) => [
                'type' => 'setting',
                'label' => $setting->key,
                'href' => route('admin.settings.index'),
                'context' => 'Site setting',
            ]);
    }

    private function pageQuery(Media $media): Builder
    {
        return $this->matchColumns(
            Page::query(),
            ['blocks', 'content', 'og_image'],
            $this->needles($media),
        );
    }

    private function matchColumns(Builder $query, array $columns, array $needles): Builder
    {
        return $query->where(function (Builder $builder) use ($columns, $needles) {
            foreach ($columns as $column) {
                foreach ($needles as $needle) {
                    $builder->orWhere($column, 'like', '%'.$needle.'%');
                }
            }
        });
    }

    private function needles(Media $media): array
    {
        return collect([
            $media->getUrl(),
            parse_url($media->getUrl(), PHP_URL_PATH),
            $media->file_name,
            $media->uuid,
        ])->filter()->unique()->values()->all();
    }

    private function pageContext(Page $page, Media $media): string
    {
        $needles = $this->needles($media);

        foreach ($needles as $needle) {
            if ($page->og_image && str_contains((string) $page->og_image, $needle)) {
                return 'Open Graph image';
            }

            if ($page->content && str_contains((string) $page->content, $needle)) {
                return 'Legacy page content';
            }

            if ($page->blocks && str_contains(json_encode($page->blocks) ?: '', $needle)) {
                return 'Page builder blocks';
            }
        }

        return 'Page content';
    }
}
