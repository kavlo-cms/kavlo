<?php

namespace App\Services;

use App\Contracts\PluginBase;
use App\Models\Plugin;
use App\Plugins\PluginContext;
use App\Plugins\PluginManifest;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use PharData;
use RecursiveIteratorIterator;
use ReflectionClass;
use RuntimeException;
use ZipArchive;

class PluginManager
{
    public function __construct(
        private readonly HookManager $hooks,
    ) {}

    /**
     * Check whether a plugin is currently enabled.
     */
    public function isEnabled(string $slug): bool
    {
        try {
            return Plugin::where('slug', $slug)->value('is_enabled') ?? false;
        } catch (\Throwable) {
            return false;
        }
    }

    /**
     * Discover plugins on disk, sync to DB, and boot enabled ones.
     */
    public function bootPlugins(): void
    {
        $pluginsPath = base_path('plugins');
        if (! File::exists($pluginsPath)) {
            return;
        }

        // Read which slugs are enabled from DB — skip entirely if DB isn't ready yet
        try {
            $enabledSlugs = Plugin::where('is_enabled', true)->pluck('slug')->flip()->toArray();
        } catch (\Throwable) {
            return;
        }

        foreach (File::directories($pluginsPath) as $directory) {
            $manifest = $this->readManifestFromDirectory($directory, swallowInvalid: true);
            if (! $manifest instanceof PluginManifest) {
                continue;
            }

            if (isset($enabledSlugs[$manifest->slug])) {
                try {
                    $this->bootPlugin($manifest);
                } catch (\Throwable $exception) {
                    Plugin::where('slug', $manifest->slug)->update(['is_enabled' => false]);
                    Log::warning('Plugin boot failed and was disabled.', [
                        'plugin' => $manifest->slug,
                        'error' => $exception->getMessage(),
                    ]);
                }
            }
        }
    }

    public function enable(Plugin $plugin): void
    {
        $directory = $this->pluginDirectory($plugin->slug);
        $manifest = $this->readManifestFromDirectory($directory);
        $this->guardPluginSources($manifest);
        $this->runMigrations($manifest);
        $this->bootPlugin($manifest);

        $plugin->forceFill(['is_enabled' => true])->save();
    }

    public function disable(Plugin $plugin): void
    {
        $plugin->forceFill(['is_enabled' => false])->save();
    }

    /**
     * @return list<PluginManifest>
     */
    public function enabledManifests(): array
    {
        try {
            $slugs = Plugin::query()
                ->where('is_enabled', true)
                ->orderBy('slug')
                ->pluck('slug')
                ->all();
        } catch (\Throwable) {
            return [];
        }

        return collect($slugs)
            ->map(fn (string $slug) => $this->readManifestFromDirectory($this->pluginDirectory($slug), swallowInvalid: true))
            ->filter(fn (mixed $manifest) => $manifest instanceof PluginManifest)
            ->values()
            ->all();
    }

    /**
     * @return list<string>
     */
    public function migrationPathsForManifest(PluginManifest $manifest): array
    {
        return $this->migrationPaths($manifest);
    }

    public function installFromArchive(UploadedFile $archive): Plugin
    {
        $tempDir = storage_path('app/tmp/plugin-upload-'.Str::uuid());
        File::ensureDirectoryExists($tempDir);

        $originalName = $archive->getClientOriginalName();
        $archiveName = preg_replace('/[^A-Za-z0-9._-]/', '-', $originalName) ?: 'plugin-archive';
        $archivePath = $archive->move($tempDir, $archiveName)->getRealPath();
        $extractDir = $tempDir.'/extracted';
        File::ensureDirectoryExists($extractDir);

        try {
            $this->extractArchive($archivePath, $originalName, $extractDir);

            [$packageRoot, $manifest] = $this->locatePluginPackage($extractDir);
            $installDirectory = $packageRoot === $extractDir
                ? $this->deriveInstallDirectoryName($manifest, $originalName)
                : basename($packageRoot);

            $destination = base_path('plugins/'.$installDirectory);
            if (File::exists($destination)) {
                throw new RuntimeException("A plugin directory named [{$installDirectory}] already exists.");
            }

            File::ensureDirectoryExists(base_path('plugins'));

            if (! File::moveDirectory($packageRoot, $destination)) {
                throw new RuntimeException('The plugin archive could not be installed.');
            }

            $plugin = $this->discover()->firstWhere('slug', $installDirectory);

            if (! $plugin instanceof Plugin) {
                throw new RuntimeException('The plugin was extracted but could not be discovered.');
            }

            return $plugin;
        } finally {
            File::deleteDirectory($tempDir);
        }
    }

