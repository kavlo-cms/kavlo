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
                'page' => View::shared('page'),
            ])->render());
        }

        $corePath = base_path("blocks/{$type}/render.blade.php");

        if (! File::exists($corePath)) {
            return new HtmlString('');
        }

        return new HtmlString(View::file($corePath, [
            'block' => $block,
            'data' => $data,
            'page' => View::shared('page'),
        ])->render());
    }
}

if (! function_exists('kavlo_block_width_class')) {
    function kavlo_block_width_class(?string $value, string $default = 'medium'): string
    {
        return [
            'full' => 'max-w-none',
            'wide' => 'max-w-5xl',
            'medium' => 'max-w-3xl',
            'narrow' => 'max-w-2xl',
        ][$value ?: $default] ?? 'max-w-3xl';
    }
}

if (! function_exists('kavlo_is_hex_color')) {
    function kavlo_is_hex_color(mixed $value): bool
    {
        return is_string($value) && preg_match('/^#(?:[0-9a-fA-F]{3}|[0-9a-fA-F]{6})$/', trim($value)) === 1;
    }
}

if (! function_exists('kavlo_normalize_hex_color')) {
    function kavlo_normalize_hex_color(string $value): string
    {
        $trimmed = strtolower(trim($value));

        if (strlen($trimmed) === 4) {
            return '#'.$trimmed[1].$trimmed[1].$trimmed[2].$trimmed[2].$trimmed[3].$trimmed[3];
        }

        return $trimmed;
    }
}

if (! function_exists('kavlo_resolve_text_color')) {
    function kavlo_resolve_text_color(mixed $value): string
    {
        if (kavlo_is_hex_color($value)) {
            return kavlo_normalize_hex_color($value);
        }

        return [
            'default' => '#111827',
            'muted' => '#6b7280',
            'primary' => '#2563eb',
            'accent' => '#0284c7',
            'inverse' => '#ffffff',
        ][(string) ($value ?: 'default')] ?? '#111827';
    }
}

if (! function_exists('kavlo_resolve_button_color')) {
    function kavlo_resolve_button_color(mixed $value): string
    {
        if (kavlo_is_hex_color($value)) {
            return kavlo_normalize_hex_color($value);
        }

        return [
            'brand' => '#2563eb',
            'neutral' => '#0f172a',
            'success' => '#059669',
            'danger' => '#dc2626',
        ][(string) ($value ?: 'brand')] ?? '#2563eb';
    }
}

if (! function_exists('kavlo_readable_text_color')) {
    function kavlo_readable_text_color(string $background): string
    {
        $hex = ltrim($background, '#');

        if (strlen($hex) === 3) {
            $hex = $hex[0].$hex[0].$hex[1].$hex[1].$hex[2].$hex[2];
        }

        $red = hexdec(substr($hex, 0, 2)) / 255;
        $green = hexdec(substr($hex, 2, 2)) / 255;
        $blue = hexdec(substr($hex, 4, 2)) / 255;
        $luminance = 0.2126 * $red + 0.7152 * $green + 0.0722 * $blue;

        return $luminance > 0.6 ? '#0f172a' : '#ffffff';
    }
}

if (! function_exists('kavlo_normalize_gradient_config')) {
    /**
     * @return array{start: string, end: string, angle: int}|null
     */
    function kavlo_normalize_gradient_config(mixed $value): ?array
    {
        if (! is_array($value)) {
            return null;
        }

        $start = $value['start'] ?? null;
        $end = $value['end'] ?? null;
        $angle = $value['angle'] ?? null;

        if (! kavlo_is_hex_color($start) || ! kavlo_is_hex_color($end) || ! is_numeric($angle)) {
            return null;
        }

        $normalizedAngle = ((int) round((float) $angle)) % 360;

        if ($normalizedAngle < 0) {
            $normalizedAngle += 360;
        }

        return [
            'start' => kavlo_normalize_hex_color($start),
            'end' => kavlo_normalize_hex_color($end),
            'angle' => $normalizedAngle,
        ];
    }
}

if (! function_exists('kavlo_gradient_css')) {
    /**
     * @param  array{start: string, end: string, angle: int}  $gradient
     */
    function kavlo_gradient_css(array $gradient): string
    {
        return "linear-gradient({$gradient['angle']}deg, {$gradient['start']}, {$gradient['end']})";
    }
}

if (! function_exists('kavlo_gradient_text_style')) {
    function kavlo_gradient_text_style(mixed $value): ?string
    {
        $gradient = kavlo_normalize_gradient_config($value);

        if (! $gradient) {
            return null;
        }

        $css = kavlo_gradient_css($gradient);

        return "background-image: {$css}; background-clip: text; -webkit-background-clip: text; color: transparent; -webkit-text-fill-color: transparent;";
    }
}

