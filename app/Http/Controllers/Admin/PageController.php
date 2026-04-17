<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Menu;
use App\Models\MenuItem;
use App\Models\Page;
use App\Models\Redirect;
use App\Models\Theme;
use App\Services\BlockManager;
use App\Services\ContentRouteRegistry;
use App\Services\EmbeddableFormRegistry;
use App\Services\PageTypeManager;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

class PageController extends Controller
{
    public function index(): Response
    {
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
        $pages = Page::select('id', 'title')->orderBy('title')->get();

        return Inertia::render('Pages/Create', [
            'pages'     => $pages,
            'pageTypes' => PageTypeManager::all(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'title'            => 'required|string|max:255',
            'slug'             => 'nullable|string|max:255',
            'type'             => 'nullable|string|max:64',
            'content'          => 'nullable|string',
            'is_published'     => 'boolean',
            'is_homepage'      => 'boolean',
            'parent_id'        => 'nullable|integer|exists:pages,id',
            'meta_title'       => 'nullable|string|max:255',
            'meta_description' => 'nullable|string|max:500',
            'og_image'         => 'nullable|string|max:2048',
            'publish_at'       => 'nullable|date',
            'unpublish_at'     => 'nullable|date',
        ]);

        $fullSlug = $this->buildSlug($validated['slug'] ?? null, $validated['title'], $validated['parent_id'] ?? null);

        if (Page::where('slug', $fullSlug)->exists()) {
            return back()->withErrors(['slug' => 'A page with this URL already exists.'])->withInput();
        }

        $validated['slug'] = $fullSlug;

        if ($validated['is_homepage'] ?? false) {
            Page::where('is_homepage', true)->update(['is_homepage' => false]);
        }

        if ($validated['is_published'] ?? false) {
            $validated['published_at'] = now();
        }

        $page = Page::create($validated);
        app(ContentRouteRegistry::class)->forget();

        return redirect()->route('admin.pages.edit', $page)
            ->with('success', 'Page created.');
    }

    public function edit(Page $page): Response
    {
        $pages = Page::where('id', '!=', $page->id)
            ->select('id', 'title', 'slug')
            ->orderBy('title')
            ->get();

        $theme = Theme::where('is_active', true)->value('slug') ?? 'blocks';
        $availableBlocks = EmbeddableFormRegistry::decorateAvailableBlocks(BlockManager::getAvailableBlocks($theme));
        $themeConfig = $this->readThemeConfig($theme);

        return Inertia::render('Pages/Edit', [
            'page'            => $page,
            'pages'           => $pages,
            'availableForms'  => EmbeddableFormRegistry::editorOptions(),
            'availableFormPreviews' => EmbeddableFormRegistry::editorPreviews(),
            'availableBlocks' => $availableBlocks,
            'previewUrl'      => route('admin.pages.preview', $page),
            'themeConfig'     => $themeConfig,
            'pageTypes'       => PageTypeManager::all(),
        ]);
    }

    private function readThemeConfig(string $slug): array
    {
        $path = base_path("themes/{$slug}/theme.json");
        if (!file_exists($path)) {
            return [];
        }
        return json_decode(file_get_contents($path), true) ?? [];
    }

    public function update(Request $request, Page $page): RedirectResponse
    {
        $validated = $request->validate([
            'title'           => 'required|string|max:255',
            'slug'            => 'nullable|string|max:255',
            'type'            => 'nullable|string|max:64',
            'content'         => 'nullable|string',
            'is_published'    => 'boolean',
            'is_homepage'     => 'boolean',
            'parent_id'       => 'nullable|integer|exists:pages,id',
            'blocks'          => 'nullable|array',
            'blocks.*.id'     => 'required|string',
            'blocks.*.type'   => 'required|string',
            'blocks.*.data'   => 'nullable|array',
            'blocks.*.order'  => 'nullable|integer',
            'metadata'        => 'nullable|array',
            'create_redirect' => 'boolean',
            'meta_title'       => 'nullable|string|max:255',
            'meta_description' => 'nullable|string|max:500',
            'og_image'         => 'nullable|string|max:2048',
            'publish_at'       => 'nullable|date',
            'unpublish_at'     => 'nullable|date',
        ]);

        $fullSlug = $this->buildSlug($validated['slug'] ?? null, $validated['title'], $validated['parent_id'] ?? null);

        if (Page::where('slug', $fullSlug)->where('id', '!=', $page->id)->exists()) {
            return back()->withErrors(['slug' => 'A page with this URL already exists.'])->withInput();
        }

        $validated['slug'] = $fullSlug;

        if ($validated['is_homepage'] ?? false) {
            Page::where('is_homepage', true)->where('id', '!=', $page->id)->update(['is_homepage' => false]);
        }

        if (($validated['is_published'] ?? false) && ! $page->is_published) {
            $validated['published_at'] = now();
        } elseif (! ($validated['is_published'] ?? false)) {
            $validated['published_at'] = null;
        }

        $page->revisions()->create([
            'user_id'           => auth()->id(),
            'content_snapshot'  => $page->blocks ?? [],
            'meta_snapshot'     => $page->metadata ?? [],
            'label'             => 'Auto-save ' . now()->format('Y-m-d H:i'),
        ]);

        $oldSlug    = '/' . ltrim($page->slug, '/');
        $page->update($validated);

        // Create redirect from old slug → new slug if requested and slug actually changed
        $newSlug = '/' . ltrim($page->fresh()->slug, '/');
        if (($validated['create_redirect'] ?? false) && $oldSlug !== $newSlug) {
            $r = Redirect::updateOrCreate(
                ['from_url' => Redirect::normalizePath($oldSlug)],
                ['to_url' => $newSlug, 'type' => 301, 'is_active' => true],
            );
            $r->flushCache();
        }

        // Cascade slug changes to all descendant pages
        $this->updateDescendantSlugs($page->fresh());

        // Bust menu cache for any menu linking to this page
        $this->bustMenuCaches([$page->id]);
        app(ContentRouteRegistry::class)->forget();

        return redirect()->route('admin.pages.edit', $page)
            ->with('success', 'Page saved.');
    }

    public function reorder(Request $request): JsonResponse
    {
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
        $order   = 0;

        foreach ($pages as $item) {
            $page = Page::find($item['id']);
            if (! $page) {
                continue;
            }

            // Preserve base slug (last segment) and build full path from parent
            $segments  = array_values(array_filter(explode('/', $page->slug)));
            $baseSlug  = end($segments) ?: Str::slug($page->title);
            $newSlug   = $parentSlug ? $parentSlug . '/' . $baseSlug : $baseSlug;

            $page->update([
                'parent_id' => $parentId,
                'order'     => $order++,
                'slug'      => $newSlug,
            ]);

            // Keep MenuItem.url in sync for page-linked items
            MenuItem::where('page_id', $page->id)->update(['url' => '/' . $newSlug]);

            $updated[$page->id] = $newSlug;

            if (! empty($item['children'])) {
                $updated += $this->syncPageOrder($item['children'], $page->id, $newSlug);
            }
        }

        return $updated;
    }

    /**
     * Build a full hierarchical slug from an optional submitted value, page title, and parent.
     * Always treats the last segment of the submitted value as the base slug.
     */
    private function buildSlug(?string $submitted, string $title, ?int $parentId): string
    {
        if ($submitted) {
            $segments = array_values(array_filter(explode('/', $submitted)));
            $baseSlug = Str::slug(end($segments));
        }

        if (empty($baseSlug)) {
            $baseSlug = Str::slug($title);
        }

        if ($parentId) {
            $parent = Page::find($parentId);
            if ($parent) {
                return $parent->slug . '/' . $baseSlug;
            }
        }

        return $baseSlug;
    }

    /**
     * Recursively update descendant slugs when a parent's slug changes.
     */
    private function updateDescendantSlugs(Page $page): void
    {
        foreach ($page->children as $child) {
            $segments     = array_values(array_filter(explode('/', $child->slug)));
            $baseSlug     = end($segments) ?: Str::slug($child->title);
            $newChildSlug = $page->slug . '/' . $baseSlug;

            $child->update(['slug' => $newChildSlug]);
            MenuItem::where('page_id', $child->id)->update(['url' => '/' . $newChildSlug]);
            $this->updateDescendantSlugs($child);
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

        Menu::whereHas('items', fn ($q) => $q->whereIn('page_id', $pageIds))
            ->pluck('slug')
            ->each(fn ($slug) => Cache::forget('cms_menu_html_' . $slug));
    }

    public function destroy(Page $page): RedirectResponse
    {
        $page->delete();
        app(ContentRouteRegistry::class)->forget();

        return redirect()->route('admin.pages.index')
            ->with('success', 'Page moved to trash.');
    }

    public function trash(): Response
    {
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
        $page = Page::onlyTrashed()->findOrFail($id);
        $page->restore();
        app(ContentRouteRegistry::class)->forget();

        return back()->with('success', 'Page restored.');
    }

    public function forceDelete(int $id): RedirectResponse
    {
        $page = Page::onlyTrashed()->findOrFail($id);
        $page->forceDelete();
        app(ContentRouteRegistry::class)->forget();

        return back()->with('success', 'Page permanently deleted.');
    }

    public function duplicate(Page $page): RedirectResponse
    {
        $baseSlug = $page->slug . '-copy';
        $slug     = $baseSlug;
        $suffix   = 1;
        while (Page::where('slug', $slug)->exists()) {
            $slug = $baseSlug . '-' . $suffix++;
        }

        $newPage = $page->replicate(['is_homepage', 'publish_at', 'unpublish_at', 'published_at']);
        $newPage->title        = $page->title . ' (Copy)';
        $newPage->slug         = $slug;
        $newPage->is_published = false;
        $newPage->is_homepage  = false;
        $newPage->published_at = null;
        $newPage->publish_at   = null;
        $newPage->unpublish_at = null;
        $newPage->save();
        app(ContentRouteRegistry::class)->forget();

        return redirect()->route('admin.pages.edit', $newPage)
            ->with('success', 'Page duplicated.');
    }

    public function bulkAction(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'action' => 'required|in:delete,publish,unpublish',
            'ids'    => 'required|array|min:1',
            'ids.*'  => 'integer|exists:pages,id',
        ]);

        $pages = Page::whereIn('id', $validated['ids']);

        match ($validated['action']) {
            'delete'    => $pages->get()->each->delete(),
            'publish'   => $pages->update(['is_published' => true,  'published_at' => now()]),
            'unpublish' => $pages->update(['is_published' => false, 'published_at' => null]),
        };

        $count = count($validated['ids']);
        $label = match ($validated['action']) {
            'delete'    => "Deleted {$count} page(s).",
            'publish'   => "Published {$count} page(s).",
            'unpublish' => "Unpublished {$count} page(s).",
        };
        app(ContentRouteRegistry::class)->forget();

        return redirect()->route('admin.pages.index')->with('success', $label);
    }

