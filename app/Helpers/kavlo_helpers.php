<?php

use App\Facades\Hook;
use App\Services\ContentRouteRegistry;
use App\Services\MenuRenderCache;
use App\Services\MenuWalker;
use App\Services\ScriptManager;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\View;
use Illuminate\Support\HtmlString;

if (! function_exists('kavlo_head')) {
    function kavlo_head()
    {
        return View::make('core::partials.head')->render();
    }
}

if (! function_exists('kavlo_scripts')) {
    function kavlo_scripts(string $placement)
    {
        return app(ScriptManager::class)->render($placement);
    }
}

if (! function_exists('kavlo_menu')) {
    function kavlo_menu($slug, array $options = [])
    {
        return app(MenuRenderCache::class)->remember((string) $slug, $options, function () use ($slug, $options) {
            $menu = app(ContentRouteRegistry::class)->resolveMenu($slug);

            if (! $menu) {
                return '';
            }

            return app(MenuWalker::class)->render($menu, $options);
        });
    }
}

if (! function_exists('kavlo_form')) {
    function kavlo_form(string $reference, array $data = []): HtmlString
    {
        $reference = trim($reference);

        if ($reference === '') {
            return new HtmlString('');
        }

        $payload = [
            'data' => [
                ...$data,
                'form_slug' => $reference,
            ],
        ];

        return new HtmlString(
            View::file(base_path('blocks/form/render.blade.php'), $payload)->render(),
        );
    }
}

if (! function_exists('kavlo_render_block')) {
    /**
     * @param  array<string, mixed>  $block
     */
    function kavlo_render_block(array $block): HtmlString
    {
        $type = trim((string) ($block['type'] ?? ''));
        $data = is_array($block['data'] ?? null) ? $block['data'] : [];

        if ($type === '') {
            return new HtmlString('');
        }

        $custom = Hook::applyFilters('blocks.render', null, $block, $data);

        if ($custom !== null) {
            return $custom instanceof HtmlString
                ? $custom
                : new HtmlString((string) $custom);
        }

        $themeView = "theme::blocks.{$type}.render";

        if (View::exists($themeView)) {
            return new HtmlString(view($themeView, [
                'block' => $block,
                'data' => $data,
            ])->render());
        }

        $corePath = base_path("blocks/{$type}/render.blade.php");

        if (! File::exists($corePath)) {
            return new HtmlString('');
        }

        return new HtmlString(View::file($corePath, [
            'block' => $block,
            'data' => $data,
        ])->render());
    }
}
