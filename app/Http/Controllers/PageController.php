<?php

namespace App\Http\Controllers;

use App\Models\Theme;
use App\Services\ContentRouteRegistry;
use App\Services\PageAnalytics;
use App\Services\PageTypeManager;
use App\Services\PublicPageCache;
use App\Services\SiteLocaleManager;
use Illuminate\Http\Request;

class PageController extends Controller
{
    public function show(
        Request $request,
        ContentRouteRegistry $routes,
        PageAnalytics $analytics,
        PublicPageCache $pageCache,
        string $slug = '/',
    ) {
        $path = $slug === '/' ? '/' : '/'.ltrim($slug, '/');
        $page = $routes->resolvePage($path);

        if (! $page) {
            abort(404);
        }

        $themeSlug = Theme::where('is_active', true)->value('slug') ?? Theme::DEFAULT_THEME_SLUG;
        $view = 'theme::'.PageTypeManager::viewFor($page->type ?? 'page');

        // Fall back to pages.show if the type-specific view doesn't exist
        if (! view()->exists($view)) {
            $view = 'theme::pages.show';
        }

        $analytics->track($page, $request);

        if (! $pageCache->shouldCache($request)) {
            view()->share('page', $page);

            return view($view, [
                'page' => $page,
            ]);
        }

        $cached = $pageCache->remember($page, $themeSlug, $view, function () use ($page, $view) {
            view()->share('page', $page);

            return view($view, [
                'page' => $page,
            ])->render();
        });

        return response($cached['html'], 200, [
            'Content-Type' => 'text/html; charset=UTF-8',
            'X-CMS-Page-Cache' => $cached['status'],
        ]);
    }

    public function showLocalized(
        Request $request,
        ContentRouteRegistry $routes,
        PageAnalytics $analytics,
        PublicPageCache $pageCache,
        string $locale,
        ?string $slug = null,
    ) {
        $normalizedLocale = app(SiteLocaleManager::class)->normalizeLocale($locale);

        if (
            ! $normalizedLocale
            || app(SiteLocaleManager::class)->isDefaultLocale($normalizedLocale)
            || ! app(SiteLocaleManager::class)->isConfiguredLocale($normalizedLocale)
        ) {
            if ($slug === null) {
                return $this->show($request, $routes, $analytics, $pageCache, $locale);
            }

            abort(404);
        }

        $path = app(SiteLocaleManager::class)->pathForLocale($slug ?? '/', $normalizedLocale, ($slug ?? '') === '');

        $page = $routes->resolvePage($path);

        if (! $page) {
            abort(404);
        }

        $themeSlug = Theme::where('is_active', true)->value('slug') ?? Theme::DEFAULT_THEME_SLUG;
        $view = 'theme::'.PageTypeManager::viewFor($page->type ?? 'page');

        if (! view()->exists($view)) {
            $view = 'theme::pages.show';
        }

        $analytics->track($page, $request);

        if (! $pageCache->shouldCache($request)) {
            view()->share('page', $page);

            return view($view, [
                'page' => $page,
            ]);
        }

        $cached = $pageCache->remember($page, $themeSlug, $view, function () use ($page, $view) {
            view()->share('page', $page);

            return view($view, [
                'page' => $page,
            ])->render();
        });

        return response($cached['html'], 200, [
            'Content-Type' => 'text/html; charset=UTF-8',
            'X-CMS-Page-Cache' => $cached['status'],
        ]);
    }
}