if (! function_exists('kavlo_gradient_background_style')) {
    function kavlo_gradient_background_style(mixed $value): ?string
    {
        $gradient = kavlo_normalize_gradient_config($value);

        if (! $gradient) {
            return null;
        }

        $css = kavlo_gradient_css($gradient);
        $textColor = kavlo_readable_text_color($gradient['start']);

        return "background-image: {$css}; color: {$textColor}; border-color: transparent;";
    }
}

if (! function_exists('kavlo_button_variant_class')) {
    function kavlo_button_variant_class(string $variant, mixed $tone): string
    {
        if (kavlo_is_hex_color($tone)) {
            return [
                'primary' => 'border border-transparent',
                'secondary' => 'border border-transparent',
                'outline' => 'border bg-transparent',
                'ghost' => 'bg-transparent',
            ][$variant] ?? 'border border-transparent';
        }

        $tone = (string) ($tone ?: 'brand');

        return [
            'primary' => [
                'brand' => 'bg-primary text-primary-foreground hover:bg-primary/90',
                'neutral' => 'bg-slate-900 text-white hover:bg-slate-800 dark:bg-slate-100 dark:text-slate-900 dark:hover:bg-slate-200',
                'success' => 'bg-emerald-600 text-white hover:bg-emerald-500',
                'danger' => 'bg-red-600 text-white hover:bg-red-500',
            ],
            'secondary' => [
                'brand' => 'bg-secondary text-secondary-foreground hover:bg-secondary/80',
                'neutral' => 'bg-slate-100 text-slate-900 hover:bg-slate-200 dark:bg-slate-800 dark:text-slate-100 dark:hover:bg-slate-700',
                'success' => 'bg-emerald-100 text-emerald-800 hover:bg-emerald-200 dark:bg-emerald-950 dark:text-emerald-200 dark:hover:bg-emerald-900',
                'danger' => 'bg-red-100 text-red-800 hover:bg-red-200 dark:bg-red-950 dark:text-red-200 dark:hover:bg-red-900',
            ],
            'outline' => [
                'brand' => 'border border-primary text-primary hover:bg-primary/10',
                'neutral' => 'border border-slate-300 text-slate-900 hover:bg-slate-100 dark:border-slate-700 dark:text-slate-100 dark:hover:bg-slate-800',
                'success' => 'border border-emerald-500 text-emerald-700 hover:bg-emerald-50 dark:text-emerald-300 dark:hover:bg-emerald-950',
                'danger' => 'border border-red-500 text-red-700 hover:bg-red-50 dark:text-red-300 dark:hover:bg-red-950',
            ],
            'ghost' => [
                'brand' => 'text-primary hover:bg-primary/10',
                'neutral' => 'text-slate-900 hover:bg-slate-100 dark:text-slate-100 dark:hover:bg-slate-800',
                'success' => 'text-emerald-700 hover:bg-emerald-50 dark:text-emerald-300 dark:hover:bg-emerald-950',
                'danger' => 'text-red-700 hover:bg-red-50 dark:text-red-300 dark:hover:bg-red-950',
            ],
        ][$variant][$tone] ?? 'bg-primary text-primary-foreground hover:bg-primary/90';
    }
}

if (! function_exists('kavlo_button_variant_style')) {
    function kavlo_button_variant_style(string $variant, mixed $tone): ?string
    {
        if (! kavlo_is_hex_color($tone)) {
            return null;
        }

        $color = kavlo_normalize_hex_color($tone);

        return match ($variant) {
            'secondary' => 'background-color: '.kavlo_hex_rgba($color, 0.14).'; border-color: '.kavlo_hex_rgba($color, 0.22)."; color: {$color};",
            'outline' => "border-color: {$color}; color: {$color};",
            'ghost' => "color: {$color};",
            default => "background-color: {$color}; border-color: {$color}; color: ".kavlo_readable_text_color($color).';',
        };
    }
}

if (! function_exists('kavlo_button_radius_class')) {
    function kavlo_button_radius_class(?string $value): string
    {
        return [
            'rounded' => 'rounded-lg',
            'soft' => 'rounded-md',
            'pill' => 'rounded-full',
            'square' => 'rounded-none',
        ][$value ?: 'rounded'] ?? 'rounded-lg';
    }
}

if (! function_exists('kavlo_button_width_class')) {
    function kavlo_button_width_class(?string $value): string
    {
        return ($value ?: 'auto') === 'full' ? 'w-full justify-center' : '';
    }
}

if (! function_exists('kavlo_hex_rgba')) {
    function kavlo_hex_rgba(string $value, float $alpha): string
    {
        $hex = ltrim($value, '#');

        if (strlen($hex) === 3) {
            $hex = $hex[0].$hex[0].$hex[1].$hex[1].$hex[2].$hex[2];
        }

        $red = hexdec(substr($hex, 0, 2));
        $green = hexdec(substr($hex, 2, 2));
        $blue = hexdec(substr($hex, 4, 2));

        return "rgba({$red}, {$green}, {$blue}, {$alpha})";
    }
}
