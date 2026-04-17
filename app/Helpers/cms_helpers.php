<?php

use Illuminate\Support\Facades\View;
use Illuminate\Support\Facades\Cache;
use App\Services\ContentRouteRegistry;
use App\Services\MenuWalker;

if (! function_exists('cms_head')) {
    function cms_head()
    {
        return View::make('core::partials.head')->render();
    }
}

if (! function_exists('cms_menu')) {
    function cms_menu($slug, array $options = [])
    {
        // Cache the rendered HTML string (not the Eloquent model) to avoid
        // __PHP_Incomplete_Class errors from PHP object deserialization in
        // the database cache driver.
        $cacheKey = 'cms_menu_html_' . $slug;

        return Cache::remember($cacheKey, 60, function () use ($slug, $options) {
            $menu = app(ContentRouteRegistry::class)->resolveMenu($slug);

            if (! $menu) {
                return '';
            }

            return app(MenuWalker::class)->render($menu, $options);
        });
    }
}
