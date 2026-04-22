<?php

namespace App\Plugins;

use Illuminate\Support\Str;
use RuntimeException;

class PluginManifest
{
    private const ALLOWED_SCOPE_OBJECTS = [
        'admin_nav',
        'datahub',
        'forms',
        'hooks',
        'http',
        'media',
        'migrations',
        'models',
        'storage',
    ];

    /**
     * @param  list<string>  $scopes
     * @param  list<string>  $migrationPaths
     * @param  array<int, array<string, mixed>>  $adminNav
     */
    public function __construct(
        public readonly string $slug,
        public readonly string $directory,
        public readonly ?string $declaredSlug,
        public readonly string $name,
        public readonly ?string $version,
        public readonly ?string $description,
        public readonly ?string $author,
        public readonly ?string $updateUrl,
        public readonly string $entrypoint,
        public readonly array $scopes,
        public readonly array $migrationPaths,
        public readonly ?string $modelNamespace,
        public readonly array $adminNav,
    ) {}

    /**
     * @param  array<string, mixed>  $config
     */
    public static function fromConfig(string $slug, string $directory, array $config): self
    {
        $name = trim((string) ($config['name'] ?? ''));
        $entrypoint = trim((string) ($config['entrypoint'] ?? ''));
        $legacyProvider = trim((string) ($config['provider'] ?? ''));
        $modelNamespace = trim((string) ($config['model_namespace'] ?? $config['models'] ?? ''));

        if ($name === '') {
            throw new RuntimeException("Plugin [{$slug}] is missing a name in plugin.json.");
        }

        if ($entrypoint === '') {
            if ($legacyProvider !== '') {
                throw new RuntimeException("Plugin [{$slug}] still uses a legacy service provider. Define an entrypoint class that implements App\\Contracts\\PluginBase.");
            }

            throw new RuntimeException("Plugin [{$slug}] is missing an entrypoint in plugin.json.");
        }

        $scopes = self::normalizeScopes($slug, $config['scopes'] ?? []);
        $migrationPaths = self::normalizePaths($slug, $config['migrations'] ?? $config['migration_paths'] ?? null);
        $adminNav = self::normalizeAdminNav($slug, $config['admin_nav'] ?? []);

        if ($migrationPaths !== [] && ! self::scopeInList($scopes, 'migrations:write')) {
            throw new RuntimeException("Plugin [{$slug}] declares migration paths without requesting the [migrations:write] scope.");
        }

        if ($modelNamespace !== '' && ! self::scopeInList($scopes, 'models:read')) {
            throw new RuntimeException("Plugin [{$slug}] declares a model namespace without requesting a [models:read] or [models:write] scope.");
        }

        if ($adminNav !== [] && ! self::scopeInList($scopes, 'admin_nav:write')) {
            throw new RuntimeException("Plugin [{$slug}] declares admin navigation without requesting the [admin_nav:write] scope.");
        }

        return new self(
            slug: $slug,
            directory: $directory,
            declaredSlug: self::nullableString($config['slug'] ?? null),
            name: $name,
            version: self::nullableString($config['version'] ?? null),
            description: self::nullableString($config['description'] ?? null),
            author: self::nullableString($config['author'] ?? null),
            updateUrl: self::nullableString($config['update_url'] ?? null),
            entrypoint: $entrypoint,
            scopes: $scopes,
            migrationPaths: $migrationPaths === [] ? ['database/migrations'] : $migrationPaths,
            modelNamespace: $modelNamespace !== '' ? $modelNamespace : null,
            adminNav: $adminNav,
        );
    }

    public function hasScope(string $scope): bool
    {
        $normalized = self::normalizeScopeValue($scope);

        if ($normalized === null) {
            return false;
        }

        if (in_array($normalized, $this->scopes, true)) {
            return true;
        }

        [$object, $action] = explode(':', $normalized, 2);

        return $action === 'read' && in_array($object.':write', $this->scopes, true);
    }

    /**
     * @param  list<string>  $scopes
     */
    public function hasAnyScope(array $scopes): bool
    {
        foreach ($scopes as $scope) {
            if ($this->hasScope($scope)) {
                return true;
            }
        }

        return false;
    }

    public function tablePrefix(): string
    {
        return 'plugin_'.Str::snake($this->slug).'_';
    }

    private static function nullableString(mixed $value): ?string
    {
        $string = trim((string) $value);

        return $string !== '' ? $string : null;
    }

    /**
     * @return list<string>
     */
    private static function normalizeScopes(string $slug, mixed $scopes): array
    {
        if (! is_array($scopes)) {
            throw new RuntimeException("Plugin [{$slug}] must declare scopes as an array.");
        }

        return collect($scopes)
            ->filter(fn ($scope) => is_string($scope) && trim($scope) !== '')
            ->map(function (string $scope) use ($slug) {
                $normalized = self::normalizeScopeValue($scope);

                if ($normalized === null) {
                    throw new RuntimeException("Plugin [{$slug}] declares an invalid scope [{$scope}]. Use the <object>:<read|write> format.");
                }

                [$object, $action] = explode(':', $normalized, 2);

                if (! in_array($object, self::ALLOWED_SCOPE_OBJECTS, true)) {
                    throw new RuntimeException("Plugin [{$slug}] requests unsupported scope object [{$object}].");
                }

                if (! in_array($action, ['read', 'write'], true)) {
                    throw new RuntimeException("Plugin [{$slug}] requests unsupported scope action [{$action}].");
                }

                return $normalized;
            })
            ->unique()
            ->values()
            ->all();
    }

    private static function normalizeScopeValue(string $scope): ?string
    {
        $normalized = strtolower(trim($scope));

        if ($normalized === '') {
            return null;
        }

        if (preg_match('/^([a-z][a-z0-9_]*):(read|write)$/', $normalized) !== 1) {
            return null;
        }

        return $normalized;
    }

    /**
     * @return list<string>
     */
    private static function normalizePaths(string $slug, mixed $paths): array
    {
        if ($paths === null || $paths === '') {
            return [];
        }

        if (is_string($paths)) {
            return [trim($paths)];
        }

        if (! is_array($paths)) {
            throw new RuntimeException("Plugin [{$slug}] has invalid migration path configuration.");
        }

        return collect($paths)
            ->filter(fn ($path) => is_string($path) && trim($path) !== '')
            ->map(fn (string $path) => trim($path))
            ->values()
            ->all();
    }

    /**
     * @param  list<string>  $scopes
     */
    private static function scopeInList(array $scopes, string $requiredScope): bool
    {
        if (in_array($requiredScope, $scopes, true)) {
            return true;
        }

        [$object, $action] = explode(':', $requiredScope, 2);

        return $action === 'read' && in_array($object.':write', $scopes, true);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private static function normalizeAdminNav(string $slug, mixed $items): array
    {
        if ($items === null || $items === []) {
            return [];
        }

        if (! is_array($items)) {
            throw new RuntimeException("Plugin [{$slug}] has invalid admin_nav configuration.");
        }

        foreach ($items as $item) {
            if (! is_array($item)) {
                throw new RuntimeException("Plugin [{$slug}] has invalid admin_nav configuration.");
            }
        }

        return array_values($items);
    }
}
