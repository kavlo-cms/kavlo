<?php

namespace App\Http\Middleware;

use App\Services\SiteLocaleManager;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class SetSiteLocale
{
    public function __construct(
        private readonly SiteLocaleManager $locales,
    ) {}

    public function handle(Request $request, Closure $next): Response
    {
        $candidate = $this->locales->normalizeLocale($request->route('locale'));
        $locale = $candidate && $this->locales->isConfiguredLocale($candidate)
            ? $candidate
            : $this->locales->defaultLocale();

        $request->attributes->set('site_locale', $locale);
        app()->setLocale(str_replace('-', '_', $locale));

        return $next($request);
    }
}