    public function preview(Page $page): \Illuminate\View\View
    {
        view()->share('page', $page);

        $view = 'theme::' . PageTypeManager::viewFor($page->type ?? 'page');
        if (!view()->exists($view)) {
            $view = 'theme::pages.show';
        }

        return view($view, ['page' => $page]);
    }

    public function previewLive(Request $request, Page $page): \Illuminate\Http\Response
    {
        $validated = $request->validate([
            'title'          => 'nullable|string|max:255',
            'slug'           => 'nullable|string|max:255',
            'type'           => 'nullable|string|max:64',
            'content'        => 'nullable|string',
            'blocks'         => 'nullable|array',
            'blocks.*.id'    => 'required|string',
            'blocks.*.type'  => 'required|string',
            'blocks.*.data'  => 'nullable|array',
            'blocks.*.order' => 'nullable|integer',
        ]);

        $preview = $page->replicate();
        $preview->fill(Arr::only($validated, ['title', 'slug', 'type', 'content']));
        $preview->blocks = $validated['blocks'] ?? ($page->blocks ?? []);

        $viewType = $preview->type ?? $page->type ?? 'page';
        $view = 'theme::' . PageTypeManager::viewFor($viewType);
        if (!view()->exists($view)) {
            $view = 'theme::pages.show';
        }

        $html = view($view, ['page' => $preview])->render();

        // Inject a base tag so relative assets resolve correctly inside srcdoc
        $base = '<base href="' . rtrim(config('app.url'), '/') . '/">';
        $html = preg_replace('/<head([^>]*)>/i', '<head$1>' . $base, $html, 1);

        return response($html, 200)->header('Content-Type', 'text/html');
    }
}
