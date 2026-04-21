<?php

namespace App\Services;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Throwable;

class SystemHealthService
{
    public const SCHEDULER_HEARTBEAT_CACHE_KEY = 'system-health.scheduler.last-heartbeat';

    public function __construct(
        private readonly KavloMailDelivery $mailDelivery,
    ) {}

    public function report(): array
    {
        $checks = [
            $this->databaseCheck(),
            $this->cacheCheck(),
            $this->storageCheck(),
            $this->queueCheck(),
            $this->mailCheck(),
            $this->schedulerCheck(),
        ];

        $counts = [
            'ok' => count(array_filter($checks, fn (array $check) => $check['status'] === 'ok')),
            'warning' => count(array_filter($checks, fn (array $check) => $check['status'] === 'warning')),
            'fail' => count(array_filter($checks, fn (array $check) => $check['status'] === 'fail')),
        ];

        $status = $counts['fail'] > 0 ? 'fail' : ($counts['warning'] > 0 ? 'warning' : 'ok');

        return [
            'status' => $status,
            'checked_at' => now()->toIso8601String(),
            'summary' => $counts,
            'checks' => $checks,
        ];
    }

    public function markSchedulerHeartbeat(): void
    {
        Cache::forever(self::SCHEDULER_HEARTBEAT_CACHE_KEY, now()->toIso8601String());
    }

    private function databaseCheck(): array
    {
        try {
            DB::select('select 1 as healthy');

            return $this->check(
                key: 'database',
                label: 'Database',
                status: 'ok',
                message: 'Database connection responded successfully.',
                meta: ['connection' => DB::getDefaultConnection()],
            );
        } catch (Throwable $exception) {
            return $this->check(
                key: 'database',
                label: 'Database',
                status: 'fail',
                message: 'Database connection failed.',
                meta: ['error' => $exception->getMessage()],
            );
        }
    }

    private function cacheCheck(): array
    {
        $key = 'system-health.cache.'.Str::uuid();
        $value = Str::uuid()->toString();

        try {
            Cache::put($key, $value, 60);
            $resolved = Cache::get($key);
            Cache::forget($key);

            if ($resolved !== $value) {
                return $this->check(
                    key: 'cache',
                    label: 'Cache',
                    status: 'fail',
                    message: 'Cache store did not return the expected value.',
                    meta: ['store' => config('cache.default')],
                );
            }

            return $this->check(
                key: 'cache',
                label: 'Cache',
                status: 'ok',
                message: 'Cache store is readable and writable.',
                meta: ['store' => config('cache.default')],
            );
        } catch (Throwable $exception) {
            return $this->check(
                key: 'cache',
                label: 'Cache',
                status: 'fail',
                message: 'Cache store failed.',
                meta: ['store' => config('cache.default'), 'error' => $exception->getMessage()],
            );
        }
    }

    private function storageCheck(): array
    {
        $disk = (string) config('cms.storage.public_disk', config('filesystems.default', 'local'));
        $path = 'system-health/'.Str::uuid().'.txt';
        $contents = Str::uuid()->toString();

        try {
            Storage::disk($disk)->put($path, $contents);
            $resolved = Storage::disk($disk)->get($path);
            Storage::disk($disk)->delete($path);

            if ($resolved !== $contents) {
                return $this->check(
                    key: 'storage',
                    label: 'Storage',
                    status: 'fail',
                    message: 'Default filesystem disk did not return the expected file contents.',
                    meta: ['disk' => $disk],
                );
            }

            return $this->check(
                key: 'storage',
                label: 'Storage',
                status: 'ok',
                message: 'CMS public storage disk is readable and writable.',
                meta: ['disk' => $disk],
            );
        } catch (Throwable $exception) {
            return $this->check(
                key: 'storage',
                label: 'Storage',
                status: 'fail',
                message: 'CMS public storage disk failed.',
                meta: ['disk' => $disk, 'error' => $exception->getMessage()],
            );
        }
    }

