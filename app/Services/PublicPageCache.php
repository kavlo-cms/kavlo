<?php

namespace App\Services;

use App\Models\Page;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

class PublicPageCache
{
    private const VERSION_CACHE_KEY = 'cms.public_pages.version';

    private const LAST_FLUSHED_AT_CACHE_KEY = 'cms.public_pages.last_flushed_at';

    public function shouldCache(Request $request): bool
    {
        return $this->isEnabled()
            && $request->isMethod('GET')
            && ! $request->ajax()
            && ! $request->expectsJson()
            && $request->user() === null
            && $request->query->count() === 0;
    }

    /**
     * @return array{html: string, status: 'hit'|'miss'}
     */
    public function remember(Page $page, string $themeSlug, string $view, Closure $callback): array
    {
        $key = $this->key($page, $themeSlug, $view);
        $cached = Cache::get($key);

        if (is_string($cached)) {
            return [
                'html' => $cached,
                'status' => 'hit',
            ];
        }

        $html = (string) $callback();
        Cache::put($key, $html, $this->ttlSeconds());

        return [
            'html' => $html,
            'status' => 'miss',
        ];
    }

    public function flush(): void
    {
        Cache::forever(self::VERSION_CACHE_KEY, Str::uuid()->toString());
        Cache::forever(self::LAST_FLUSHED_AT_CACHE_KEY, now()->toIso8601String());
    }

    /**
     * @return array{enabled: bool, ttl_seconds: int, last_flushed_at: ?string, cache_scope: string}
     */
    public function status(): array
    {
        $lastFlushedAt = Cache::get(self::LAST_FLUSHED_AT_CACHE_KEY);

        return [
            'enabled' => $this->isEnabled(),
            'ttl_seconds' => $this->ttlSeconds(),
            'last_flushed_at' => is_string($lastFlushedAt) ? $lastFlushedAt : null,
            'cache_scope' => 'Guest GET page requests without query strings',
        ];
    }

    private function isEnabled(): bool
    {
        return (bool) config('cms.cache.public_pages.enabled', true);
    }

    private function ttlSeconds(): int
    {
        return max(30, (int) config('cms.cache.public_pages.ttl_seconds', 300));
    }

    private function version(): string
    {
        $version = Cache::get(self::VERSION_CACHE_KEY);

        if (is_string($version) && $version !== '') {
            return $version;
        }

        $version = Str::uuid()->toString();
        Cache::forever(self::VERSION_CACHE_KEY, $version);

        return $version;
    }

    private function key(Page $page, string $themeSlug, string $view): string
    {
        return implode(':', [
            'cms',
            'public-page',
            $this->version(),
            (string) $page->getKey(),
            $themeSlug,
            md5($view.'|'.$page->slug),
        ]);
    }
}
