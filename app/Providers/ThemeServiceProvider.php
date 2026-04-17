<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\View;
use Illuminate\Support\Facades\Log;
use App\Models\Theme;

class ThemeServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap services.
     */
    public function boot(): void
    {
        if ($this->app->runningInConsole()) {
            return;
        }

        $activeThemeSlug = Cache::rememberForever('active_theme_slug', function () {
            $theme = \App\Models\Theme::where('is_active', true)->first();

            if (!$theme) {
                $theme = \App\Models\Theme::first();
                if ($theme) {
                    $theme->update(['is_active' => true]);
                }
            }

            return $theme?->slug;
        });

        // 3. Use the string to set the paths
        if ($activeThemeSlug) {
            View::prependNamespace('theme', base_path("themes/{$activeThemeSlug}/views"));
            View::prependNamespace('blocks', [
                base_path("themes/{$activeThemeSlug}/blocks"),
                base_path('blocks'),
            ]);

            $functions = base_path("themes/{$activeThemeSlug}/functions.php");
            if (file_exists($functions)) {
                require_once $functions;
            }
        }
    }
}
