<?php

namespace Tests\Unit;

use App\Services\BackupManifest;
use Tests\TestCase;

class BackupManifestTest extends TestCase
{
    public function test_it_accepts_a_valid_backup_manifest_shape(): void
    {
        $errors = app(BackupManifest::class)->validate([
            '$schema' => '../../resources/schemas/backup.schema.json',
            'filename' => 'cms-backup-2026-04-20-075000.zip',
            'created_at' => now()->toIso8601String(),
            'app_name' => 'CMS',
            'app_url' => 'https://cms.example.com',
            'laravel' => '13.4.0',
            'php' => '8.4.18',
            'stats' => [
                'database_tables' => 12,
                'public_files' => 24,
                'plugins' => 3,
                'themes' => 1,
            ],
        ]);

        $this->assertSame([], $errors);
    }

    public function test_it_rejects_missing_stats_keys(): void
    {
        $errors = app(BackupManifest::class)->validate([
            'filename' => 'cms-backup.zip',
            'created_at' => now()->toIso8601String(),
            'app_name' => 'CMS',
            'app_url' => 'https://cms.example.com',
            'laravel' => '13.4.0',
            'php' => '8.4.18',
            'stats' => [
                'database_tables' => 12,
            ],
        ]);

        $this->assertContains('$.stats.public_files is required.', $errors);
        $this->assertContains('$.stats.plugins is required.', $errors);
        $this->assertContains('$.stats.themes is required.', $errors);
    }
}
