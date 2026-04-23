<?php

namespace App\Services;

use App\Models\SiteLanguage;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

class SiteLocaleManager
{
    private const ACTIVE_CACHE_KEY = 'cms.site_languages.active.v2';

    private const ALL_CACHE_KEY = 'cms.site_languages.all.v2';

    public function activeLanguages(): Collection
    {
        return $this->hydrateLanguages(
            $this->rememberRows(self::ACTIVE_CACHE_KEY, function (): array {
                if (! $this->languagesTableExists()) {
                    return [$this->fallbackLanguageRow()];
                }

                return SiteLanguage::query()
                    ->active()
                    ->orderByDesc('is_default')
                    ->orderBy('name')
                    ->get(['code', 'name', 'is_default', 'is_active'])
                    ->map(fn (SiteLanguage $language) => $this->languageRow($language))
                    ->all();
            }),
        );
    }

    public function allLanguages(): Collection
    {
        return $this->hydrateLanguages(
            $this->rememberRows(self::ALL_CACHE_KEY, function (): array {
                if (! $this->languagesTableExists()) {
                    return [$this->fallbackLanguageRow()];
                }

                return SiteLanguage::query()
                    ->orderByDesc('is_default')
                    ->orderBy('name')
                    ->get(['code', 'name', 'is_default', 'is_active'])
                    ->map(fn (SiteLanguage $language) => $this->languageRow($language))
                    ->all();
            }),
        );
    }

    public function defaultLocale(): string
    {
        return $this->activeLanguages()
            ->firstWhere('is_default', true)?->code
            ?? $this->activeLanguages()->first()?->code
            ?? $this->fallbackLocale();
    }

    public function currentLocale(?Request $request = null): string
    {
        $request ??= request();

        $locale = $this->normalizeLocale($request?->attributes->get('site_locale'));

        return $locale && $this->isConfiguredLocale($locale)
            ? $locale
            : $this->defaultLocale();
    }

    public function isConfiguredLocale(?string $locale): bool
    {
        $normalized = $this->normalizeLocale($locale);

        if ($normalized === null) {
            return false;
        }

        return $this->activeLanguages()->contains(
            fn (SiteLanguage $language) => $language->code === $normalized,
        );
    }

    public function isDefaultLocale(?string $locale): bool
    {
        return $this->normalizeLocale($locale) === $this->defaultLocale();
    }

    public function nonDefaultLanguages(): Collection
    {
        $defaultLocale = $this->defaultLocale();

        return $this->activeLanguages()
            ->reject(fn (SiteLanguage $language) => $language->code === $defaultLocale)
            ->values();
    }

    public function normalizeLocale(?string $locale): ?string
    {
        $locale = trim((string) $locale);

        if ($locale === '') {
            return null;
        }

        return Str::of($locale)
            ->replace('_', '-')
            ->lower()
            ->value();
    }

    public function pathForLocale(string $slug, ?string $locale = null, bool $isHomepage = false): string
    {
        $path = $this->normalizePath($isHomepage ? '/' : $slug);
        $normalizedLocale = $this->normalizeLocale($locale) ?? $this->defaultLocale();

        if ($this->isDefaultLocale($normalizedLocale)) {
            return $path;
        }

        if ($path === '/') {
            return '/'.$normalizedLocale;
        }

        return '/'.$normalizedLocale.$path;
    }

    public function flush(): void
    {
        Cache::forget('cms.site_languages.active');
        Cache::forget('cms.site_languages.all');
        Cache::forget(self::ACTIVE_CACHE_KEY);
        Cache::forget(self::ALL_CACHE_KEY);
    }

    private function normalizePath(?string $path): string
    {
        $trimmed = trim((string) $path);

        if ($trimmed === '' || $trimmed === '/') {
            return '/';
        }

        return '/'.trim($trimmed, '/');
    }

    private function languagesTableExists(): bool
    {
        try {
            return Schema::hasTable('site_languages');
        } catch (\Throwable) {
            return false;
        }
    }

    private function fallbackLocale(): string
    {
        return $this->normalizeLocale(config('app.locale', 'en')) ?? 'en';
    }

    /**
     * @param  callable(): array<int, array{code: string, name: string, is_default: bool, is_active: bool}>  $resolver
     * @return array<int, array{code: string, name: string, is_default: bool, is_active: bool}>
     */
    private function rememberRows(string $key, callable $resolver): array
    {
        $cached = Cache::get($key);

        if ($this->validRows($cached)) {
            return $cached;
        }

        Cache::forget($key);

        $rows = $resolver();
        Cache::forever($key, $rows);

        return $rows;
    }

    private function validRows(mixed $rows): bool
    {
        if (! is_array($rows)) {
            return false;
        }

        foreach ($rows as $row) {
            if (
                ! is_array($row)
                || ! is_string($row['code'] ?? null)
                || ! is_string($row['name'] ?? null)
                || ! is_bool($row['is_default'] ?? null)
                || ! is_bool($row['is_active'] ?? null)
            ) {
                return false;
            }
        }

        return true;
    }

    /**
     * @param  array<int, array{code: string, name: string, is_default: bool, is_active: bool}>  $rows
     * @return Collection<int, SiteLanguage>
     */
    private function hydrateLanguages(array $rows): Collection
    {
        return collect($rows)
            ->map(fn (array $row) => new SiteLanguage($row))
            ->values();
    }

    /**
     * @return array{code: string, name: string, is_default: bool, is_active: bool}
     */
    private function fallbackLanguageRow(): array
    {
        $locale = $this->fallbackLocale();

        return [
            'code' => $locale,
            'name' => strtoupper($locale),
            'is_default' => true,
            'is_active' => true,
        ];
    }

    /**
     * @return array{code: string, name: string, is_default: bool, is_active: bool}
     */
    private function languageRow(SiteLanguage $language): array
    {
        return [
            'code' => (string) $language->code,
            'name' => (string) $language->name,
            'is_default' => (bool) $language->is_default,
            'is_active' => (bool) $language->is_active,
        ];
    }
}
