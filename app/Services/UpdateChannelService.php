<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Throwable;

class UpdateChannelService
{
    private const CACHE_KEY = 'cms.updates.latest_release';

    /**
     * @return array{
     *     enabled: bool,
     *     currentVersion: string,
     *     latestVersion: string|null,
     *     releaseUrl: string|null,
     *     publishedAt: string|null,
     *     checkedAt: string|null,
     *     available: bool
     * }
     */
    public function report(): array
    {
        $currentVersion = $this->normalizeVersion((string) config('app.version', 'dev')) ?? 'dev';

        $base = [
            'enabled' => (bool) config('cms.updates.enabled', true),
            'currentVersion' => $currentVersion,
            'latestVersion' => null,
            'releaseUrl' => null,
            'publishedAt' => null,
            'checkedAt' => null,
            'available' => false,
        ];

        if (! $base['enabled'] || $currentVersion === 'dev') {
            return $base;
        }

        $latest = Cache::remember(
            self::CACHE_KEY,
            now()->addMinutes(max(1, (int) config('cms.updates.cache_ttl_minutes', 360))),
            fn (): array => $this->fetchLatestRelease()
        );

        $latestVersion = $this->normalizeVersion($latest['latestVersion'] ?? null);

        if (! $latestVersion) {
            return [
                ...$base,
                'checkedAt' => $latest['checkedAt'] ?? null,
            ];
        }

        return [
            ...$base,
            'latestVersion' => $latestVersion,
            'releaseUrl' => $this->releaseUrlForVersion($latestVersion, $latest['releaseUrl'] ?? null),
            'publishedAt' => $latest['publishedAt'] ?? null,
            'checkedAt' => $latest['checkedAt'] ?? null,
            'available' => version_compare($latestVersion, $currentVersion, '>'),
        ];
    }

    /**
     * @return array{latestVersion: string|null, releaseUrl: string|null, publishedAt: string|null, checkedAt: string}
     */
    private function fetchLatestRelease(): array
    {
        $checkedAt = now()->toIso8601String();

        try {
            $response = Http::acceptJson()
                ->withHeaders([
                    'User-Agent' => (string) config('app.name', 'Kavlo CMS').' Update Checker',
                ])
                ->timeout(max(1, (int) config('cms.updates.timeout_seconds', 5)))
                ->get((string) config('cms.updates.release_api_url'));

            if ($response->failed()) {
                return [
                    'latestVersion' => null,
                    'releaseUrl' => null,
                    'publishedAt' => null,
                    'checkedAt' => $checkedAt,
                ];
            }

            return [
                'latestVersion' => $response->json('tag_name') ?? $response->json('name'),
                'releaseUrl' => $response->json('html_url'),
                'publishedAt' => $response->json('published_at'),
                'checkedAt' => $checkedAt,
            ];
        } catch (Throwable $e) {
            report($e);

            return [
                'latestVersion' => null,
                'releaseUrl' => null,
                'publishedAt' => null,
                'checkedAt' => $checkedAt,
            ];
        }
    }

    private function normalizeVersion(?string $version): ?string
    {
        if (! is_string($version)) {
            return null;
        }

        $normalized = trim($version);

        if ($normalized === '') {
            return null;
        }

        return ltrim($normalized, 'vV');
    }

    private function releaseUrlForVersion(string $version, ?string $releaseUrl): string
    {
        $normalizedUrl = is_string($releaseUrl) ? trim($releaseUrl) : '';

        if ($normalizedUrl !== '') {
            return $normalizedUrl;
        }

        return rtrim((string) config('cms.updates.release_repository_url'), '/').'/tag/v'.$version;
    }
}
