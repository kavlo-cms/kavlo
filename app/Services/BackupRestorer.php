<?php

namespace App\Services;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use RuntimeException;
use ZipArchive;

class BackupRestorer
{
    public function __construct(
        private readonly KavloStorage $storage,
    ) {}

    private const SKIPPED_TABLES = [
        'cache',
        'cache_locks',
        'failed_jobs',
        'job_batches',
        'jobs',
        'migrations',
        'password_reset_tokens',
        'sessions',
    ];

    public function restore(UploadedFile $archive): array
    {
        [$zip, $manifest, $databaseEntries, $publicFiles] = $this->openArchive($archive);

        try {
            $restoredTables = $this->restoreDatabase($zip, $databaseEntries);
            $restoredFiles = $this->restorePublicFiles($zip, $publicFiles);
        } finally {
            $zip->close();
        }

        $this->refreshApplicationState();

        return [
            'manifest' => $manifest,
            'tables' => $restoredTables,
            'files' => $restoredFiles,
        ];
    }

    public function inspect(UploadedFile $archive): array
    {
        [$zip, $manifest, $databaseEntries, $publicFiles] = $this->openArchive($archive);

        try {
            $currentTables = collect(Schema::getTableListing())
                ->map(fn (string $table) => Str::afterLast($table, '.'))
                ->reject(fn (string $table) => in_array($table, self::SKIPPED_TABLES, true))
                ->values()
                ->all();

            $archivedTables = array_keys($databaseEntries);
            $restorableTables = array_values(array_intersect($currentTables, $archivedTables));
            $missingTables = array_values(array_diff($currentTables, $archivedTables));
            $extraTables = array_values(array_diff($archivedTables, $currentTables));

            return [
                'manifest' => $manifest,
                'database' => [
                    'archived_tables' => count($archivedTables),
                    'restorable_tables' => count($restorableTables),
                    'missing_tables' => $missingTables,
                    'extra_tables' => $extraTables,
                ],
                'public_files' => count($publicFiles),
            ];
        } finally {
            $zip->close();
        }
    }

    /**
     * @return array{0: ZipArchive, 1: array<string, mixed>, 2: array<string, string>, 3: array<string, string>}
     */
    private function openArchive(UploadedFile $archive): array
    {
        if (! class_exists(ZipArchive::class)) {
            throw new RuntimeException('ZIP archive support is not available on this server.');
        }

        $zip = new ZipArchive;
        $opened = $zip->open($archive->getRealPath());

        if ($opened !== true) {
            throw new RuntimeException('The uploaded backup archive could not be opened.');
        }

        try {
            $manifest = $this->readManifest($zip);
            $databaseEntries = $this->databaseEntries($zip);
            $publicFiles = $this->publicFileEntries($zip);

            return [$zip, $manifest, $databaseEntries, $publicFiles];
        } catch (\Throwable $exception) {
            $zip->close();

            throw $exception;
        }
    }

    private function readManifest(ZipArchive $zip): array
    {
        $manifest = $zip->getFromName('backup/manifest.json');

        if ($manifest === false) {
            throw new RuntimeException('The uploaded file is not a CMS backup archive.');
        }

        $payload = json_decode($manifest, true);

        if (! is_array($payload)) {
            throw new RuntimeException('The backup manifest is invalid.');
        }

        $errors = app(BackupManifest::class)->validate($payload);

        if ($errors !== []) {
            throw new RuntimeException("The backup manifest is invalid: {$errors[0]}");
        }

        return $payload;
    }

    private function databaseEntries(ZipArchive $zip): array
    {
        $entries = [];

        for ($index = 0; $index < $zip->numFiles; $index++) {
            $name = $zip->getNameIndex($index);

            if (! is_string($name) || ! str_starts_with($name, 'database/') || ! str_ends_with($name, '.json')) {
                continue;
            }

            $table = basename($name, '.json');
            if ($table === '') {
                continue;
            }

            $entries[$table] = $name;
        }

        return $entries;
    }

    private function publicFileEntries(ZipArchive $zip): array
    {
        $entries = [];

        for ($index = 0; $index < $zip->numFiles; $index++) {
            $name = $zip->getNameIndex($index);

            if (! is_string($name) || ! str_starts_with($name, 'storage/public/')) {
                continue;
            }

            if (str_ends_with($name, '/')) {
                continue;
            }

            $relativePath = Str::after($name, 'storage/public/');
            $this->assertSafeRelativePath($relativePath);

            $entries[$relativePath] = $name;
        }

        return $entries;
    }