    /**
     * Scan plugins dir and upsert into DB without booting — used by the admin UI.
     *
     * @return Collection<int, Plugin>
     */
    public function discover(): Collection
    {
        $pluginsPath = base_path('plugins');

        if (! File::exists($pluginsPath)) {
            return Plugin::orderBy('name')->get();
        }

        $foundSlugs = [];
        $pluginScopes = [];
        $pluginUpdateUrls = [];

        foreach (File::directories($pluginsPath) as $directory) {
            $manifest = $this->readManifestFromDirectory($directory, swallowInvalid: true);
            if (! $manifest instanceof PluginManifest) {
                continue;
            }

            $slug = basename($directory);
            $foundSlugs[] = $slug;
            $pluginScopes[$slug] = $manifest->scopes;
            $pluginUpdateUrls[$slug] = $manifest->updateUrl;

            Plugin::updateOrCreate(
                ['slug' => $slug],
                [
                    'name' => $manifest->name,
                    'version' => $manifest->version,
                    'description' => $manifest->description,
                    'author' => $manifest->author,
                ]
            );
        }

        // Remove DB records for plugins no longer on disk
        Plugin::whereNotIn('slug', $foundSlugs)->delete();

        return Plugin::orderBy('name')
            ->get()
            ->each(function (Plugin $plugin) use ($pluginScopes, $pluginUpdateUrls): void {
                $plugin->setAttribute('scopes', $pluginScopes[$plugin->slug] ?? []);
                $plugin->setAttribute('update_url', $pluginUpdateUrls[$plugin->slug] ?? null);
            });
    }

    private function bootPlugin(PluginManifest $manifest): void
    {
        $plugin = $this->resolveEntrypoint($manifest);

        if ($manifest->adminNav !== []) {
            (new PluginContext($manifest, $this->hooks))->registerAdminNav($manifest->adminNav);
        }

        $plugin->boot(new PluginContext($manifest, $this->hooks));
    }

    private function resolveEntrypoint(PluginManifest $manifest): PluginBase
    {
        if (! class_exists($manifest->entrypoint)) {
            $this->loadPluginPhpFiles($manifest);
        }

        if (! class_exists($manifest->entrypoint)) {
            throw new RuntimeException("Plugin [{$manifest->slug}] entrypoint [{$manifest->entrypoint}] could not be loaded.");
        }

        $reflection = new ReflectionClass($manifest->entrypoint);

        if (! $reflection->implementsInterface(PluginBase::class)) {
            throw new RuntimeException("Plugin [{$manifest->slug}] entrypoint must implement App\\Contracts\\PluginBase.");
        }

        $constructor = $reflection->getConstructor();

        if ($constructor !== null && $constructor->getNumberOfRequiredParameters() > 0) {
            throw new RuntimeException("Plugin [{$manifest->slug}] entrypoint constructor must not require arguments.");
        }

        return $reflection->newInstance();
    }

    private function loadPluginPhpFiles(PluginManifest $manifest): void
    {
        foreach (File::allFiles($manifest->directory) as $file) {
            if ($file->getExtension() !== 'php') {
                continue;
            }

            if (str_contains(str_replace('\\', '/', $file->getRealPath()), '/database/migrations/')) {
                continue;
            }

            require_once $file->getRealPath();
        }
    }

    private function runMigrations(PluginManifest $manifest): void
    {
        if (! $manifest->hasScope('migrations:write')) {
            return;
        }

        $paths = collect($this->migrationPaths($manifest))
            ->filter(fn (string $path) => File::isDirectory($path))
            ->values()
            ->all();

        foreach ($paths as $path) {
            $this->validateMigrationDirectory($manifest, $path);
        }

        foreach ($paths as $path) {
            Artisan::call('migrate', [
                '--force' => true,
                '--path' => $path,
                '--realpath' => true,
            ]);
        }
    }

    /**
     * @return list<string>
     */
    private function migrationPaths(PluginManifest $manifest): array
    {
        return collect($manifest->migrationPaths)
            ->map(fn (string $path) => $this->normalizePluginPath($manifest->directory, $path))
            ->values()
            ->all();
    }

