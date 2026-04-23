<?php

namespace App\Providers;

use App\Models\Theme;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;
use PDOException;

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

        try {
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

            if ($activeThemeSlug) {
                $this->registerThemeNamespaces($activeThemeSlug);
            }
        } catch (PDOException|QueryException $exception) {
            Log::warning('Falling back to the default theme namespace.', [
                'theme' => Theme::DEFAULT_THEME_SLUG,
                'error' => $exception->getMessage(),
            ]);

            $this->registerThemeNamespaces(Theme::DEFAULT_THEME_SLUG);
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
