<?php

namespace App\Services;

use Illuminate\Database\Migrations\Migrator;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\File;
use Throwable;

class DeploymentReadinessService
{
    public function __construct(
        private readonly SystemHealthService $systemHealth,
        private readonly BackupExporter $backups,
        private readonly PluginManager $plugins,
        private readonly Migrator $migrator,
    ) {}

    public function report(): array
    {
        $checks = [
            $this->systemHealthCheck(),
            $this->pendingMigrationsCheck(),
            $this->rollbackCheckpointCheck(),
            $this->backupStorageCheck(),
            $this->appDebugCheck(),
            $this->maintenanceModeCheck(),
        ];

        $counts = [
            'ok' => count(array_filter($checks, fn (array $check) => $check['status'] === 'ok')),
            'warning' => count(array_filter($checks, fn (array $check) => $check['status'] === 'warning')),
            'fail' => count(array_filter($checks, fn (array $check) => $check['status'] === 'fail')),
        ];

        return [
            'status' => $counts['fail'] > 0 ? 'fail' : ($counts['warning'] > 0 ? 'warning' : 'ok'),
            'checked_at' => now()->toIso8601String(),
            'summary' => $counts,
            'checks' => $checks,
        ];
    }

    private function systemHealthCheck(): array
    {
        $report = $this->systemHealth->report();
        $status = $report['status'] ?? 'fail';

        return $this->check(
            key: 'system_health',
            label: 'System health',
            status: in_array($status, ['ok', 'warning', 'fail'], true) ? $status : 'fail',
            message: match ($status) {
                'ok' => 'Runtime dependencies look healthy.',
                'warning' => 'Runtime checks have warnings that should be reviewed before deploying.',
                default => 'Runtime health checks are failing.',
            },
            meta: $report['summary'] ?? [],
        );
    }

    private function pendingMigrationsCheck(): array
    {
        try {
            if (! $this->migrator->repositoryExists()) {
                return $this->check(
                    key: 'pending_migrations',
                    label: 'Pending migrations',
                    status: 'fail',
                    message: 'The migrations repository does not exist.',
                );
            }

            $ran = array_fill_keys($this->migrator->getRepository()->getRan(), true);
            $pending = collect($this->migrationFiles())
                ->map(fn (string $path) => pathinfo($path, PATHINFO_FILENAME))
                ->unique()
                ->reject(fn (string $migration) => isset($ran[$migration]))
                ->sort()
                ->values()
                ->all();

            if ($pending === []) {
                return $this->check(
                    key: 'pending_migrations',
                    label: 'Pending migrations',
                    status: 'ok',
                    message: 'No unapplied migrations were found.',
                );
            }

            return $this->check(
                key: 'pending_migrations',
                label: 'Pending migrations',
                status: 'warning',
                message: 'There are unapplied migrations that should be planned into the deployment.',
                meta: [
                    'count' => count($pending),
                    'migrations' => implode(', ', array_slice($pending, 0, 8)),
                ],
            );
        } catch (Throwable $exception) {
            return $this->check(
                key: 'pending_migrations',
                label: 'Pending migrations',
                status: 'fail',
                message: 'Unable to inspect migration state.',
                meta: ['error' => $exception->getMessage()],
            );
        }
    }

