<?php

namespace App\Providers;

use App\Models\Theme;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;

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
        if ($this->app->runningInConsole() && ! $this->app->runningUnitTests()) {
            return;
        }

        if (! Schema::hasTable('themes')) {
            $this->registerThemeNamespaces(Theme::DEFAULT_THEME_SLUG);

            return;
        }

        Theme::discover();

        $activeThemeSlug = Cache::rememberForever('active_theme_slug', function () {
            $theme = Theme::where('is_active', true)->first();

            if (! $theme) {
                $theme = Theme::where('slug', Theme::DEFAULT_THEME_SLUG)->first()
                    ?? Theme::orderBy('name')->first();

                if ($theme) {
                    $theme->activate();
                }
            }

            return $theme?->slug;
        });

        // 3. Use the string to set the paths
        if ($activeThemeSlug) {
            $this->registerThemeNamespaces($activeThemeSlug);
        }
    }

    private function registerThemeNamespaces(string $slug): void
    {
        View::prependNamespace('theme', base_path("themes/{$slug}/views"));
        View::prependNamespace('blocks', [
            base_path("themes/{$slug}/blocks"),
            base_path('blocks'),
        ]);

        $functions = base_path("themes/{$slug}/functions.php");
        if (file_exists($functions)) {
            require_once $functions;
        }
    }
}
