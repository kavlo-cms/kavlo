<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\File;
use PharData;
use Spatie\Permission\Models\Role;
use Symfony\Component\HttpFoundation\File\UploadedFile as SymfonyUploadedFile;
use Tests\TestCase;
use ZipArchive;

class PluginArchiveUploadTest extends TestCase
{
    use RefreshDatabase;

    private array $installedPluginDirectories = [];

    protected function tearDown(): void
    {
        foreach ($this->installedPluginDirectories as $directory) {
            File::deleteDirectory($directory);
        }

        parent::tearDown();
    }

    public function test_uploading_zip_archive_extracts_and_discovers_plugin(): void
    {
        $archive = $this->makeZipArchive('UploadedZipPlugin');

        $this->actingAs($this->adminUser())
            ->post(route('admin.plugins.upload'), [
                'archive' => $archive,
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('plugins', [
            'slug' => 'UploadedZipPlugin',
            'name' => 'Uploaded Zip Plugin',
        ]);
        $this->assertTrue(File::exists(base_path('plugins/UploadedZipPlugin/plugin.json')));
    }

    public function test_uploading_tar_archive_extracts_and_discovers_plugin(): void
    {
        $archive = $this->makeTarArchive('UploadedTarPlugin');

        $this->actingAs($this->adminUser())
            ->post(route('admin.plugins.upload'), [
                'archive' => $archive,
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('plugins', [
            'slug' => 'UploadedTarPlugin',
            'name' => 'Uploaded Tar Plugin',
        ]);
        $this->assertTrue(File::exists(base_path('plugins/UploadedTarPlugin/plugin.json')));
    }

    public function test_uploading_archive_with_invalid_plugin_manifest_is_rejected(): void
    {
        $archive = $this->makeInvalidZipArchive('UploadedBrokenPlugin');

        $this->actingAs($this->adminUser())
            ->post(route('admin.plugins.upload'), [
                'archive' => $archive,
            ])
            ->assertSessionHasErrors('archive');

        $this->assertFalse(File::exists(base_path('plugins/UploadedBrokenPlugin/plugin.json')));
    }

    private function makeZipArchive(string $directoryName): UploadedFile
    {
        $tempPath = tempnam(sys_get_temp_dir(), 'plugin-archive-');
        $archivePath = $tempPath.'.zip';
        @unlink($tempPath);

        $zip = new ZipArchive;
        $zip->open($archivePath, ZipArchive::CREATE | ZipArchive::OVERWRITE);
        $zip->addFromString($directoryName.'/plugin.json', json_encode([
            '$schema' => '../../resources/schemas/plugin.schema.json',
            'name' => 'Uploaded Zip Plugin',
            'entrypoint' => "Plugins\\{$directoryName}\\{$directoryName}Plugin",
            'scopes' => ['hooks:write'],
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
        $zip->addFromString($directoryName.'/'.$directoryName.'Plugin.php', $this->pluginStub($directoryName));
        $zip->close();

        $this->installedPluginDirectories[] = base_path('plugins/'.$directoryName);

        return $this->uploadedArchive($archivePath, $directoryName.'.zip', 'application/zip');
    }

    private function makeTarArchive(string $directoryName): UploadedFile
    {
        $tempPath = tempnam(sys_get_temp_dir(), 'plugin-archive-');
        $archivePath = $tempPath.'.tar';
        @unlink($tempPath);

        $tar = new PharData($archivePath);
        $tar->addFromString($directoryName.'/plugin.json', json_encode([
            '$schema' => '../../resources/schemas/plugin.schema.json',
            'name' => 'Uploaded Tar Plugin',
            'entrypoint' => "Plugins\\{$directoryName}\\{$directoryName}Plugin",
            'scopes' => ['hooks:write'],
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
        $tar->addFromString($directoryName.'/'.$directoryName.'Plugin.php', $this->pluginStub($directoryName));

        $this->installedPluginDirectories[] = base_path('plugins/'.$directoryName);

        return $this->uploadedArchive($archivePath, $directoryName.'.tar', 'application/x-tar');
    }

    private function makeInvalidZipArchive(string $directoryName): UploadedFile
    {
        $tempPath = tempnam(sys_get_temp_dir(), 'plugin-archive-');
        $archivePath = $tempPath.'.zip';
        @unlink($tempPath);

        $zip = new ZipArchive;
        $zip->open($archivePath, ZipArchive::CREATE | ZipArchive::OVERWRITE);
        $zip->addFromString($directoryName.'/plugin.json', json_encode([
            '$schema' => '../../resources/schemas/plugin.schema.json',
            'name' => 'Uploaded Broken Plugin',
            'entrypoint' => "Plugins\\{$directoryName}\\{$directoryName}Plugin",
            'scopes' => ['hooks'],
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
        $zip->addFromString($directoryName.'/'.$directoryName.'Plugin.php', $this->pluginStub($directoryName));
        $zip->close();

        $this->installedPluginDirectories[] = base_path('plugins/'.$directoryName);

        return $this->uploadedArchive($archivePath, $directoryName.'.zip', 'application/zip');
    }

    private function uploadedArchive(string $path, string $name, string $mimeType): UploadedFile
    {
        return UploadedFile::createFromBase(new SymfonyUploadedFile(
            $path,
            $name,
            $mimeType,
            null,
            true,
        ), true);
    }

    private function pluginStub(string $directoryName): string
    {
        return <<<PHP
<?php

namespace Plugins\\{$directoryName};

use App\\Contracts\\PluginBase;
use App\\Plugins\\PluginContext;

class {$directoryName}Plugin implements PluginBase
{
    public function boot(PluginContext \$context): void
    {
    }
}
PHP;
    }

    protected function adminUser(): User
    {
        Role::findOrCreate('admin', 'web');

        $user = User::factory()->create();
        $user->assignRole('admin');

        return $user;
    }
}
