<?php

namespace App\Services;

use App\Models\Form;
use App\Models\Menu;
use App\Models\Page;
use App\Models\Setting;
use Illuminate\Support\Facades\Cache;

class ContentRouteRegistry
{
    public const CACHE_KEY = 'cms.content_routes.v1';

    public function manifest(): array
    {
        return Cache::rememberForever(self::CACHE_KEY, fn () => $this->buildManifest());
    }

    public function refresh(): array
    {
        Cache::forget(self::CACHE_KEY);

        return $this->manifest();
    }

    public function forget(): void
    {
        Cache::forget(self::CACHE_KEY);
    }

    public function routes(array $types = [], bool $preview = false): array
    {
        $normalizedTypes = array_map(
            static fn (string $type) => strtolower(trim($type)),
            array_filter($types),
        );

        return array_values(array_filter($this->manifest()['routes'], function (array $route) use ($normalizedTypes, $preview) {
            if ($normalizedTypes !== [] && ! in_array($route['type'], $normalizedTypes, true)) {
                return false;
            }

            if (! $preview && $route['type'] === 'page' && ! $route['published']) {
                return false;
            }

            return true;
        }));
    }

    public function route(string $path, bool $preview = false): ?array
    {
        $normalizedPath = $this->normalizePath($path);
        $manifest = $this->manifest();

        if (isset($manifest['page_paths'][$normalizedPath])) {
            $pageRoute = $manifest['page_paths'][$normalizedPath];

            if ($preview || $pageRoute['published']) {
                return $pageRoute;
            }
        }

        return $manifest['form_paths'][$normalizedPath] ?? null;
    }

    public function resolvePage(string $path = '/', bool $preview = false): ?Page
    {
        $route = $this->route($path, $preview);

        if (! $route || $route['type'] !== 'page') {
            return null;
        }

        $query = Page::query()->whereKey($route['id']);

        if (! $preview) {
            $query->where('is_published', true);
        }

        return $query->first();
    }

    public function resolveMenu(?string $slug): ?Menu
    {
        $manifest = $this->manifest();
        $slug = trim((string) $slug);

        $entry = $slug !== '' ? ($manifest['menus'][$slug] ?? null) : null;

        if (! $entry) {
            $firstSlug = array_key_first($manifest['menus']);
            $entry = $firstSlug ? $manifest['menus'][$firstSlug] : null;
        }

        if (! $entry) {
            return null;
        }

        return Menu::query()
            ->with($this->menuRelations())
            ->whereKey($entry['id'])
            ->first();
    }

    public function resolveForm(string $slug): ?Form
    {
        $entry = $this->manifest()['forms'][trim($slug)] ?? null;

        if (! $entry) {
            return null;
        }

        return Form::query()->whereKey($entry['id'])->first();
    }

    public function publishedPageUrls(): array
    {
        return array_values(array_map(function (array $route) {
            return [
                'loc' => url($route['path'] === '/' ? '' : ltrim($route['path'], '/')),
                'lastmod' => $route['updated_at'],
                'priority' => $route['path'] === '/' ? '1.0' : '0.8',
                'changefreq' => 'weekly',
            ];
        }, array_filter($this->manifest()['page_paths'], static function (array $route) {
            return $route['published'] && ! $route['is_alias'];
        })));
    }

