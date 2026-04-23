<?php

namespace App\Services;

use App\Models\Form;
use App\Models\Menu;
use App\Models\MenuItem;
use App\Models\Page;
use App\Models\PageTranslation;
use App\Models\Setting;
use Illuminate\Support\Facades\Cache;

class ContentRouteRegistry
{
    public const CACHE_KEY = 'cms.content_routes.v2';

    public function __construct(
        private readonly SiteLocaleManager $locales,
    ) {}

    public function manifest(): array
    {
        return Cache::rememberForever(self::CACHE_KEY, fn () => $this->buildManifest());
    }

    public function refresh(): array
    {
        $this->forget();

        return $this->manifest();
    }

    public function forget(): void
    {
        Cache::forget(self::CACHE_KEY);
        app(MenuRenderCache::class)->flush();
        app(PublicPageCache::class)->flush();
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

        $page = Page::query()
            ->with('translations')
            ->whereKey($route['id'])
            ->first();

        if (! $page) {
            return null;
        }

        return $page->applyLocale($route['locale']);
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

    public function pagePayload(Page $page, ?string $locale = null): array
    {
        $data = $this->localizedPageData($page, $locale);

        return [
            'id' => (string) $page->id,
            'title' => $data['title'],
            'slug' => $data['slug'],
            'path' => $data['path'],
            'type' => $page->type,
            'locale' => $data['locale'],
            'isPublished' => $data['is_published'],
            'isHomepage' => (bool) $page->is_homepage,
            'metaTitle' => $data['meta_title'],
            'metaDescription' => $data['meta_description'],
            'ogImage' => $data['og_image'],
            'metadataJson' => json_encode($data['metadata'] ?? [], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?: '{}',
            'blocks' => $this->blocksPayload($data['blocks'] ?? []),
            'updatedAt' => $data['updated_at'],
            'publishedAt' => $data['published_at'],
        ];
    }

    public function menuPayload(Menu $menu): array
    {
        $locale = $this->locales->currentLocale();

        return [
            'id' => (string) $menu->id,
            'name' => $menu->name,
            'slug' => $menu->slug,
            'items' => $menu->items->map(fn ($item) => $this->menuItemPayload($item, $locale))->values()->all(),
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
        $activeLocales = $this->locales->activeLanguages();
        $defaultLocale = $this->locales->defaultLocale();
        $localeCodes = $activeLocales->pluck('code')->all();

        $pages = Page::query()
            ->with(['translations' => fn ($query) => $query->whereIn('locale', $localeCodes)])
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
            foreach ($activeLocales as $language) {
                $translation = $page->translationFor($language->code);

                if ($language->code !== $defaultLocale && ! $translation) {
                    continue;
                }

                $route = $this->pageRouteEntry($page, $translation, $language->code);

                $routes[] = $route;
                $pagePaths[$route['path']] = $route;

                if ($homepage && $homepage->id === $page->id) {
                    $aliasPath = $this->locales->pathForLocale('/', $language->code, true);
                    $aliasRoute = [
                        ...$route,
                        'key' => $aliasPath,
                        'path' => $aliasPath,
                        'is_alias' => $route['path'] !== $aliasPath,
                    ];

                    $routes[] = $aliasRoute;
                    $pagePaths[$aliasPath] = $aliasRoute;
                }
            }
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

    protected function menuItemPayload(MenuItem $item, ?string $locale = null): array
    {
        $translatedSlug = $item->page?->translationFor($locale)?->slug ?? $item->page?->slug;

        return [
            'id' => (string) $item->id,
            'label' => $item->label,
            'url' => $item->page_id
                ? url($item->page?->localizedPath($locale) ?? '/')
                : $item->url,
            'target' => $item->target,
            'pageId' => $item->page_id ? (string) $item->page_id : null,
            'pageSlug' => $translatedSlug,
            'children' => $item->children->map(fn ($child) => $this->menuItemPayload($child, $locale))->values()->all(),
        ];
    }

    protected function menuRelations(): array
    {
        return [
            'items' => fn ($query) => $query->whereNull('parent_id')->orderBy('order'),
            'items.children' => fn ($query) => $query->orderBy('order'),
            'items.children.children' => fn ($query) => $query->orderBy('order'),
            'items.page.translations',
            'items.children.page.translations',
            'items.children.children.page.translations',
        ];
    }

    protected function normalizePath(?string $path): string
    {
        $trimmed = trim((string) $path);

        if ($trimmed === '' || $trimmed === '/') {
            return '/';
        }

        return '/'.trim($trimmed, '/');
    }

    private function pageRouteEntry(Page $page, ?PageTranslation $translation, string $locale): array
    {
        $slug = $translation?->slug ?? $page->slug;
        $title = $translation?->title ?? $page->title;
        $published = (bool) ($translation?->is_published ?? $page->is_published);
        $updatedAt = $translation?->updated_at?->toAtomString() ?? $page->updated_at?->toAtomString();

        return [
            'id' => (string) $page->id,
            'type' => 'page',
            'key' => $this->locales->pathForLocale($slug, $locale),
            'slug' => $slug,
            'label' => $title,
            'path' => $this->locales->pathForLocale($slug, $locale),
            'locale' => $locale,
            'published' => $published,
            'updated_at' => $updatedAt,
            'is_alias' => false,
        ];
    }

    private function localizedPageData(Page $page, ?string $locale = null): array
    {
        $locale ??= $this->locales->currentLocale();
        $translation = $page->translationFor($locale);
        $slug = $translation?->slug ?? $page->slug;

        return [
            'locale' => $locale,
            'title' => $translation?->title ?? $page->title,
            'slug' => $slug,
            'path' => $this->locales->pathForLocale($slug, $locale),
            'is_published' => (bool) ($translation?->is_published ?? $page->is_published),
            'meta_title' => $translation?->meta_title ?? $page->meta_title,
            'meta_description' => $translation?->meta_description ?? $page->meta_description,
            'og_image' => $translation?->og_image ?? $page->og_image,
            'metadata' => $translation?->metadata ?? $page->metadata ?? [],
            'blocks' => $translation?->blocks ?? $page->blocks ?? [],
            'updated_at' => $translation?->updated_at?->toAtomString() ?? $page->updated_at?->toAtomString(),
            'published_at' => ($translation?->published_at ?? $page->published_at)?->toAtomString(),
        ];
    }
}