    private function normalizePluginPath(string $directory, string $path): string
    {
        if (str_starts_with($path, DIRECTORY_SEPARATOR) || preg_match('/^[A-Za-z]:[\\\\\\/]/', $path) === 1) {
            throw new RuntimeException('Plugin migration paths must stay inside the plugin directory.');
        }

        $resolved = realpath($directory.'/'.ltrim($path, '/'));

        if ($resolved === false) {
            return $directory.'/'.ltrim($path, '/');
        }

        $pluginRoot = realpath($directory) ?: $directory;

        if (! str_starts_with($resolved, $pluginRoot)) {
            throw new RuntimeException('Plugin paths must stay inside the plugin directory.');
        }

        return $resolved;
    }

    private function readConfigFromDirectory(string $directory): ?array
    {
        $configPath = $directory.'/plugin.json';

        return app(PluginConfigManifest::class)->loadFromPath(
            $configPath,
            pluginSlug: basename($directory),
        );
    }

    private function readManifestFromDirectory(string $directory, bool $swallowInvalid = false): ?PluginManifest
    {
        $config = app(PluginConfigManifest::class)->loadFromPath(
            $directory.'/plugin.json',
            swallowInvalid: $swallowInvalid,
            pluginSlug: basename($directory),
        );

        if ($config === null) {
            return null;
        }

        try {
            return PluginManifest::fromConfig(basename($directory), $directory, $config);
        } catch (RuntimeException $exception) {
            if ($swallowInvalid) {
                return null;
            }

            throw $exception;
        }
    }

    private function pluginDirectory(string $slug): string
    {
        return base_path('plugins/'.$slug);
    }

    private function extractArchive(string $archivePath, string $originalName, string $extractDir): void
    {
        $name = Str::lower($originalName);

        if (str_ends_with($name, '.zip')) {
            $this->extractZipArchive($archivePath, $extractDir);

            return;
        }

        if (str_ends_with($name, '.tar') || str_ends_with($name, '.tar.gz') || str_ends_with($name, '.tgz')) {
            $this->extractTarArchive($archivePath, $extractDir);

            return;
        }

        throw new RuntimeException('Unsupported archive type. Upload a .zip, .tar, .tar.gz, or .tgz plugin package.');
    }

    private function extractZipArchive(string $archivePath, string $extractDir): void
    {
        if (! class_exists(ZipArchive::class)) {
            throw new RuntimeException('ZIP extraction is not available on this server.');
        }

        $zip = new ZipArchive;
        $result = $zip->open($archivePath);

        if ($result !== true) {
            throw new RuntimeException('The ZIP archive could not be opened.');
        }

        try {
            for ($index = 0; $index < $zip->numFiles; $index++) {
                $entryName = $zip->getNameIndex($index);
                $relativePath = $this->sanitizeArchivePath($entryName);

                if ($relativePath === null) {
                    continue;
                }

                $destination = $extractDir.'/'.$relativePath;

                if (str_ends_with($entryName, '/')) {
                    File::ensureDirectoryExists($destination);

                    continue;
                }

                $stream = $zip->getStream($entryName);

                if ($stream === false) {
                    throw new RuntimeException("Failed to read archive entry [{$entryName}].");
                }

                File::ensureDirectoryExists(dirname($destination));
                File::put($destination, stream_get_contents($stream));
                fclose($stream);
            }
        } finally {
            $zip->close();
        }
    }

    private function extractTarArchive(string $archivePath, string $extractDir): void
    {
        $pharPath = $archivePath;
        $lowerPath = Str::lower($archivePath);

        if (str_ends_with($lowerPath, '.tar.gz') || str_ends_with($lowerPath, '.tgz')) {
            $decompressed = new PharData($archivePath);
            $decompressed->decompress();
            $pharPath = preg_replace('/\.(tar\.gz|tgz)$/i', '.tar', $archivePath) ?: $archivePath;
        }

        $archive = new PharData($pharPath);
        $prefix = 'phar://'.str_replace('\\', '/', $pharPath).'/';

        foreach (new RecursiveIteratorIterator($archive) as $file) {
            $pathName = str_replace('\\', '/', $file->getPathname());
            $relativePath = $this->sanitizeArchivePath(str_starts_with($pathName, $prefix)
                ? substr($pathName, strlen($prefix))
                : $file->getFilename());

            if ($relativePath === null || $file->isDir()) {
                continue;
            }

            $destination = $extractDir.'/'.$relativePath;
            File::ensureDirectoryExists(dirname($destination));
            File::put($destination, file_get_contents($file->getPathname()));
        }
    }

