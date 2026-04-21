<?php

namespace Tests\Feature;

use App\Models\Page;
use App\Models\Setting;
use App\Models\User;
use App\Services\BackupExporter;
use App\Services\SystemHealthService;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;
use Spatie\Permission\Models\Role;
use Symfony\Component\HttpFoundation\File\UploadedFile as SymfonyUploadedFile;
use Tests\TestCase;
use ZipArchive;

class AdminBackupExportTest extends TestCase
{
    use RefreshDatabase;

    private string $publicTestFile;

    private array $archivePaths = [];

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);
        Config::set('cms.storage.public_disk', 'uploads');
        Config::set('filesystems.disks.uploads', [
            'driver' => 'local',
            'root' => storage_path('framework/testing/disks/uploads'),
            'url' => 'https://cdn.example.com/assets',
            'visibility' => 'public',
            'throw' => false,
        ]);
        Storage::fake('uploads');
        Config::set('filesystems.disks.uploads.url', 'https://cdn.example.com/assets');

        $this->publicTestFile = 'uploads/test-backup.txt';
        Storage::disk('uploads')->put($this->publicTestFile, 'backup-content');
    }

    protected function tearDown(): void
    {
        Storage::disk('uploads')->delete($this->publicTestFile);
        Storage::disk('uploads')->delete('uploads/changed-after-backup.txt');
        foreach ($this->archivePaths as $path) {
            File::delete($path);
        }

        parent::tearDown();
    }

    public function test_admin_can_download_backup_archive(): void
    {
        Page::create([
            'title' => 'Backup page',
            'slug' => 'backup-page',
            'type' => 'page',
            'blocks' => [],
            'metadata' => [],
        ]);
        Setting::set('site_name', 'Backup CMS');

        $response = $this->actingAsConfirmed($this->userWithRole('admin'))
            ->get(route('admin.backups.export'));

        $response->assertOk();
        $response->assertDownload();

        $archivePath = $response->baseResponse->getFile()->getPathname();
        $this->archivePaths[] = $archivePath;
        $zip = new ZipArchive;
        $zip->open($archivePath);

        $this->assertNotFalse($zip->locateName('backup/manifest.json'));
        $this->assertNotFalse($zip->locateName('database/pages.json'));
        $this->assertNotFalse($zip->locateName('cms/settings.json'));
        $this->assertNotFalse($zip->locateName('storage/public/uploads/test-backup.txt'));
        $this->assertStringContainsString('Backup CMS', $zip->getFromName('cms/settings.json'));

        $zip->close();
    }

    public function test_admin_can_restore_backup_archive(): void
    {
        $admin = $this->userWithRole('admin');

        $page = Page::create([
            'title' => 'Backup page',
            'slug' => 'backup-page',
            'type' => 'page',
            'blocks' => [],
            'metadata' => [],
        ]);
        Setting::set('site_name', 'Backup CMS');

        $archive = app(BackupExporter::class)->createArchive();
        $this->archivePaths[] = $archive['path'];

        $page->update(['title' => 'Changed page']);
        Setting::set('site_name', 'Changed CMS');
        Storage::disk('uploads')->delete($this->publicTestFile);
        Storage::disk('uploads')->put('uploads/changed-after-backup.txt', 'changed-content');

        $response = $this->actingAsConfirmed($admin)->post(route('admin.backups.restore'), [
            'archive' => $this->uploadedArchive($archive['path'], $archive['filename']),
            'confirmation' => 'RESTORE',
        ]);

        $this->assertSame(302, $response->getStatusCode());

        $this->assertDatabaseHas('pages', [
            'id' => $page->id,
            'title' => 'Backup page',
            'slug' => 'backup-page',
        ]);
        $this->assertSame('Backup CMS', Setting::get('site_name'));
        $this->assertTrue(Storage::disk('uploads')->exists($this->publicTestFile));
        $this->assertSame('backup-content', Storage::disk('uploads')->get($this->publicTestFile));
        $this->assertFalse(Storage::disk('uploads')->exists('uploads/changed-after-backup.txt'));
    }

    public function test_admin_can_inspect_backup_archive(): void
    {
        $admin = $this->userWithRole('admin');

        Page::create([
            'title' => 'Backup page',
            'slug' => 'backup-page',
            'type' => 'page',
            'blocks' => [],
            'metadata' => [],
        ]);
        Setting::set('site_name', 'Backup CMS');

        $archive = app(BackupExporter::class)->createArchive();
        $this->archivePaths[] = $archive['path'];

        $response = $this->actingAsConfirmed($admin)
            ->post(route('admin.backups.inspect'), [
                'archive' => $this->uploadedArchive($archive['path'], $archive['filename']),
            ], [
                'Accept' => 'application/json',
                'X-Requested-With' => 'XMLHttpRequest',
            ])
            ->assertOk()
            ->assertJsonPath('manifest.filename', $archive['filename'])
            ->assertJsonPath('manifest.app_name', config('app.name'));

        $this->assertGreaterThan(0, $response->json('public_files'));
    }

    public function test_backups_screen_includes_deployment_readiness_report(): void
    {
        Cache::forever(SystemHealthService::SCHEDULER_HEARTBEAT_CACHE_KEY, now()->toIso8601String());

        $this->actingAs($this->userWithRole('admin'))
            ->get(route('admin.backups.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $assert) => $assert
                ->component('Backups/Index')
                ->has('readiness.checks')
                ->where('readiness.checks.0.key', 'system_health')
            );
    }

    public function test_admin_can_create_named_rollback_checkpoint(): void
    {
        $admin = $this->userWithRole('admin');

        $this->actingAsConfirmed($admin)
            ->post(route('admin.backups.checkpoints.store'), [
                'label' => 'Before release candidate',
            ])
            ->assertRedirect(route('admin.backups.index'));

        $checkpoint = collect(app(BackupExporter::class)->recentCheckpoints(10))
            ->firstWhere('label', 'Before release candidate');

        $this->assertNotNull($checkpoint);

        $checkpointPath = app(BackupExporter::class)->directory().'/'.$checkpoint['filename'];
        $this->archivePaths[] = $checkpointPath;

        $this->assertTrue(File::exists($checkpointPath));
    }

    public function test_admin_can_download_saved_rollback_checkpoint(): void
    {
        $admin = $this->userWithRole('admin');

        $archive = app(BackupExporter::class)->createArchive([
            'persist' => true,
            'purpose' => 'deployment-checkpoint',
            'label' => 'Downloadable checkpoint',
        ]);
        $this->archivePaths[] = $archive['path'];

        $this->actingAsConfirmed($admin)
            ->get(route('admin.backups.checkpoints.download', ['file' => $archive['filename']]))
            ->assertOk()
            ->assertDownload($archive['filename']);
    }

    public function test_restore_requires_confirmation_phrase(): void
    {
        $admin = $this->userWithRole('admin');
        $archive = app(BackupExporter::class)->createArchive();
        $this->archivePaths[] = $archive['path'];

        $this->actingAsConfirmed($admin)
            ->post(route('admin.backups.restore'), [
                'archive' => $this->uploadedArchive($archive['path'], $archive['filename']),
                'confirmation' => 'restore',
            ])
            ->assertSessionHasErrors('confirmation');
    }

    public function test_author_cannot_download_backup_archive(): void
    {
        $this->actingAs($this->userWithRole('author'))
            ->get(route('admin.backups.export'))
            ->assertForbidden();
    }

    public function test_author_cannot_restore_backup_archive(): void
    {
        $this->actingAs($this->userWithRole('author'))
            ->post(route('admin.backups.restore'), [
                'archive' => UploadedFile::fake()->create('backup.zip', 8, 'application/zip'),
                'confirmation' => 'RESTORE',
            ])
            ->assertForbidden();
    }

    public function test_author_cannot_create_rollback_checkpoint(): void
    {
        $this->actingAs($this->userWithRole('author'))
            ->post(route('admin.backups.checkpoints.store'), [
                'label' => 'Nope',
            ])
            ->assertForbidden();
    }

    public function test_author_cannot_inspect_backup_archive(): void
    {
        $this->actingAs($this->userWithRole('author'))
            ->post(route('admin.backups.inspect'), [
                'archive' => UploadedFile::fake()->create('backup.zip', 8, 'application/zip'),
            ], [
                'Accept' => 'application/json',
                'X-Requested-With' => 'XMLHttpRequest',
            ])
            ->assertForbidden();
    }

    private function userWithRole(string $role): User
    {
        $user = User::factory()->create();
        $user->assignRole(Role::findByName($role, 'web'));

        return $user;
    }

    private function actingAsConfirmed(User $user): static
    {
        return $this->actingAs($user)->withSession([
            'auth.password_confirmed_at' => time(),
        ]);
    }

    private function uploadedArchive(string $path, string $name): UploadedFile
    {
        return UploadedFile::createFromBase(new SymfonyUploadedFile(
            $path,
            $name,
            'application/zip',
            null,
            true,
        ), true);
    }
}
