<?php

namespace App\Http\Controllers;

use App\Models\Theme;
use App\Services\ContentRouteRegistry;
use App\Services\PageAnalytics;
use App\Services\PageTypeManager;
use App\Services\PublicPageCache;
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
        $page = $routes->resolvePage($slug);

        if (! $page) {
            abort(404);
        }

        $themeSlug = Theme::where('is_active', true)->value('slug') ?? 'blocks';
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
}
