<?php

namespace App\Services;

use Closure;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

class MenuRenderCache
{
    private const VERSION_CACHE_KEY = 'cms.menus.render.version';

    public function remember(string $slug, array $options, Closure $callback): string
    {
        $key = implode(':', [
            'cms',
            'menu',
            $this->version(),
            trim($slug),
            md5(json_encode($options, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?: '[]'),
        ]);

        $cached = Cache::get($key);

        if (is_string($cached)) {
            return $cached;
        }

        $html = (string) $callback();
        Cache::put($key, $html, max(60, (int) config('cms.cache.menu_html_ttl_seconds', 3600)));

        return $html;
    }

    public function flush(): void
    {
        Cache::forever(self::VERSION_CACHE_KEY, Str::uuid()->toString());
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
}
