<?php

namespace App\Services;

use App\Models\Plugin;
use App\Models\Setting;
use App\Models\Theme;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use RuntimeException;
use SplFileInfo;
use ZipArchive;

class BackupExporter
{
    public function __construct(
        private readonly KavloStorage $storage,
    ) {}

    public function directory(): string
    {
        return storage_path('app/backups');
    }

    public function stats(): array
    {
        return [
            'database_tables' => count(Schema::getTableListing()),
            'public_files' => $this->publicFiles()->count(),
            'plugins' => Schema::hasTable('plugins') ? Plugin::count() : 0,
            'themes' => Schema::hasTable('themes') ? Theme::count() : 0,
        ];
    }

    /**
     * @param  array{persist?: bool, purpose?: string, label?: string}  $options
     */
    public function createArchive(array $options = []): array
    {
        if (! class_exists(ZipArchive::class)) {
            throw new RuntimeException('ZIP archive support is not available on this server.');
        }

        $directory = $this->directory();
        File::ensureDirectoryExists($directory);

        $timestamp = now()->format('Y-m-d-His');
        $persist = (bool) ($options['persist'] ?? false);
        $purpose = trim((string) ($options['purpose'] ?? ($persist ? 'deployment-checkpoint' : 'manual-export')));
        $label = trim((string) ($options['label'] ?? ''));
        $prefix = $purpose === 'deployment-checkpoint' ? 'cms-checkpoint' : 'cms-backup';
        $labelSegment = $label !== '' ? '-'.Str::slug($label) : '';
        $filename = $this->uniqueFilename($directory, "{$prefix}{$labelSegment}-{$timestamp}.zip");
        $path = $persist ? $directory.'/'.$filename : $directory.'/'.Str::uuid().'-'.$filename;

        $zip = new ZipArchive;
        $opened = $zip->open($path, ZipArchive::CREATE | ZipArchive::OVERWRITE);

        if ($opened !== true) {
            throw new RuntimeException('The backup archive could not be created.');
        }

        try {
            $this->addMetadata($zip, $filename, [
                'purpose' => $purpose,
                'label' => $label !== '' ? $label : null,
            ]);
            $this->addDatabaseTables($zip);
            $this->addSettingsSnapshot($zip);
            $this->addPluginSnapshot($zip);
            $this->addThemeSnapshot($zip);
            $this->addPublicFiles($zip);
        } finally {
            $zip->close();
        }

        return [
            'path' => $path,
            'filename' => $filename,
            'purpose' => $purpose,
            'label' => $label !== '' ? $label : null,
        ];
    }

    /**
     * @return list<array{filename: string, label: string, created_at: string, size_bytes: int, purpose: string}>
     */
    public function recentCheckpoints(int $limit = 5): array
    {
        $directory = $this->directory();

        if (! File::isDirectory($directory)) {
            return [];
        }

        return collect(File::files($directory))
            ->filter(fn (SplFileInfo $file) => $file->getExtension() === 'zip')
            ->sortByDesc(fn (SplFileInfo $file) => $file->getMTime())
            ->map(fn (SplFileInfo $file) => $this->readArchiveSummary($file->getRealPath()))
            ->filter(fn (?array $summary) => is_array($summary) && $summary['purpose'] === 'deployment-checkpoint')
            ->take($limit)
            ->values()
            ->all();
    }

    public function resolveCheckpointPath(string $filename): string
    {
        if (basename($filename) !== $filename || preg_match('/^[A-Za-z0-9._-]+\.zip$/', $filename) !== 1) {
            throw new RuntimeException('The requested rollback checkpoint is invalid.');
        }

        $path = $this->directory().'/'.$filename;

        if (! File::exists($path)) {
            throw new RuntimeException('The requested rollback checkpoint does not exist.');
        }

        $summary = $this->readArchiveSummary($path);

        if ($summary === null || $summary['purpose'] !== 'deployment-checkpoint') {
            throw new RuntimeException('The requested archive is not a stored rollback checkpoint.');
        }

        return $path;
    }

    /**
     * @param  array{purpose?: string, label?: ?string}  $options
     */
    private function addMetadata(ZipArchive $zip, string $filename, array $options): void
    {
        $payload = [
            '$schema' => '../../resources/schemas/backup.schema.json',
            'filename' => $filename,
            'created_at' => now()->toIso8601String(),
            'app_name' => config('app.name'),
            'app_url' => config('app.url'),
            'laravel' => app()->version(),
            'php' => PHP_VERSION,
            'purpose' => $options['purpose'] ?? 'manual-export',
            'stats' => $this->stats(),
        ];

        if (filled($options['label'] ?? null)) {
            $payload['label'] = $options['label'];
        }

        $zip->addFromString('backup/manifest.json', $this->json($payload));
    }