    private function rollbackCheckpointCheck(): array
    {
        $checkpoint = $this->backups->recentCheckpoints(1)[0] ?? null;

        if (! is_array($checkpoint)) {
            return $this->check(
                key: 'rollback_checkpoint',
                label: 'Rollback checkpoint',
                status: 'warning',
                message: 'No stored rollback checkpoint exists yet.',
            );
        }

        try {
            $createdAt = isset($checkpoint['created_at']) && is_string($checkpoint['created_at'])
                ? Carbon::parse($checkpoint['created_at'])
                : null;
        } catch (Throwable) {
            $createdAt = null;
        }

        if (! $createdAt instanceof Carbon) {
            return $this->check(
                key: 'rollback_checkpoint',
                label: 'Rollback checkpoint',
                status: 'warning',
                message: 'A rollback checkpoint exists but its timestamp could not be read.',
                meta: ['label' => (string) ($checkpoint['label'] ?? $checkpoint['filename'] ?? 'checkpoint')],
            );
        }

        $ageHours = (int) $createdAt->diffInHours(now());

        return $this->check(
            key: 'rollback_checkpoint',
            label: 'Rollback checkpoint',
            status: $ageHours > 168 ? 'warning' : 'ok',
            message: $ageHours > 168
                ? 'The latest rollback checkpoint is older than seven days.'
                : 'A recent rollback checkpoint is available.',
            meta: [
                'label' => (string) ($checkpoint['label'] ?? $checkpoint['filename'] ?? 'checkpoint'),
                'created_at' => $createdAt->toIso8601String(),
                'age_hours' => $ageHours,
            ],
        );
    }

    private function backupStorageCheck(): array
    {
        $directory = $this->backups->directory();
        $probe = $directory.'/deployment-readiness-'.str_replace('.', '', uniqid('', true)).'.txt';

        try {
            File::ensureDirectoryExists($directory);
            File::put($probe, 'ok');
            $contents = File::get($probe);
            File::delete($probe);

            if ($contents !== 'ok') {
                return $this->check(
                    key: 'backup_storage',
                    label: 'Backup storage',
                    status: 'fail',
                    message: 'Backup storage returned an unexpected probe value.',
                    meta: ['directory' => $directory],
                );
            }

            return $this->check(
                key: 'backup_storage',
                label: 'Backup storage',
                status: 'ok',
                message: 'Backup storage is writable.',
                meta: ['directory' => $directory],
            );
        } catch (Throwable $exception) {
            File::delete($probe);

            return $this->check(
                key: 'backup_storage',
                label: 'Backup storage',
                status: 'fail',
                message: 'Backup storage could not be written.',
                meta: ['directory' => $directory, 'error' => $exception->getMessage()],
            );
        }
    }

    private function appDebugCheck(): array
    {
        $debug = (bool) config('app.debug', false);

        return $this->check(
            key: 'app_debug',
            label: 'App debug',
            status: $debug ? 'warning' : 'ok',
            message: $debug
                ? 'APP_DEBUG is enabled.'
                : 'APP_DEBUG is disabled.',
        );
    }

    private function maintenanceModeCheck(): array
    {
        $down = app()->isDownForMaintenance();

        return $this->check(
            key: 'maintenance_mode',
            label: 'Maintenance mode',
            status: $down ? 'warning' : 'ok',
            message: $down
                ? 'Maintenance mode is currently enabled.'
                : 'Maintenance mode is currently disabled.',
        );
    }

    /**
     * @return list<string>
     */
    private function migrationFiles(): array
    {
        $paths = [database_path('migrations')];

        foreach ($this->plugins->enabledManifests() as $manifest) {
            foreach ($this->plugins->migrationPathsForManifest($manifest) as $path) {
                $paths[] = $path;
            }
        }

        return collect($paths)
            ->filter(fn (mixed $path) => is_string($path) && File::isDirectory($path))
            ->unique()
            ->flatMap(function (string $path) {
                return collect(File::allFiles($path))
                    ->filter(fn (\SplFileInfo $file) => $file->getExtension() === 'php')
                    ->map(fn (\SplFileInfo $file) => $file->getRealPath())
                    ->filter();
            })
            ->unique()
            ->values()
            ->all();
    }

    /**
     * @param  array<string, mixed>  $meta
     * @return array<string, mixed>
     */
    private function check(string $key, string $label, string $status, string $message, array $meta = []): array
    {
        return [
            'key' => $key,
            'label' => $label,
            'status' => $status,
            'message' => $message,
            'meta' => $meta,
        ];
    }
}
