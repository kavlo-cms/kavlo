<?php

namespace App\Providers;

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;
use Illuminate\Support\Facades\View;
use App\Services\ContentRouteRegistry;
use App\Services\HookManager;
use App\Services\MenuWalker;
use App\Services\PluginManager;
use App\Services\SitemapRegistry;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->singleton(HookManager::class);

        $this->app->singleton(MenuWalker::class, function ($app) {
            return new MenuWalker();
        });

        $this->app->singleton(ContentRouteRegistry::class, function ($app) {
            return new ContentRouteRegistry();
        });

        (new PluginManager())->bootPlugins();
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(SitemapRegistry $registry): void
    {
        // super-admin bypasses all Gate/permission checks
        \Illuminate\Support\Facades\Gate::before(function ($user, $ability) {
            return $user->hasRole('super-admin') ? true : null;
        });

        // Register theme error pages
        $this->registerThemeErrorPages();

        $this->configureDefaults();
        $this->configureMailFromSettings();
        View::addNamespace('core', resource_path('views/core'));
        $registry->addCollector(fn () => app(ContentRouteRegistry::class)->publishedPageUrls());
    }

    protected function registerThemeErrorPages(): void
    {
        try {
            $themeSlug = \App\Models\Setting::get('active_theme_slug', 'midnight-blue');
            $errorsPath = base_path("themes/{$themeSlug}/views/errors");

            if (is_dir($errorsPath)) {
                \Illuminate\Support\Facades\View::addNamespace('errors', $errorsPath);
            }
        } catch (\Exception $e) {
            // DB not ready (e.g. during migrations/tests) — keep default error views.
        }
    }

    protected function configureMailFromSettings(): void
    {
        try {
            $host = \App\Models\Setting::get('mail_host');
            if (!$host) return;
            config([
                'mail.default'                 => \App\Models\Setting::get('mail_mailer', 'smtp'),
                'mail.mailers.smtp.host'       => $host,
                'mail.mailers.smtp.port'       => (int) \App\Models\Setting::get('mail_port', 587),
                'mail.mailers.smtp.username'   => \App\Models\Setting::get('mail_username'),
                'mail.mailers.smtp.password'   => \App\Models\Setting::get('mail_password'),
                'mail.mailers.smtp.encryption' => \App\Models\Setting::get('mail_encryption', 'tls'),
                'mail.from.address'            => \App\Models\Setting::get('mail_from_address', config('mail.from.address')),
                'mail.from.name'               => \App\Models\Setting::get('mail_from_name', config('mail.from.name')),
            ]);
        } catch (\Exception $e) {
            // DB not ready (e.g. during migrations) — skip silently
        }
    }

    /**
     * Configure default behaviors for production-ready applications.
     */
    protected function configureDefaults(): void
    {
        Date::use(CarbonImmutable::class);

        DB::prohibitDestructiveCommands(
            app()->isProduction(),
        );

        Password::defaults(
            fn(): ?Password => app()->isProduction()
            ? Password::min(12)
                ->mixedCase()
                ->letters()
                ->numbers()
                ->symbols()
                ->uncompromised()
            : null,
        );
    }
}