    private function addDatabaseTables(ZipArchive $zip): void
    {
        foreach (Schema::getTableListing() as $table) {
            $rows = DB::table($table)->get()->map(fn ($row) => $this->normalize((array) $row))->all();
            $fileName = Str::afterLast($table, '.');

            $zip->addFromString("database/{$fileName}.json", $this->json($rows));
        }
    }

    private function addSettingsSnapshot(ZipArchive $zip): void
    {
        if (! Schema::hasTable('settings')) {
            return;
        }

        $zip->addFromString('cms/settings.json', $this->json($this->normalize(Setting::allCached())));
    }

    private function addPluginSnapshot(ZipArchive $zip): void
    {
        if (Schema::hasTable('plugins')) {
            $zip->addFromString('cms/plugins.json', $this->json(
                Plugin::query()->orderBy('slug')->get()->map(fn (Plugin $plugin) => $plugin->toArray())->all()
            ));
        }

        $pluginsPath = base_path('plugins');
        if (! File::isDirectory($pluginsPath)) {
            return;
        }

        foreach (File::directories($pluginsPath) as $directory) {
            $manifest = $directory.'/plugin.json';
            if (File::exists($manifest)) {
                $zip->addFromString('cms/plugin-manifests/'.basename($directory).'/plugin.json', File::get($manifest));
            }
        }
    }

    private function addThemeSnapshot(ZipArchive $zip): void
    {
        if (Schema::hasTable('themes')) {
            $zip->addFromString('cms/themes.json', $this->json(
                Theme::query()->orderBy('slug')->get()->map(fn (Theme $theme) => $theme->toArray())->all()
            ));
        }

        $themesPath = base_path('themes');
        if (! File::isDirectory($themesPath)) {
            return;
        }

        foreach (File::directories($themesPath) as $directory) {
            $manifest = $directory.'/theme.json';
            if (File::exists($manifest)) {
                $zip->addFromString('cms/theme-manifests/'.basename($directory).'/theme.json', File::get($manifest));
            }
        }
    }

    private function addPublicFiles(ZipArchive $zip): void
    {
        foreach ($this->publicFiles() as $file) {
            $contents = $this->storage->publicDisk()->get($file);

            $zip->addFromString('storage/public/'.ltrim(str_replace('\\', '/', $file), '/'), $contents);
        }
    }

    private function publicFiles()
    {
        return collect($this->storage->publicDisk()->allFiles())
            ->filter(fn (mixed $path) => is_string($path) && $path !== '')
            ->values();
    }

    private function normalize(mixed $value): mixed
    {
        if (is_array($value)) {
            return array_map(fn ($item) => $this->normalize($item), $value);
        }

        if ($value instanceof \DateTimeInterface) {
            return $value->format(DATE_ATOM);
        }

        if (is_object($value)) {
            return $this->normalize((array) $value);
        }

        return $value;
    }

    private function json(mixed $payload): string
    {
        return json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE)
            ?: '[]';
    }

    private function uniqueFilename(string $directory, string $filename): string
    {
        $candidate = $filename;
        $counter = 2;

        while (File::exists($directory.'/'.$candidate)) {
            $candidate = str_replace('.zip', '', $filename).'-'.$counter.'.zip';
            $counter++;
        }

        return $candidate;
    }

    /**
     * @return array{filename: string, label: string, created_at: string, size_bytes: int, purpose: string}|null
     */
    private function readArchiveSummary(string $path): ?array
    {
        $zip = new ZipArchive;

        if ($zip->open($path) !== true) {
            return null;
        }

        try {
            $manifestContents = $zip->getFromName('backup/manifest.json');

            if ($manifestContents === false) {
                return null;
            }

            $manifest = json_decode($manifestContents, true);

            if (! is_array($manifest)) {
                return null;
            }

            $errors = app(BackupManifest::class)->validate($manifest);

            if ($errors !== []) {
                return null;
            }

            return [
                'filename' => basename($path),
                'label' => (string) ($manifest['label'] ?? pathinfo($path, PATHINFO_FILENAME)),
                'created_at' => (string) ($manifest['created_at'] ?? now()->toIso8601String()),
                'size_bytes' => (int) File::size($path),
                'purpose' => (string) ($manifest['purpose'] ?? 'manual-export'),
            ];
        } finally {
            $zip->close();
        }
    }
}
