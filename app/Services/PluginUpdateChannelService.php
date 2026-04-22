<?php

namespace App\Services;

use App\Models\Plugin;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Throwable;

class PluginUpdateChannelService
{
    /**
     * @param  Collection<int, Plugin>  $plugins
     * @return array<string, array{
     *     enabled: bool,
     *     currentVersion: string|null,
     *     latestVersion: string|null,
     *     releaseUrl: string|null,
     *     checkedAt: string|null,
     *     available: bool
     * }>
     */
    public function reportsFor(Collection $plugins): array
    {
        return $plugins
            ->mapWithKeys(fn (Plugin $plugin) => [$plugin->slug => $this->reportFor($plugin)])
            ->all();
    }

    /**
     * @return array{
     *     enabled: bool,
     *     currentVersion: string|null,
     *     latestVersion: string|null,
     *     releaseUrl: string|null,
     *     checkedAt: string|null,
     *     available: bool
     * }
     */
    public function reportFor(Plugin $plugin): array
    {
        $currentVersion = $this->normalizeVersion($plugin->version);
        $updateUrl = $plugin->getAttribute('update_url');

        $base = [
            'enabled' => is_string($updateUrl) && trim($updateUrl) !== '' && $currentVersion !== null,
            'currentVersion' => $currentVersion,
            'latestVersion' => null,
            'releaseUrl' => null,
            'checkedAt' => null,
            'available' => false,
        ];

        if (! $base['enabled']) {
            return $base;
        }

        $latest = Cache::remember(
            $this->cacheKey($plugin->slug),
            now()->addMinutes(max(1, (int) config('cms.updates.plugin_cache_ttl_minutes', 360))),
            fn (): array => $this->fetchLatestRelease((string) $updateUrl)
        );

        $latestVersion = $this->normalizeVersion($latest['latestVersion'] ?? null);

        if ($latestVersion === null || $currentVersion === null) {
            return [
                ...$base,
                'checkedAt' => $latest['checkedAt'] ?? null,
            ];
        }

        return [
            ...$base,
            'latestVersion' => $latestVersion,
            'releaseUrl' => $this->normalizeUrl($latest['releaseUrl'] ?? null),
            'checkedAt' => $latest['checkedAt'] ?? null,
            'available' => version_compare($latestVersion, $currentVersion, '>'),
        ];
    }

    /**
     * @return array{latestVersion: string|null, releaseUrl: string|null, checkedAt: string}
     */
    private function fetchLatestRelease(string $updateUrl): array
    {
        $checkedAt = now()->toIso8601String();

        try {
            $response = Http::acceptJson()
                ->withHeaders([
                    'User-Agent' => (string) config('app.name', 'Kavlo CMS').' Plugin Update Checker',
                ])
                ->timeout(max(1, (int) config('cms.updates.timeout_seconds', 5)))
                ->get($updateUrl);

            if ($response->failed()) {
                return [
                    'latestVersion' => null,
                    'releaseUrl' => null,
                    'checkedAt' => $checkedAt,
                ];
            }

            return [
                'latestVersion' => $response->json('version')
                    ?? $response->json('tag_name')
                    ?? $response->json('name'),
                'releaseUrl' => $response->json('html_url')
                    ?? $response->json('release_url')
                    ?? $response->json('url'),
                'checkedAt' => $checkedAt,
            ];
        } catch (Throwable $e) {
            report($e);

            return [
                'latestVersion' => null,
                'releaseUrl' => null,
                'checkedAt' => $checkedAt,
            ];
        }
    }

    private function cacheKey(string $slug): string
    {
        return 'cms.plugins.updates.'.strtolower($slug);
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

    private function normalizeUrl(?string $url): ?string
    {
        if (! is_string($url)) {
            return null;
        }

        $normalized = trim($url);

        return $normalized !== '' ? $normalized : null;
    }
}
