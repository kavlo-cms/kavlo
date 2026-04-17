<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

class Redirect extends Model
{
    protected $fillable = [
        'from_url',
        'to_url',
        'type',
        'is_active',
    ];

    protected $casts = [
        'is_active'   => 'boolean',
        'hits'        => 'integer',
        'type'        => 'integer',
        'last_hit_at' => 'datetime',
    ];

    /**
     * Normalize a URL path for consistent matching.
     * Lowercases, strips query string, ensures leading slash, strips trailing slash.
     */
    public static function normalizePath(string $path): string
    {
        $path = strtolower(parse_url($path, PHP_URL_PATH) ?? $path);
        $path = '/' . ltrim($path, '/');

        return rtrim($path, '/') ?: '/';
    }

    /**
     * Find an active redirect matching the given request path.
     * Uses a cache keyed on the path so repeated hits are near-zero cost.
     */
    public static function findForPath(string $path): ?self
    {
        $normalized = self::normalizePath($path);

        return Cache::rememberForever('redirect:' . $normalized, function () use ($normalized) {
            return self::where('from_url', $normalized)
                ->where('is_active', true)
                ->first();
        });
    }

    /**
     * Flush the redirect lookup cache entry for this record.
     */
    public function flushCache(): void
    {
        Cache::forget('redirect:' . self::normalizePath($this->from_url));
    }
}
