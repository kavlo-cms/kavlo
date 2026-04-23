<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Menu;
use App\Models\MenuItem;
use App\Models\Page;
use App\Models\PageTranslation;
use App\Models\Redirect;
use App\Models\Revision;
use App\Models\Theme;
use App\Services\BlockManager;
use App\Services\BuilderBlockPayload;
use App\Services\ContentRouteRegistry;
use App\Services\EmbeddableFormRegistry;
use App\Services\MenuRenderCache;
use App\Services\PageContentRenderer;
use App\Services\PageTypeManager;
use App\Services\SiteLocaleManager;
use App\Services\ThemeManifest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response as HttpResponse;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use Illuminate\View\View;
use Inertia\Inertia;
use Inertia\Response;

class PageController extends Controller
{
    public function __construct(
        private readonly BuilderBlockPayload $blockPayload,
    ) {}

    public function index(): Response
    {
        $this->authorize('view pages');

        $pages = Page::select('id', 'title', 'slug', 'type', 'is_published', 'is_homepage', 'parent_id', 'order', 'published_at', 'created_at')
            ->orderBy('order')
            ->orderBy('id')
            ->get();

        return Inertia::render('Pages/Index', [
            'pages' => $pages,
        ]);
    }

    public function create(): Response
    {
        $this->authorize('create pages');

        $pages = Page::select('id', 'title')->orderBy('title')->get();

        return Inertia::render('Pages/Create', [
            'pages' => $pages,
            'pageTypes' => PageTypeManager::all(),
        ]);
    }

    public function quickCreate(): RedirectResponse
    {
        $this->authorize('create pages');

        $page = Page::create(Page::sanitizePersistedAttributes([
            'title' => 'Untitled page',
            'slug' => $this->nextAvailableSlug('untitled-page'),
            'type' => $this->defaultPageType(),
            'editor_mode' => 'builder',
            'is_published' => false,
            'is_homepage' => false,
            'blocks' => [],
            'metadata' => [],
            'author_id' => auth()->id(),
            'parent_id' => null,
            'order' => (Page::whereNull('parent_id')->max('order') ?? -1) + 1,
        ]));

        app(ContentRouteRegistry::class)->forget();

        return redirect()->route('admin.pages.edit', $page)
            ->with('success', 'Draft page created.');
    }

