<?php

namespace App\Providers;

use App\Models\ApiKey;
use App\Models\Setting;
use App\Services\ContentRouteRegistry;
use App\Services\HookManager;
use App\Services\MenuWalker;
use App\Services\PluginManager;
use App\Services\SitemapRegistry;
use Carbon\CarbonImmutable;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->singleton(HookManager::class);
        $this->app->singleton(PluginManager::class);

        $this->app->singleton(MenuWalker::class, function ($app) {
            return new MenuWalker;
        });

        $this->app->singleton(ContentRouteRegistry::class, function ($app) {
            return new ContentRouteRegistry;
        });

    }

    /**
     * Bootstrap any application services.
     */
    public function boot(SitemapRegistry $registry): void
    {
        app(PluginManager::class)->bootPlugins();

        // super-admin bypasses all Gate/permission checks
        Gate::before(function ($user, $ability) {
            return $user->hasRole('super-admin') ? true : null;
        });

        // Register theme error pages
        $this->registerThemeErrorPages();

        $this->configureDefaults();
        $this->configureRateLimiting();
        $this->configureMailFromSettings();
        View::addNamespace('core', resource_path('views/core'));
        $registry->addCollector(fn () => app(ContentRouteRegistry::class)->publishedPageUrls());
    }

    protected function registerThemeErrorPages(): void
    {
        try {
            $themeSlug = Setting::get('active_theme_slug', 'midnight-blue');
            $errorsPath = base_path("themes/{$themeSlug}/views/errors");

            if (is_dir($errorsPath)) {
                View::addNamespace('errors', $errorsPath);
            }
        } catch (\Exception $e) {
            // DB not ready (e.g. during migrations/tests) — keep default error views.
        }
    }

    protected function configureMailFromSettings(): void
    {
        try {
            $mailer = Setting::get('mail_mailer');
            $encryption = Setting::get('mail_encryption', 'tls');

            config([
                'mail.default' => $mailer ?: config('mail.default'),
                'mail.mailers.smtp.host' => Setting::get('mail_host', config('mail.mailers.smtp.host')),
                'mail.mailers.smtp.port' => (int) Setting::get('mail_port', 587),
                'mail.mailers.smtp.username' => Setting::get('mail_username'),
                'mail.mailers.smtp.password' => Setting::get('mail_password'),
                'mail.mailers.smtp.scheme' => $encryption === 'none' ? null : $encryption,
                'mail.from.address' => Setting::get('mail_from_address', config('mail.from.address')),
                'mail.from.name' => Setting::get('mail_from_name', config('mail.from.name')),
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
            fn (): ?Password => app()->isProduction()
            ? Password::min(12)
                ->mixedCase()
                ->letters()
                ->numbers()
                ->symbols()
                ->uncompromised()
            : null,
        );
    }

    protected function configureRateLimiting(): void
    {
        RateLimiter::for('graphql', function (Request $request) {
            $apiKey = $request->attributes->get('current_api_key');

            if ($apiKey instanceof ApiKey) {
                return Limit::perMinute(max(1, (int) config('cms.api_keys.graphql_rate_limit_per_minute', 120)))
                    ->by('api-key:'.$apiKey->id);
            }

            if ($request->user()) {
                return Limit::perMinute(max(1, (int) config('cms.graphql.authenticated_rate_limit_per_minute', 240)))
                    ->by('user:'.$request->user()->getAuthIdentifier());
            }

            return Limit::perMinute(max(1, (int) config('cms.graphql.guest_rate_limit_per_minute', 60)))
                ->by('ip:'.$request->ip());
        });
    }
}