    private function queueCheck(): array
    {
        $connection = $this->mailDelivery->connectionName();
        $queueConfig = config("queue.connections.{$connection}");

        if ($connection === '' || ! is_array($queueConfig)) {
            return $this->check(
                key: 'queue',
                label: 'Queue',
                status: 'fail',
                message: 'Queue connection is not configured.',
                meta: ['connection' => $connection],
            );
        }

        $failedJobs = Schema::hasTable('failed_jobs') ? DB::table('failed_jobs')->count() : null;
        $afterCommit = (bool) ($queueConfig['after_commit'] ?? false);
        $meta = [
            'connection' => $connection,
            'after_commit' => $afterCommit,
        ];

        if ($failedJobs !== null) {
            $meta['failed_jobs'] = $failedJobs;
        }

        if ($connection === 'sync') {
            return $this->check(
                key: 'queue',
                label: 'Queue',
                status: 'warning',
                message: 'Queue is using the sync driver, so background jobs run inline.',
                meta: $meta,
            );
        }

        if ($failedJobs !== null && $failedJobs > 0) {
            return $this->check(
                key: 'queue',
                label: 'Queue',
                status: 'warning',
                message: 'Queue has failed jobs that should be reviewed or retried.',
                meta: $meta,
            );
        }

        if (! $afterCommit) {
            return $this->check(
                key: 'queue',
                label: 'Queue',
                status: 'warning',
                message: 'Queue is async, but jobs may dispatch before database transactions commit.',
                meta: $meta,
            );
        }

        return $this->check(
            key: 'queue',
            label: 'Queue',
            status: 'ok',
            message: 'Queue connection is configured for asynchronous processing.',
            meta: $meta,
        );
    }

    private function mailCheck(): array
    {
        $mailer = (string) config('mail.default', '');
        $mailConfig = config("mail.mailers.{$mailer}");
        $fromAddress = trim((string) config('mail.from.address', ''));

        if ($mailer === '' || ! is_array($mailConfig)) {
            return $this->check(
                key: 'mail',
                label: 'Mail',
                status: 'fail',
                message: 'Mail transport is not configured.',
                meta: ['mailer' => $mailer],
            );
        }

        $transport = (string) ($mailConfig['transport'] ?? '');
        $meta = [
            'mailer' => $mailer,
            'transport' => $transport,
        ];

        if ($fromAddress === '') {
            return $this->check(
                key: 'mail',
                label: 'Mail',
                status: 'fail',
                message: 'Mail from address is missing.',
                meta: $meta,
            );
        }

        if (in_array($transport, ['array', 'log'], true)) {
            return $this->check(
                key: 'mail',
                label: 'Mail',
                status: 'warning',
                message: 'Mail is configured for non-delivery transport.',
                meta: $meta,
            );
        }

        if ($transport === 'smtp' && (blank($mailConfig['host'] ?? null) || blank($mailConfig['port'] ?? null))) {
            return $this->check(
                key: 'mail',
                label: 'Mail',
                status: 'fail',
                message: 'SMTP mail transport is missing host or port settings.',
                meta: $meta,
            );
        }

        return $this->check(
            key: 'mail',
            label: 'Mail',
            status: 'ok',
            message: 'Mail transport is configured.',
            meta: $meta,
        );
    }

    private function schedulerCheck(): array
    {
        $lastHeartbeat = Cache::get(self::SCHEDULER_HEARTBEAT_CACHE_KEY);

        if (! is_string($lastHeartbeat) || trim($lastHeartbeat) === '') {
            return $this->check(
                key: 'scheduler',
                label: 'Scheduler',
                status: 'warning',
                message: 'No scheduler heartbeat has been recorded yet.',
            );
        }

        try {
            $timestamp = Carbon::parse($lastHeartbeat);
        } catch (Throwable) {
            return $this->check(
                key: 'scheduler',
                label: 'Scheduler',
                status: 'fail',
                message: 'Scheduler heartbeat is unreadable.',
                meta: ['last_heartbeat' => $lastHeartbeat],
            );
        }

        $ageInMinutes = abs((int) $timestamp->diffInMinutes(now()));
        $meta = [
            'last_heartbeat' => $timestamp->toIso8601String(),
            'age_minutes' => $ageInMinutes,
        ];

        if ($ageInMinutes > 10) {
            return $this->check(
                key: 'scheduler',
                label: 'Scheduler',
                status: 'fail',
                message: 'Scheduler heartbeat is stale.',
                meta: $meta,
            );
        }

        if ($ageInMinutes > 2) {
            return $this->check(
                key: 'scheduler',
                label: 'Scheduler',
                status: 'warning',
                message: 'Scheduler heartbeat is delayed.',
                meta: $meta,
            );
        }

        return $this->check(
            key: 'scheduler',
            label: 'Scheduler',
            status: 'ok',
            message: 'Scheduler heartbeat is current.',
            meta: $meta,
        );
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