    public function store(Request $request): RedirectResponse
    {
        $this->authorize('create pages');

        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'slug' => 'nullable|string|max:255',
            'type' => 'nullable|string|max:64',
            'editor_mode' => 'nullable|in:builder,content',
            'content' => 'nullable|string',
            'is_published' => 'boolean',
            'is_homepage' => 'boolean',
            'parent_id' => 'nullable|integer|exists:pages,id',
            'meta_title' => 'nullable|string|max:255',
            'meta_description' => 'nullable|string|max:500',
            'og_image' => 'nullable|string|max:2048',
            'publish_at' => 'nullable|date',
            'unpublish_at' => 'nullable|date',
        ]);

        $this->authorizePublishChange($validated);

        $defaultLocale = app(SiteLocaleManager::class)->defaultLocale();
        $fullSlug = $this->buildSlugForLocale(
            $validated['slug'] ?? null,
            $validated['title'],
            $validated['parent_id'] ?? null,
            $defaultLocale,
        );

        if (PageTranslation::query()->where('locale', $defaultLocale)->where('slug', $fullSlug)->exists()) {
            return back()->withErrors(['slug' => 'A page with this URL already exists.'])->withInput();
        }

        $validated['slug'] = $fullSlug;
        $validated['editor_mode'] = $validated['editor_mode'] ?? 'builder';

        if ($validated['is_homepage'] ?? false) {
            Page::where('is_homepage', true)->update(['is_homepage' => false]);
        }

        if ($validated['is_published'] ?? false) {
            $validated['published_at'] = now();
        }

        $page = Page::create(Page::sanitizePersistedAttributes($validated));
        app(ContentRouteRegistry::class)->forget();

        return redirect()->route('admin.pages.edit', $page)
            ->with('success', 'Page created.');
    }

    public function edit(Request $request, Page $page): Response
    {
        $this->authorize('edit pages');

        $locale = $this->selectedLocale($request);

        $page->load('translations');

        $pages = Page::where('id', '!=', $page->id)
            ->select('id', 'title', 'slug')
            ->orderBy('title')
            ->get();

        $theme = Theme::where('is_active', true)->value('slug') ?? Theme::DEFAULT_THEME_SLUG;
        $availableBlocks = EmbeddableFormRegistry::decorateAvailableBlocks(BlockManager::getAvailableBlocks($theme));
        $themeConfig = $this->readThemeConfig($theme);

        return Inertia::render('Pages/Edit', [
            'page' => $this->editorPayload($page, $locale),
            'pages' => $pages,
            'revisions' => $this->isDefaultLocale($locale) ? $this->revisionPayload($page) : [],
            'availableForms' => EmbeddableFormRegistry::editorOptions(),
            'availableFormPreviews' => EmbeddableFormRegistry::editorPreviews(),
            'availableBlocks' => $availableBlocks,
            'contentContext' => app(PageContentRenderer::class)->publicContext(),
            'previewUrl' => route('admin.pages.preview', $page),
            'themeConfig' => $themeConfig,
            'pageTypes' => PageTypeManager::all(),
            'locales' => $this->availableLocalePayload($page),
            'selectedLocale' => $locale,
        ]);
    }

    private function readThemeConfig(string $slug): array
    {
        $path = base_path("themes/{$slug}/theme.json");

        return app(ThemeManifest::class)->loadFromPath($path);
    }

    public function update(Request $request, Page $page): RedirectResponse
    {
        $this->authorize('edit pages');
        $locale = $this->selectedLocale($request);
        $defaultLocale = app(SiteLocaleManager::class)->defaultLocale();

        $page->load('translations');
        $translation = $page->translationFor($locale);

        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'slug' => 'nullable|string|max:255',
            'type' => 'nullable|string|max:64',
            'editor_mode' => 'nullable|in:builder,content',
            'content' => 'nullable|string',
            'is_published' => 'boolean',
            'is_homepage' => 'boolean',
            'parent_id' => 'nullable|integer|exists:pages,id',
            'blocks' => 'nullable|array',
            'blocks.*.id' => 'required|string',
            'blocks.*.type' => 'required|string',
            'blocks.*.data' => 'nullable|array',
            'blocks.*.order' => 'nullable|integer',
            'metadata' => 'nullable|array',
            'create_redirect' => 'boolean',
            'meta_title' => 'nullable|string|max:255',
            'meta_description' => 'nullable|string|max:500',
            'og_image' => 'nullable|string|max:2048',
            'publish_at' => 'nullable|date',
            'unpublish_at' => 'nullable|date',
        ]);

        $this->authorizePublishChange($validated, $page, $translation);

        $pageBlockErrors = $this->validatePageBlocks($validated['blocks'] ?? []);

        if ($pageBlockErrors !== []) {
            return back()->withErrors(['blocks' => $pageBlockErrors[0]])->withInput();
        }

        $fullSlug = $this->buildSlugForLocale(
            $validated['slug'] ?? null,
            $validated['title'],
            $validated['parent_id'] ?? null,
            $locale,
        );

        if (PageTranslation::query()
            ->where('locale', $locale)
            ->where('slug', $fullSlug)
            ->where('page_id', '!=', $page->id)
            ->exists()) {
            return back()->withErrors(['slug' => 'A page with this URL already exists.'])->withInput();
        }

        $validated['slug'] = $fullSlug;
        $validated['editor_mode'] = $validated['editor_mode'] ?? ($page->editor_mode ?? 'builder');

        if ($validated['is_homepage'] ?? false) {
            Page::where('is_homepage', true)->where('id', '!=', $page->id)->update(['is_homepage' => false]);
        }

        $currentlyPublished = (bool) ($translation?->is_published ?? $page->is_published);

        if (($validated['is_published'] ?? false) && ! $currentlyPublished) {
            $validated['published_at'] = now();
        } elseif (! ($validated['is_published'] ?? false)) {
            $validated['published_at'] = null;
        }

        $normalizedBlocks = $this->normalizePageBlocks($validated['blocks'] ?? []);
        $currentLocalizedState = [
            'title' => $translation?->title ?? $page->title,
            'slug' => $translation?->slug ?? $page->slug,
            'type' => $page->type,
            'editor_mode' => $page->editor_mode ?? 'builder',
            'content' => $translation?->content ?? $page->content,
            'is_published' => (bool) ($translation?->is_published ?? $page->is_published),
            'is_homepage' => (bool) $page->is_homepage,
            'parent_id' => $page->parent_id,
            'meta_title' => $translation?->meta_title ?? $page->meta_title,
            'meta_description' => $translation?->meta_description ?? $page->meta_description,
            'og_image' => $translation?->og_image ?? $page->og_image,
            'publish_at' => ($translation?->publish_at ?? $page->publish_at)?->toDateTimeString(),
            'unpublish_at' => ($translation?->unpublish_at ?? $page->unpublish_at)?->toDateTimeString(),
        ];

        if ($locale === $defaultLocale && (
            $normalizedBlocks !== ($translation?->blocks ?? $page->blocks ?? [])
            || ($validated['metadata'] ?? $translation?->metadata ?? $page->metadata ?? []) !== ($translation?->metadata ?? $page->metadata ?? [])
            || Arr::only($validated, array_keys($currentLocalizedState)) !== $currentLocalizedState
        )) {
            $page->revisions()->create([
                'locale' => $locale,
                'user_id' => auth()->id(),
                'content_snapshot' => $translation?->blocks ?? $page->blocks ?? [],
                'meta_snapshot' => $translation?->metadata ?? $page->metadata ?? [],
                'page_snapshot' => $page->localizedRevisionSnapshot($locale),
                'label' => 'Saved '.now()->format('Y-m-d H:i'),
            ]);
        }

        $oldSlug = app(SiteLocaleManager::class)->pathForLocale(
            $translation?->slug ?? $page->slug,
            $locale,
        );

        $validated['blocks'] = $normalizedBlocks;

        $page->update(Page::sanitizePersistedAttributes([
            'type' => $validated['type'],
            'editor_mode' => $validated['editor_mode'],
            'is_homepage' => $validated['is_homepage'],
            'parent_id' => $validated['parent_id'],
            ...($locale === $defaultLocale ? Arr::only($validated, Page::LOCALIZED_FIELDS) : []),
        ]));

        $translationPayload = Arr::only($validated, Page::LOCALIZED_FIELDS);
        $translationPayload['locale'] = $locale;
        $page->translations()->updateOrCreate(
            ['locale' => $locale],
            $translationPayload,
        );

        if ($locale === $defaultLocale) {
            $page->refresh();
            $this->syncDefaultTranslation($page);
        }

        // Create redirect from old slug → new slug if requested and slug actually changed
        $newSlug = app(SiteLocaleManager::class)->pathForLocale(
            $page->fresh()->translationFor($locale)?->slug ?? $page->fresh()->slug,
            $locale,
        );
        if (($validated['create_redirect'] ?? false) && $oldSlug !== $newSlug) {
            $r = Redirect::updateOrCreate(
                ['from_url' => Redirect::normalizePath($oldSlug)],
                ['to_url' => $newSlug, 'type' => 301, 'is_active' => true],
            );
            $r->flushCache();
        }

        // Cascade slug changes to all descendant pages
        $this->updateDescendantSlugs($page->fresh()->load('translations'), $locale);

        // Bust menu cache for any menu linking to this page
        $this->bustMenuCaches([$page->id]);
        app(ContentRouteRegistry::class)->forget();

        return redirect()->route('admin.pages.edit', $this->editRouteParameters($page, $locale))
            ->with('success', 'Page saved.');
    }

    public function restoreRevision(Page $page, Revision $revision): RedirectResponse
    {
        $this->authorize('edit pages');
        $this->authorize('restore page revisions');

        abort_unless($revision->page_id === $page->id, 404);

        $page->revisions()->create([
            'locale' => app(SiteLocaleManager::class)->defaultLocale(),
            'user_id' => auth()->id(),
            'content_snapshot' => $page->blocks ?? [],
            'meta_snapshot' => $page->metadata ?? [],
            'page_snapshot' => $page->revisionSnapshot(),
            'label' => 'Restore point '.now()->format('Y-m-d H:i'),
        ]);

        $page->restore($revision);
        $this->syncDefaultTranslation($page->fresh());

        $this->updateDescendantSlugs($page->fresh());
        $this->bustMenuCaches([$page->id]);
        app(ContentRouteRegistry::class)->forget();

        return redirect()->route('admin.pages.edit', $page)
            ->with('success', 'Revision restored.');
    }

    public function reorder(Request $request): JsonResponse
    {
        $this->authorize('edit pages');

        $request->validate(['pages' => 'required|array']);
        $updatedSlugs = $this->syncPageOrder($request->pages, null, '');

        // Bust menu caches that contain any of the changed pages
        $this->bustMenuCaches(array_keys($updatedSlugs));
        app(ContentRouteRegistry::class)->forget();

        return response()->json(['ok' => true, 'slugs' => $updatedSlugs]);
    }

    private function syncPageOrder(array $pages, ?int $parentId, string $parentSlug): array
    {
        $updated = [];
        $order = 0;

        foreach ($pages as $item) {
            $page = Page::find($item['id']);
            if (! $page) {
                continue;
            }

            // Preserve base slug (last segment) and build full path from parent
            $segments = array_values(array_filter(explode('/', $page->slug)));
            $baseSlug = end($segments) ?: Str::slug($page->title);
            $newSlug = $parentSlug ? $parentSlug.'/'.$baseSlug : $baseSlug;

            $page->update([
                'parent_id' => $parentId,
                'order' => $order++,
                'slug' => $newSlug,
            ]);

            // Keep MenuItem.url in sync for page-linked items
            MenuItem::where('page_id', $page->id)->update(['url' => '/'.$newSlug]);

            $updated[$page->id] = $newSlug;

            if (! empty($item['children'])) {
                $updated += $this->syncPageOrder($item['children'], $page->id, $newSlug);
            }
        }

        return $updated;
    }

    private function revisionPayload(Page $page): array
    {
        $revisions = $page->revisions()
            ->with('user:id,name')
            ->limit(12)
            ->get();

        $comparison = [
            'blocks' => $page->blocks ?? [],
            'metadata' => $page->metadata ?? [],
            'page' => $page->revisionSnapshot(),
        ];

        return $revisions->map(function (Revision $revision) use ($page, &$comparison) {
            $snapshot = [
                'blocks' => $revision->content_snapshot ?? [],
                'metadata' => $revision->meta_snapshot ?? [],
                'page' => is_array($revision->page_snapshot) ? $revision->page_snapshot : [],
            ];

            $payload = [
                'id' => $revision->id,
                'label' => $revision->label ?: 'Revision',
                'created_at' => $revision->created_at?->toIso8601String(),
                'user' => $revision->user ? [
                    'id' => $revision->user->id,
                    'name' => $revision->user->name,
                ] : null,
                'preview_url' => route('admin.pages.revisions.preview', [$page, $revision]),
                'summary' => $this->revisionSummary($comparison, $snapshot),
            ];

            $comparison = $snapshot;

            return $payload;
        })->all();
    }

    private function revisionSummary(array $from, array $to): array
    {
        $summary = [];

        $pageFields = [
            'title' => 'Title',
            'slug' => 'URL',
            'type' => 'Page type',
            'editor_mode' => 'Editor mode',
            'content' => 'Content HTML',
            'is_homepage' => 'Homepage',
            'is_published' => 'Publish status',
            'parent_id' => 'Parent',
            'meta_title' => 'Meta title',
            'meta_description' => 'Meta description',
            'og_image' => 'OG image',
            'publish_at' => 'Publish schedule',
            'unpublish_at' => 'Unpublish schedule',
        ];

        $pageChanges = [];

        foreach ($pageFields as $field => $label) {
            if (Arr::get($from, "page.{$field}") !== Arr::get($to, "page.{$field}")) {
                $pageChanges[] = $label;
            }
        }

        if ($pageChanges !== []) {
            $visible = array_slice($pageChanges, 0, 2);
            $suffix = count($pageChanges) > 2 ? ' +'.(count($pageChanges) - 2).' more' : '';
            $summary[] = implode(', ', $visible).' changed'.$suffix;
        }

        if (($from['blocks'] ?? []) !== ($to['blocks'] ?? [])) {
            $summary[] = 'Layout/content blocks changed';
        }

        $metadataKeys = array_unique(array_merge(
            array_keys($from['metadata'] ?? []),
            array_keys($to['metadata'] ?? []),
        ));

        $metadataChanges = collect($metadataKeys)
            ->filter(fn (string $key) => Arr::get($from, "metadata.{$key}") !== Arr::get($to, "metadata.{$key}"))
            ->count();

        if ($metadataChanges > 0) {
            $summary[] = $metadataChanges === 1
                ? '1 metadata value changed'
                : "{$metadataChanges} metadata values changed";
        }

        return $summary === [] ? ['Snapshot available'] : array_slice($summary, 0, 3);
    }

    private function selectedLocale(Request $request): string
    {
        $manager = app(SiteLocaleManager::class);
        $requested = $manager->normalizeLocale($request->query('locale'));

        return $requested && $manager->isConfiguredLocale($requested)
            ? $requested
            : $manager->defaultLocale();
    }

    private function isDefaultLocale(string $locale): bool
    {
        return app(SiteLocaleManager::class)->isDefaultLocale($locale);
    }

    private function editorPayload(Page $page, string $locale): array
    {
        $translation = $page->translationFor($locale);
        $fallback = $translation ?? $page->translationFor(app(SiteLocaleManager::class)->defaultLocale());

        return [
            'id' => $page->id,
            'title' => $translation?->title ?? $fallback?->title ?? $page->title,
            'slug' => $translation?->slug ?? $fallback?->slug ?? $page->slug,
            'type' => $page->type,
            'editor_mode' => $page->editor_mode ?? 'builder',
            'content' => $translation?->content ?? $fallback?->content ?? $page->content,
            'is_published' => (bool) ($translation?->is_published ?? false),
            'is_homepage' => (bool) $page->is_homepage,
            'parent_id' => $page->parent_id,
            'blocks' => $translation?->blocks ?? $fallback?->blocks ?? $page->blocks ?? [],
            'metadata' => $translation?->metadata ?? $fallback?->metadata ?? $page->metadata ?? [],
            'meta_title' => $translation?->meta_title ?? $fallback?->meta_title ?? $page->meta_title,
            'meta_description' => $translation?->meta_description ?? $fallback?->meta_description ?? $page->meta_description,
            'og_image' => $translation?->og_image ?? $fallback?->og_image ?? $page->og_image,
            'publish_at' => ($translation?->publish_at ?? $fallback?->publish_at ?? null)?->toDateTimeString(),
            'unpublish_at' => ($translation?->unpublish_at ?? $fallback?->unpublish_at ?? null)?->toDateTimeString(),
            'translation_exists' => $translation !== null,
        ];
    }

    private function availableLocalePayload(Page $page): array
    {
        return app(SiteLocaleManager::class)
            ->allLanguages()
            ->map(fn ($language) => [
                'code' => $language->code,
                'name' => $language->name,
                'is_default' => (bool) $language->is_default,
                'is_active' => (bool) $language->is_active,
                'has_translation' => $page->translationFor($language->code) !== null,
                'edit_url' => route('admin.pages.edit', $this->editRouteParameters($page, $language->code)),
            ])
            ->values()
            ->all();
    }

    private function editRouteParameters(Page $page, string $locale): array|Page
    {
        if ($this->isDefaultLocale($locale)) {
            return $page;
        }

        return [
            'page' => $page,
            'locale' => $locale,
        ];
    }

    private function syncDefaultTranslation(Page $page): void
    {
        $page->translations()->updateOrCreate(
            ['locale' => app(SiteLocaleManager::class)->defaultLocale()],
            Arr::only($page->toArray(), Page::LOCALIZED_FIELDS),
        );
    }

    /**
     * Build a full hierarchical slug from an optional submitted value, page title, and parent.
     * Always treats the last segment of the submitted value as the base slug.
     */
    private function buildSlug(?string $submitted, string $title, ?int $parentId): string
    {
        return $this->buildSlugForLocale(
            $submitted,
            $title,
            $parentId,
            app(SiteLocaleManager::class)->defaultLocale(),
        );
    }

    private function buildSlugForLocale(?string $submitted, string $title, ?int $parentId, string $locale): string
    {
        $baseSlug = null;

        if ($submitted) {
            $segments = array_values(array_filter(explode('/', $submitted)));
            $baseSlug = Str::slug(end($segments));
        }

        if (empty($baseSlug)) {
            $baseSlug = Str::slug($title);
        }

        if ($parentId) {
            $parent = Page::query()->with('translations')->find($parentId);

            if ($parent) {
                $parentSlug = $parent->translationFor($locale)?->slug ?? $parent->slug;

                return $parentSlug.'/'.$baseSlug;
            }
        }

        return $baseSlug;
    }

    private function nextAvailableSlug(string $baseSlug): string
    {
        $slug = $baseSlug;
        $suffix = 1;
        $defaultLocale = app(SiteLocaleManager::class)->defaultLocale();

        while (
            Page::withTrashed()->where('slug', $slug)->exists()
            || PageTranslation::query()->where('locale', $defaultLocale)->where('slug', $slug)->exists()
        ) {
            $slug = $baseSlug.'-'.$suffix++;
        }

        return $slug;
    }

    private function nextAvailableLocalizedSlug(string $baseSlug, string $locale): string
    {
        $slug = $baseSlug;
        $suffix = 1;

        while (PageTranslation::query()->where('locale', $locale)->where('slug', $slug)->exists()) {
            $slug = $baseSlug.'-'.$suffix++;
        }

        return $slug;
    }

    private function defaultPageType(): string
    {
        $first = PageTypeManager::all()[0] ?? null;

        return is_array($first) ? ($first['type'] ?? 'page') : 'page';
    }

    /**
     * Recursively update descendant slugs when a parent's slug changes.
     */
    private function updateDescendantSlugs(Page $page, ?string $locale = null): void
    {
        $locale ??= app(SiteLocaleManager::class)->defaultLocale();

        foreach ($page->children()->with('translations')->get() as $child) {
            if ($this->isDefaultLocale($locale)) {
                $baseSlug = Page::slugSegment($child->slug, $child->title);
                $newChildSlug = $page->slug.'/'.$baseSlug;

                $child->update(['slug' => $newChildSlug]);

                if ($defaultTranslation = $child->translationFor($locale)) {
                    $defaultTranslation->update([
                        'slug' => $newChildSlug,
                    ]);
                }

                MenuItem::where('page_id', $child->id)->update(['url' => '/'.$newChildSlug]);
            } else {
                $childTranslation = $child->translationFor($locale);

                if (! $childTranslation) {
                    continue;
                }

                $parentSlug = $page->translationFor($locale)?->slug ?? $page->slug;
                $localizedSegment = Page::slugSegment($childTranslation->slug, $childTranslation->title);

                $childTranslation->update([
                    'slug' => $parentSlug.'/'.$localizedSegment,
                ]);
            }

            $this->updateDescendantSlugs($child, $locale);
        }
    }

    /**
     * Forget the menu cache for every menu that links to any of the given page IDs.
     */
    private function bustMenuCaches(array $pageIds): void
    {
        if (empty($pageIds)) {
            return;
        }

        if (Menu::whereHas('items', fn ($q) => $q->whereIn('page_id', $pageIds))->exists()) {
            app(MenuRenderCache::class)->flush();
        }
    }

    public function destroy(Page $page): RedirectResponse
    {
        $this->authorize('delete pages');

        $page->delete();
        app(ContentRouteRegistry::class)->forget();

        return redirect()->route('admin.pages.index')
            ->with('success', 'Page moved to trash.');
    }

    public function trash(): Response
    {
        $this->authorize('delete pages');

        $pages = Page::onlyTrashed()
            ->select('id', 'title', 'slug', 'type', 'deleted_at')
            ->orderByDesc('deleted_at')
            ->get();

        return Inertia::render('Pages/Trash', [
            'pages' => $pages,
        ]);
    }

    public function restore(int $id): RedirectResponse
    {
        $this->authorize('delete pages');

        $page = Page::onlyTrashed()->findOrFail($id);
        $page->restore();
        app(ContentRouteRegistry::class)->forget();

        return back()->with('success', 'Page restored.');
    }

    public function forceDelete(int $id): RedirectResponse
    {
        $this->authorize('delete pages');

        $page = Page::onlyTrashed()->findOrFail($id);
        $page->forceDelete();
        app(ContentRouteRegistry::class)->forget();

        return back()->with('success', 'Page permanently deleted.');
    }

    public function duplicate(Page $page): RedirectResponse
    {
        $this->authorize('create pages');

        $page->load('translations');

        $baseSlug = $page->slug.'-copy';
        $slug = $baseSlug;
        $suffix = 1;
        while (Page::where('slug', $slug)->exists()) {
            $slug = $baseSlug.'-'.$suffix++;
        }

        $newPage = $page->replicate(['is_homepage', 'publish_at', 'unpublish_at', 'published_at']);
        $newPage->title = $page->title.' (Copy)';
        $newPage->slug = $slug;
        $newPage->is_published = false;
        $newPage->is_homepage = false;
        $newPage->published_at = null;
        $newPage->publish_at = null;
        $newPage->unpublish_at = null;
        $newPage->save();

        foreach ($page->translations as $translation) {
            if ($translation->locale === app(SiteLocaleManager::class)->defaultLocale()) {
                continue;
            }

            $translatedSlug = $translation->locale === app(SiteLocaleManager::class)->defaultLocale()
                ? $slug
                : $this->nextAvailableLocalizedSlug($translation->slug.'-copy', $translation->locale);

            $newPage->translations()->create([
                'locale' => $translation->locale,
                'title' => $translation->title,
                'slug' => $translatedSlug,
                'content' => $translation->content,
                'is_published' => false,
                'metadata' => $translation->metadata ?? [],
                'blocks' => $translation->blocks ?? [],
                'meta_title' => $translation->meta_title,
                'meta_description' => $translation->meta_description,
                'og_image' => $translation->og_image,
                'publish_at' => null,
                'unpublish_at' => null,
                'published_at' => null,
            ]);
        }

        app(ContentRouteRegistry::class)->forget();

        return redirect()->route('admin.pages.edit', $newPage)
            ->with('success', 'Page duplicated.');
    }

    public function bulkAction(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'action' => 'required|in:delete,publish,unpublish',
            'ids' => 'required|array|min:1',
            'ids.*' => 'integer|exists:pages,id',
        ]);

        match ($validated['action']) {
            'delete' => $this->authorize('delete pages'),
            'publish', 'unpublish' => $this->authorize('publish pages'),
        };

        $pages = Page::whereIn('id', $validated['ids']);

        match ($validated['action']) {
            'delete' => $pages->get()->each->delete(),
            'publish' => $pages->update(['is_published' => true,  'published_at' => now()]),
            'unpublish' => $pages->update(['is_published' => false, 'published_at' => null]),
        };

        $count = count($validated['ids']);
        $label = match ($validated['action']) {
            'delete' => "Deleted {$count} page(s).",
            'publish' => "Published {$count} page(s).",
            'unpublish' => "Unpublished {$count} page(s).",
        };
        app(ContentRouteRegistry::class)->forget();

        return redirect()->route('admin.pages.index')->with('success', $label);
    }

    public function preview(Request $request, Page $page): View
    {
        $this->authorize('edit pages');

        $page->load('translations')->applyLocale($this->selectedLocale($request));

        view()->share('page', $page);

        $view = 'theme::'.PageTypeManager::viewFor($page->type ?? 'page');
        if (! view()->exists($view)) {
            $view = 'theme::pages.show';
        }

        return view($view, ['page' => $page]);
    }

    public function previewRevision(Request $request, Page $page, Revision $revision): HttpResponse
    {
        $this->authorize('edit pages');

        if (! $this->isDefaultLocale($this->selectedLocale($request))) {
            abort(404);
        }

        abort_unless($revision->page_id === $page->id, 404);

        $preview = $this->makePreviewPage($page, [
            ...Arr::only(is_array($revision->page_snapshot) ? $revision->page_snapshot : [], [
                'title',
                'slug',
                'type',
                'editor_mode',
                'content',
                'is_homepage',
                'is_published',
                'parent_id',
                'meta_title',
                'meta_description',
                'og_image',
                'publish_at',
                'unpublish_at',
                'published_at',
            ]),
            'blocks' => $revision->content_snapshot ?? ($page->blocks ?? []),
            'metadata' => $revision->meta_snapshot ?? ($page->metadata ?? []),
        ]);

        return response($this->renderPreviewHtml($preview), 200)->header('Content-Type', 'text/html');
    }

    public function previewLive(Request $request, Page $page): HttpResponse
    {
        $this->authorize('edit pages');
        $locale = $this->selectedLocale($request);

        $validated = $request->validate([
            'title' => 'nullable|string|max:255',
            'slug' => 'nullable|string|max:255',
            'type' => 'nullable|string|max:64',
            'editor_mode' => 'nullable|in:builder,content',
            'content' => 'nullable|string',
            'blocks' => 'nullable|array',
            'blocks.*.id' => 'required|string',
            'blocks.*.type' => 'required|string',
            'blocks.*.data' => 'nullable|array',
            'blocks.*.order' => 'nullable|integer',
        ]);

        $pageBlockErrors = $this->validatePageBlocks($validated['blocks'] ?? []);

        if ($pageBlockErrors !== []) {
            return response($pageBlockErrors[0], 422);
        }

        $preview = $this->makePreviewPage($page, [
            ...Arr::only($validated, ['title', 'slug', 'type', 'editor_mode', 'content']),
            'blocks' => $this->normalizePageBlocks($validated['blocks'] ?? ($page->blocks ?? [])),
        ], $locale);

        return response($this->renderPreviewHtml($preview), 200)->header('Content-Type', 'text/html');
    }

    private function authorizePublishChange(array $validated, ?Page $page = null, ?PageTranslation $translation = null): void
    {
        $current = [
            'is_published' => $translation?->is_published ?? $page?->is_published ?? false,
            'publish_at' => ($translation?->publish_at ?? $page?->publish_at)?->toDateTimeString(),
            'unpublish_at' => ($translation?->unpublish_at ?? $page?->unpublish_at)?->toDateTimeString(),
        ];

        $next = [
            'is_published' => (bool) ($validated['is_published'] ?? false),
            'publish_at' => $validated['publish_at'] ?? null,
            'unpublish_at' => $validated['unpublish_at'] ?? null,
        ];

        if ($current !== $next) {
            $this->authorize('publish pages');
        }
    }

    private function makePreviewPage(Page $page, array $attributes = [], ?string $locale = null): Page
    {
        $page->loadMissing('translations');
        $preview = $page->replicate();
        $preview->setRelation('translations', $page->translations);
        $preview->applyLocale($locale);

        $fillable = Arr::only($attributes, [
            'title',
            'slug',
            'type',
            'editor_mode',
            'content',
            'is_homepage',
            'is_published',
            'parent_id',
            'meta_title',
            'meta_description',
            'og_image',
            'publish_at',
            'unpublish_at',
            'published_at',
            'metadata',
            'blocks',
        ]);

        $preview->fill(Arr::except($fillable, ['metadata', 'blocks']));
        $preview->metadata = $fillable['metadata'] ?? ($preview->metadata ?? $page->metadata ?? []);
        $preview->blocks = $this->normalizePageBlocks($fillable['blocks'] ?? ($preview->blocks ?? $page->blocks ?? []));

        return $preview;
    }

    /**
     * @param  array<int, mixed>  $blocks
     * @return array<int, string>
     */
    private function validatePageBlocks(array $blocks): array
    {
        $schemaErrors = $this->blockPayload->validateStructure($blocks);

        if ($schemaErrors !== []) {
            return $schemaErrors;
        }

        $allowedTypes = collect($this->availablePageBlocks())->pluck('type')->all();

        $allowedTypeErrors = $this->blockPayload->validateAllowedTypes($blocks, $allowedTypes);

        if ($allowedTypeErrors !== []) {
            return $allowedTypeErrors;
        }

        return $this->validateContentBlockPlacement($this->normalizePageBlocks($blocks));
    }

    /**
     * @param  array<int, mixed>  $blocks
     * @return array<int, array<string, mixed>>
     */
    private function normalizePageBlocks(array $blocks): array
    {
        return $this->blockPayload->normalizeBlocks($blocks);
    }

    private function availablePageBlocks(): array
    {
        $theme = Theme::where('is_active', true)->value('slug') ?? Theme::DEFAULT_THEME_SLUG;

        return EmbeddableFormRegistry::decorateAvailableBlocks(BlockManager::getAvailableBlocks($theme));
    }

    /**
     * @param  array<int, array<string, mixed>>  $blocks
     * @return array<int, string>
     */
    private function validateContentBlockPlacement(array $blocks): array
    {
        $contentBlockCount = 0;
        $errors = [];

        foreach ($blocks as $index => $block) {
            if (($block['type'] ?? null) === 'content') {
                $contentBlockCount++;

                if ($contentBlockCount > 1) {
                    $errors[] = 'Only one Content block is allowed per page.';

                    break;
                }
            }

            $errors = [
                ...$errors,
                ...$this->validateNestedContentBlocks(
                    is_array($block['data'] ?? null) ? $block['data'] : [],
                    '$['.$index.']',
                ),
            ];
        }

        return $errors;
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<int, string>
     */
    private function validateNestedContentBlocks(array $data, string $path): array
    {
        $errors = [];

        foreach ($data as $key => $value) {
            if (($key !== 'children' && preg_match('/^col_\d+$/', (string) $key) !== 1) || ! is_array($value)) {
                continue;
            }

            foreach ($value as $index => $child) {
                if (! is_array($child)) {
                    continue;
                }

                if (($child['type'] ?? null) === 'content') {
                    $errors[] = "Content blocks can only be placed at the top level ({$path}.data.{$key}[{$index}]).";

                    continue;
                }

                $errors = [
                    ...$errors,
                    ...$this->validateNestedContentBlocks(
                        is_array($child['data'] ?? null) ? $child['data'] : [],
                        "{$path}.data.{$key}[{$index}]",
                    ),
                ];
            }
        }

        return $errors;
    }

    private function renderPreviewHtml(Page $preview): string
    {
        view()->share('page', $preview);

        $viewType = $preview->type ?? 'page';
        $view = 'theme::'.PageTypeManager::viewFor($viewType);
        if (! view()->exists($view)) {
            $view = 'theme::pages.show';
        }

        $html = view($view, ['page' => $preview])->render();

        // Inject a base tag so relative assets resolve correctly inside srcdoc
        $base = '<base href="'.rtrim(config('app.url'), '/').'/">';
        $html = preg_replace('/<head([^>]*)>/i', '<head$1>'.$base, $html, 1);

        return $html;
    }
}
