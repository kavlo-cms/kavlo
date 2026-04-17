<?php

namespace App\Http\Controllers;

use App\Services\ContentRouteRegistry;
use App\Services\PageTypeManager;

class PageController extends Controller
{
    public function show(ContentRouteRegistry $routes, string $slug = '/')
    {
        $page = $routes->resolvePage($slug);

        if (!$page) {
            abort(404);
        }

        view()->share('page', $page);

        $view = 'theme::' . PageTypeManager::viewFor($page->type ?? 'page');

        // Fall back to pages.show if the type-specific view doesn't exist
        if (!view()->exists($view)) {
            $view = 'theme::pages.show';
        }

        return view($view, [
            'page' => $page,
        ]);
    }
}