    public function pagePayload(Page $page): array
    {
        return [
            'id' => (string) $page->id,
            'title' => $page->title,
            'slug' => $page->slug,
            'path' => $this->normalizePath($page->slug),
            'type' => $page->type,
            'isPublished' => (bool) $page->is_published,
            'isHomepage' => (bool) $page->is_homepage,
            'metaTitle' => $page->meta_title,
            'metaDescription' => $page->meta_description,
            'ogImage' => $page->og_image,
            'metadataJson' => json_encode($page->metadata ?? [], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?: '{}',
            'blocks' => $this->blocksPayload($page->blocks ?? []),
            'updatedAt' => $page->updated_at?->toAtomString(),
            'publishedAt' => $page->published_at?->toAtomString(),
        ];
    }

    public function menuPayload(Menu $menu): array
    {
        return [
            'id' => (string) $menu->id,
            'name' => $menu->name,
            'slug' => $menu->slug,
            'items' => $menu->items->map(fn ($item) => $this->menuItemPayload($item))->values()->all(),
            'updatedAt' => $menu->updated_at?->toAtomString(),
        ];
    }

    public function formPayload(Form $form): array
    {
        return [
            'id' => (string) $form->id,
            'name' => $form->name,
            'slug' => $form->slug,
            'description' => $form->description,
            'submissionAction' => $form->resolvedSubmissionAction(),
            'successMessage' => $form->success_message,
            'redirectUrl' => $form->redirect_url,
            'notifyEmail' => $form->notify_email,
            'submissionPath' => route('forms.submit', ['form' => $form->slug], false),
            'fields' => array_map(function (array $field) {
                return [
                    'key' => (string) ($field['key'] ?? ''),
                    'label' => (string) ($field['label'] ?? ''),
                    'type' => (string) ($field['type'] ?? 'text'),
                    'required' => (bool) ($field['required'] ?? false),
                    'placeholder' => $field['placeholder'] ?? null,
                    'optionsJson' => json_encode($field['options'] ?? [], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?: '[]',
                ];
            }, $form->submissionFields()),
            'blocks' => $this->blocksPayload($form->editorBlocks()),
            'updatedAt' => $form->updated_at?->toAtomString(),
        ];
    }

    protected function buildManifest(): array
    {
        $pages = Page::query()
            ->select('id', 'title', 'slug', 'type', 'is_published', 'is_homepage', 'updated_at')
            ->orderBy('slug')
            ->get();

        $menus = Menu::query()
            ->select('id', 'name', 'slug', 'updated_at')
            ->orderBy('id')
            ->get();

        $forms = Form::query()
            ->select('id', 'name', 'slug', 'updated_at')
            ->orderBy('name')
            ->get();

        $homepageId = Setting::get('homepage_id');
        $homepage = $homepageId ? $pages->firstWhere('id', (int) $homepageId) : null;
        $homepage ??= $pages->firstWhere('is_homepage', true);

        $routes = [];
        $pagePaths = [];

        foreach ($pages as $page) {
            $path = $this->normalizePath($page->slug);
            $route = [
                'id' => (string) $page->id,
                'type' => 'page',
                'key' => $path,
                'slug' => $page->slug,
                'label' => $page->title,
                'path' => $path,
                'published' => (bool) $page->is_published,
                'updated_at' => $page->updated_at?->toAtomString(),
                'is_alias' => false,
            ];

            $routes[] = $route;
            $pagePaths[$path] = $route;
        }

        if ($homepage) {
            $rootRoute = [
                'id' => (string) $homepage->id,
                'type' => 'page',
                'key' => '/',
                'slug' => $homepage->slug,
                'label' => $homepage->title,
                'path' => '/',
                'published' => (bool) $homepage->is_published,
                'updated_at' => $homepage->updated_at?->toAtomString(),
                'is_alias' => $this->normalizePath($homepage->slug) !== '/',
            ];

            $routes[] = $rootRoute;
            $pagePaths['/'] = $rootRoute;
        }

        $formEntries = [];
        $formPaths = [];

        foreach ($forms as $form) {
            $path = route('forms.submit', ['form' => $form->slug], false);
            $entry = [
                'id' => (string) $form->id,
                'type' => 'form',
                'key' => $form->slug,
                'slug' => $form->slug,
                'label' => $form->name ?: $form->slug,
                'path' => $path,
                'published' => true,
                'updated_at' => $form->updated_at?->toAtomString(),
                'is_alias' => false,
            ];

            $routes[] = $entry;
            $formEntries[$form->slug] = $entry;
            $formPaths[$path] = $entry;
        }

        $menuEntries = [];

        foreach ($menus as $menu) {
            $entry = [
                'id' => (string) $menu->id,
                'type' => 'menu',
                'key' => $menu->slug,
                'slug' => $menu->slug,
                'label' => $menu->name ?: $menu->slug,
                'path' => null,
                'published' => true,
                'updated_at' => $menu->updated_at?->toAtomString(),
                'is_alias' => false,
            ];

            $routes[] = $entry;
            $menuEntries[$menu->slug] = $entry;
        }

        return [
            'generated_at' => now()->toAtomString(),
            'routes' => $routes,
            'page_paths' => $pagePaths,
            'forms' => $formEntries,
            'form_paths' => $formPaths,
            'menus' => $menuEntries,
        ];
    }

    protected function blocksPayload(array $blocks): array
    {
        return array_map(function (array $block) {
            return [
                'id' => (string) ($block['id'] ?? ''),
                'type' => (string) ($block['type'] ?? ''),
                'order' => isset($block['order']) ? (int) $block['order'] : null,
                'dataJson' => json_encode($block['data'] ?? [], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?: '{}',
            ];
        }, $blocks);
    }

    protected function menuItemPayload($item): array
    {
        return [
            'id' => (string) $item->id,
            'label' => $item->label,
            'url' => $item->page_id
                ? ($item->page?->is_homepage ? url('/') : url($item->page?->slug ?? '/'))
                : $item->url,
            'target' => $item->target,
            'pageId' => $item->page_id ? (string) $item->page_id : null,
            'pageSlug' => $item->page?->slug,
            'children' => $item->children->map(fn ($child) => $this->menuItemPayload($child))->values()->all(),
        ];
    }

    protected function menuRelations(): array
    {
        return [
            'items' => fn ($query) => $query->whereNull('parent_id')->orderBy('order'),
            'items.children' => fn ($query) => $query->orderBy('order'),
            'items.children.children' => fn ($query) => $query->orderBy('order'),
            'items.page',
            'items.children.page',
            'items.children.children.page',
        ];
    }

    protected function normalizePath(?string $path): string
    {
        $trimmed = trim((string) $path);

        if ($trimmed === '' || $trimmed === '/') {
            return '/';
        }

        return '/' . trim($trimmed, '/');
    }
}
