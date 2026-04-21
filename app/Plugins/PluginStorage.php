<?php

namespace App\Plugins;

use App\Services\KavloStorage;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

class PluginStorage
{
    public function __construct(
        private readonly PluginManifest $manifest,
        private readonly KavloStorage $storage,
    ) {}

    public function root(string $visibility = 'private'): string
    {
        $this->requireReadAccess();

        if ($visibility !== 'public') {
            return storage_path('app/plugins/'.$this->manifest->slug);
        }

        $root = $this->storage->publicRoot();

        return ($root ?: storage_path('app/public')).'/plugins/'.$this->manifest->slug;
    }

    public function path(string $path = '', string $visibility = 'private'): string
    {
        $normalized = $this->normalizePath($path);

        return $normalized === ''
            ? $this->root($visibility)
            : $this->root($visibility).'/'.$normalized;
    }

    public function put(string $path, string $contents, string $visibility = 'private'): void
    {
        $this->requireWriteAccess();
        Storage::disk($this->disk($visibility))->put($this->relativePath($path), $contents);
    }

    public function get(string $path, string $visibility = 'private'): ?string
    {
        $this->requireReadAccess();
        $disk = Storage::disk($this->disk($visibility));
        $relativePath = $this->relativePath($path);

        return $disk->exists($relativePath) ? $disk->get($relativePath) : null;
    }

    public function exists(string $path, string $visibility = 'private'): bool
    {
        $this->requireReadAccess();

        return Storage::disk($this->disk($visibility))->exists($this->relativePath($path));
    }

    public function delete(string $path, string $visibility = 'private'): bool
    {
        $this->requireWriteAccess();

        return Storage::disk($this->disk($visibility))->delete($this->relativePath($path));
    }

    /**
     * @return list<string>
     */
    public function files(string $path = '', string $visibility = 'private'): array
    {
        $this->requireReadAccess();

        return collect(Storage::disk($this->disk($visibility))
            ->files($this->relativePath($path)))
            ->values()
            ->map(fn (string $file) => $this->stripPrefix($file))
            ->all();
    }

    public function makeDirectory(string $path = '', string $visibility = 'private'): void
    {
        $this->requireWriteAccess();
        Storage::disk($this->disk($visibility))->makeDirectory($this->relativePath($path));
    }

    public function url(string $path): string
    {
        $this->requireReadAccess();

        return $this->storage->publicUrl($this->relativePath($path));
    }

    private function disk(string $visibility): string
    {
        return $visibility === 'public' ? $this->storage->publicDiskName() : 'local';
    }

    private function relativePath(string $path): string
    {
        $normalized = $this->normalizePath($path);
        $base = 'plugins/'.$this->manifest->slug;

        return $normalized === '' ? $base : $base.'/'.$normalized;
    }

    private function stripPrefix(string $path): string
    {
        return preg_replace('/^plugins\/'.preg_quote($this->manifest->slug, '/').'\/?/', '', $path) ?: $path;
    }

    private function normalizePath(string $path): string
    {
        $normalized = ltrim(str_replace('\\', '/', trim($path)), '/');

        if ($normalized === '') {
            return '';
        }

        if (str_contains($normalized, '../')) {
            throw new RuntimeException('Plugin storage paths must stay inside the plugin storage directory.');
        }

        return $normalized;
    }

    private function requireReadAccess(): void
    {
        if (! $this->manifest->hasScope('storage:read')) {
            throw new RuntimeException("Plugin [{$this->manifest->slug}] requires the [storage:read] scope.");
        }
    }

    private function requireWriteAccess(): void
    {
        if (! $this->manifest->hasScope('storage:write')) {
            throw new RuntimeException("Plugin [{$this->manifest->slug}] requires the [storage:write] scope.");
        }
    }
}
