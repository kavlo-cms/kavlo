<?php

namespace App\Services;

use App\Models\EmailTemplate;
use App\Models\Form;
use App\Models\Menu;
use App\Models\Page;
use App\Models\Redirect;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

class SearchService
{
    public function publicResults(?string $query, int $limit = 20): array
    {
        $term = $this->normalizeQuery($query);

        if ($term === null) {
            return [];
        }

        return $this->pageQuery($term, publishedOnly: true)
            ->limit($limit)
            ->get()
            ->map(fn (Page $page) => [
                'id' => $page->id,
                'title' => $page->title,
                'path' => '/'.ltrim($page->slug, '/'),
                'excerpt' => $this->pageExcerpt($page, $term),
                'type' => $page->type ?: 'page',
            ])
            ->all();
    }

    public function adminResults(?string $query, int $limitPerGroup = 8): array
    {
        $term = $this->normalizeQuery($query);

        if ($term === null) {
            return [
                'pages' => [],
                'forms' => [],
                'menus' => [],
                'emailTemplates' => [],
                'redirects' => [],
            ];
        }

        return [
            'pages' => $this->pageQuery($term, publishedOnly: false)
                ->limit($limitPerGroup)
                ->get()
                ->map(fn (Page $page) => [
                    'id' => $page->id,
                    'title' => $page->title,
                    'href' => route('admin.pages.edit', $page),
                    'meta' => $page->slug,
                    'status' => $page->is_published ? 'Published' : 'Draft',
                    'excerpt' => $this->pageExcerpt($page, $term),
                ])
                ->all(),
            'forms' => Form::query()
                ->where(function ($builder) use ($term) {
                    $like = "%{$term}%";

                    $builder->where('name', 'like', $like)
                        ->orWhere('slug', 'like', $like)
                        ->orWhere('description', 'like', $like)
                        ->orWhere('blocks', 'like', $like);
                })
                ->orderBy('name')
                ->limit($limitPerGroup)
                ->get()
                ->map(fn (Form $form) => [
                    'id' => $form->id,
                    'title' => $form->name,
                    'href' => route('admin.forms.edit', $form),
                    'meta' => $form->slug,
                    'excerpt' => Str::limit((string) ($form->description ?: $this->flattenBlocks($form->editorBlocks())), 140),
                ])
                ->all(),
            'menus' => Menu::query()
                ->where(function ($builder) use ($term) {
                    $like = "%{$term}%";

                    $builder->where('name', 'like', $like)
                        ->orWhere('slug', 'like', $like);
                })
                ->orderBy('name')
                ->limit($limitPerGroup)
                ->get()
                ->map(fn (Menu $menu) => [
                    'id' => $menu->id,
                    'title' => $menu->name,
                    'href' => route('admin.menus.edit', $menu),
                    'meta' => $menu->slug,
                    'excerpt' => 'Navigation menu',
                ])
                ->all(),
            'emailTemplates' => EmailTemplate::query()
                ->where(function ($builder) use ($term) {
                    $like = "%{$term}%";

                    $builder->where('name', 'like', $like)
                        ->orWhere('slug', 'like', $like)
                        ->orWhere('description', 'like', $like)
                        ->orWhere('subject', 'like', $like)
                        ->orWhere('blocks', 'like', $like);
                })
                ->orderBy('name')
                ->limit($limitPerGroup)
                ->get()
                ->map(fn (EmailTemplate $template) => [
                    'id' => $template->id,
                    'title' => $template->name,
                    'href' => route('admin.email-templates.edit', $template),
                    'meta' => $template->slug,
                    'excerpt' => Str::limit((string) ($template->subject ?: $template->description ?: $this->flattenBlocks($template->editorBlocks())), 140),
                ])
                ->all(),
            'redirects' => Redirect::query()
                ->where(function ($builder) use ($term) {
                    $like = "%{$term}%";

                    $builder->where('from_url', 'like', $like)
                        ->orWhere('to_url', 'like', $like);
                })
                ->orderBy('from_url')
                ->limit($limitPerGroup)
                ->get()
                ->map(fn (Redirect $redirect) => [
                    'id' => $redirect->id,
                    'title' => $redirect->from_url,
                    'href' => route('admin.redirects.index'),
                    'meta' => $redirect->is_active ? 'Active redirect' : 'Inactive redirect',
                    'excerpt' => "{$redirect->from_url} → {$redirect->to_url}",
                ])
                ->all(),
        ];
    }

    private function pageQuery(string $term, bool $publishedOnly)
    {
        $like = "%{$term}%";

        return Page::query()
            ->when($publishedOnly, fn ($builder) => $builder->where('is_published', true))
            ->where(function ($builder) use ($like) {
                $builder->where('title', 'like', $like)
                    ->orWhere('slug', 'like', $like)
                    ->orWhere('meta_title', 'like', $like)
                    ->orWhere('meta_description', 'like', $like)
                    ->orWhere('content', 'like', $like)
                    ->orWhere('blocks', 'like', $like);
            })
            ->orderByDesc('is_published')
            ->orderBy('title');
    }

    private function pageExcerpt(Page $page, string $term): string
    {
        $parts = collect([
            $page->meta_description,
            $page->content ? strip_tags((string) $page->content) : null,
            $this->flattenBlocks($page->blocks ?? []),
        ])->filter()->map(fn ($value) => Str::of((string) $value)->squish()->value());

        $text = $parts->first(fn ($value) => Str::contains(Str::lower($value), Str::lower($term)))
            ?? $parts->first()
            ?? $page->title;

        return Str::limit($text, 180);
    }

    private function flattenBlocks(array $blocks): string
    {
        return collect($blocks)
            ->flatMap(function (array $block) {
                return $this->flattenBlockData($block['data'] ?? []);
            })
            ->filter()
            ->implode(' ');
    }

    private function flattenBlockData(mixed $value): Collection
    {
        if (is_string($value) || is_numeric($value)) {
            return collect([(string) $value]);
        }

        if (! is_array($value)) {
            return collect();
        }

        return collect($value)->flatMap(fn ($nested) => $this->flattenBlockData($nested));
    }

    private function normalizeQuery(?string $query): ?string
    {
        $term = Str::of((string) $query)->squish()->trim()->value();

        return strlen($term) >= 2 ? $term : null;
    }
}