    private function sanitizeArchivePath(string $path): ?string
    {
        $normalized = str_replace('\\', '/', trim($path));
        $normalized = ltrim($normalized, '/');

        if ($normalized === '') {
            return null;
        }

        if (preg_match('/^[A-Za-z]:\//', $normalized) === 1) {
            throw new RuntimeException('Archive contains an invalid file path.');
        }

        $segments = [];

        foreach (explode('/', $normalized) as $segment) {
            if ($segment === '' || $segment === '.') {
                continue;
            }

            if ($segment === '..') {
                throw new RuntimeException('Archive contains an unsafe file path.');
            }

            $segments[] = $segment;
        }

        return $segments === [] ? null : implode('/', $segments);
    }

    /**
     * @return array{0:string,1:PluginManifest}
     */
    private function locatePluginPackage(string $extractDir): array
    {
        $manifests = collect(File::allFiles($extractDir))
            ->filter(fn ($file) => $file->getFilename() === 'plugin.json')
            ->values();

        if ($manifests->isEmpty()) {
            throw new RuntimeException('The uploaded archive does not contain a plugin.json manifest.');
        }

        if ($manifests->count() > 1) {
            throw new RuntimeException('The uploaded archive contains multiple plugin.json files. Upload one plugin package at a time.');
        }

        $manifest = $manifests->first();
        $packageRoot = dirname($manifest->getRealPath());
        $pluginManifest = $this->readManifestFromDirectory($packageRoot);

        if (! $pluginManifest instanceof PluginManifest) {
            throw new RuntimeException('The uploaded plugin manifest could not be read.');
        }

        return [$packageRoot, $pluginManifest];
    }

    private function deriveInstallDirectoryName(PluginManifest $manifest, string $originalName): string
    {
        if ($manifest->declaredSlug !== null) {
            return $manifest->declaredSlug;
        }

        if ($manifest->slug !== '') {
            return $manifest->slug;
        }

        $baseName = preg_replace('/\.(tar\.gz|tgz|tar|zip)$/i', '', $originalName) ?: 'plugin';

        return preg_replace('/[^A-Za-z0-9._-]/', '-', $baseName) ?: 'plugin';
    }

    private function guardPluginSources(PluginManifest $manifest): void
    {
        foreach (File::allFiles($manifest->directory) as $file) {
            if ($file->getExtension() !== 'php') {
                continue;
            }

            $contents = File::get($file->getRealPath());

            if (preg_match('/\bapp\s*\(\)\s*->\s*register\s*\(/i', $contents) === 1) {
                throw new RuntimeException("Plugin [{$manifest->slug}] uses service-provider registration, which is not allowed in the constrained runtime.");
            }

            if (preg_match('/\bDB::(statement|unprepared)\s*\(/i', $contents) === 1) {
                throw new RuntimeException("Plugin [{$manifest->slug}] uses raw SQL APIs that are not allowed in the constrained runtime.");
            }
        }
    }

    private function validateMigrationDirectory(PluginManifest $manifest, string $path): void
    {
        foreach (File::files($path) as $file) {
            if ($file->getExtension() !== 'php') {
                continue;
            }

            $this->validateMigrationFile($manifest, $file->getRealPath());
        }
    }

    private function validateMigrationFile(PluginManifest $manifest, string $path): void
    {
        $contents = File::get($path);

        if (preg_match('/\bextends\s+(?:\\\\?App\\\\Plugins\\\\PluginMigration|PluginMigration)\b/', $contents) !== 1) {
            throw new RuntimeException("Plugin [{$manifest->slug}] migration [".basename($path).'] must extend App\\Plugins\\PluginMigration.');
        }

        if (preg_match('/\bDB::(statement|unprepared|select|insert|update|delete)\s*\(/i', $contents) === 1) {
            throw new RuntimeException("Plugin [{$manifest->slug}] migration [".basename($path).'] uses raw SQL, which is not allowed.');
        }

        if (preg_match('/\bSchema::drop(AllTables|AllViews|DatabaseIfExists)\s*\(/i', $contents) === 1) {
            throw new RuntimeException("Plugin [{$manifest->slug}] migration [".basename($path).'] uses destructive schema operations that are not allowed.');
        }

        if (preg_match('/\bSchema::(?:create|table|dropIfExists|rename)\s*\(/i', $contents) === 1) {
            throw new RuntimeException("Plugin [{$manifest->slug}] migration [".basename($path).'] must use PluginMigration table helpers instead of direct Schema calls.');
        }
    }
}