    private function restoreDatabase(ZipArchive $zip, array $databaseEntries): int
    {
        $tables = collect(Schema::getTableListing())
            ->map(fn (string $table) => Str::afterLast($table, '.'))
            ->reject(fn (string $table) => in_array($table, self::SKIPPED_TABLES, true))
            ->filter(fn (string $table) => array_key_exists($table, $databaseEntries))
            ->values()
            ->all();
        $insertOrder = $this->sortTablesByDependencies($tables);
        $deleteOrder = array_reverse($insertOrder);

        $this->setForeignKeyChecks(false);
        Schema::disableForeignKeyConstraints();
        DB::beginTransaction();

        try {
            foreach ($deleteOrder as $table) {
                DB::table($table)->delete();
            }

            foreach ($insertOrder as $table) {
                $rows = $this->decodeRows($zip, $databaseEntries[$table], $table);

                if ($rows !== []) {
                    DB::table($table)->insert($rows);
                }
            }

            DB::commit();
        } catch (\Throwable $exception) {
            DB::rollBack();

            throw new RuntimeException(
                'The backup database restore failed for table data: '.$exception->getMessage(),
                previous: $exception,
            );
        } finally {
            $this->setForeignKeyChecks(true);
            Schema::enableForeignKeyConstraints();
        }

        return count($tables);
    }

    /**
     * @param  list<string>  $tables
     * @return list<string>
     */
    private function sortTablesByDependencies(array $tables): array
    {
        $tableSet = array_fill_keys($tables, true);
        $dependencies = [];
        $dependents = [];
        $inDegree = [];

        foreach ($tables as $table) {
            $dependencies[$table] = [];
            $dependents[$table] = [];
            $inDegree[$table] = 0;
        }

        foreach ($tables as $table) {
            foreach (Schema::getForeignKeys($table) as $foreignKey) {
                $foreignTable = Str::afterLast((string) ($foreignKey['foreign_table'] ?? ''), '.');

                if ($foreignTable === '' || $foreignTable === $table || ! isset($tableSet[$foreignTable])) {
                    continue;
                }

                if (in_array($foreignTable, $dependencies[$table], true)) {
                    continue;
                }

                $dependencies[$table][] = $foreignTable;
                $dependents[$foreignTable][] = $table;
                $inDegree[$table]++;
            }
        }

        $queue = collect($tables)
            ->filter(fn (string $table) => $inDegree[$table] === 0)
            ->values()
            ->all();

        $sorted = [];

        while ($queue !== []) {
            $table = array_shift($queue);
            $sorted[] = $table;

            foreach ($dependents[$table] as $dependent) {
                $inDegree[$dependent]--;

                if ($inDegree[$dependent] === 0) {
                    $queue[] = $dependent;
                }
            }
        }

        if (count($sorted) !== count($tables)) {
            foreach ($tables as $table) {
                if (! in_array($table, $sorted, true)) {
                    $sorted[] = $table;
                }
            }
        }

        return $sorted;
    }

    private function setForeignKeyChecks(bool $enabled): void
    {
        if (DB::getDriverName() !== 'sqlite') {
            return;
        }

        DB::statement('PRAGMA foreign_keys = '.($enabled ? 'ON' : 'OFF'));
    }

    private function refreshApplicationState(): void
    {
        Cache::forget('settings.all');
        Cache::forget('active_theme_slug');
        Cache::forget('spatie.permission.cache');

        app(ContentRouteRegistry::class)->forget();
    }

    private function restorePublicFiles(ZipArchive $zip, array $publicFiles): int
    {
        $disk = $this->storage->publicDisk();
        $existingFiles = $disk->allFiles();

        if ($existingFiles !== []) {
            $disk->delete($existingFiles);
        }

        foreach ($publicFiles as $relativePath => $entryName) {
            $contents = $zip->getFromName($entryName);

            if ($contents === false) {
                throw new RuntimeException("The backup archive is missing \"{$entryName}\".");
            }

            $disk->put($relativePath, $contents);
        }

        return count($publicFiles);
    }

    private function decodeRows(ZipArchive $zip, string $entryName, string $table): array
    {
        $contents = $zip->getFromName($entryName);

        if ($contents === false) {
            throw new RuntimeException("The backup archive is missing database data for \"{$table}\".");
        }

        $decoded = json_decode($contents, true);

        if (! is_array($decoded)) {
            throw new RuntimeException("The backup data for \"{$table}\" is invalid.");
        }

        return array_map(function ($row) use ($table) {
            if (! is_array($row)) {
                throw new RuntimeException("The backup data for \"{$table}\" contains an invalid row.");
            }

            return $row;
        }, $decoded);
    }

    private function assertSafeRelativePath(string $path): void
    {
        if ($path === '' || str_contains($path, "\0")) {
            throw new RuntimeException('The backup archive contains an invalid file path.');
        }

        $normalized = str_replace('\\', '/', $path);
        $segments = array_filter(explode('/', $normalized), fn (string $segment) => $segment !== '');

        foreach ($segments as $segment) {
            if ($segment === '.' || $segment === '..') {
                throw new RuntimeException('The backup archive contains an unsafe file path.');
            }
        }
    }
}
