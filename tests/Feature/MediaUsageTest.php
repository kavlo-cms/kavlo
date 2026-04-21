<?php

namespace Tests\Feature;

use App\Models\Library;
use App\Models\Page;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class MediaUsageTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);
        Config::set('cms.storage.public_disk', 'uploads');
        Config::set('media-library.disk_name', 'uploads');
        Config::set('filesystems.disks.uploads', [
            'driver' => 'local',
            'root' => storage_path('framework/testing/disks/uploads'),
            'url' => 'https://cdn.example.com/assets',
            'visibility' => 'public',
            'throw' => false,
        ]);
    }

    public function test_media_list_includes_usage_references(): void
    {
        Storage::fake('uploads');
        Config::set('filesystems.disks.uploads.url', 'https://cdn.example.com/assets');

        $user = $this->adminUser();
        $library = Library::singleton();
        $media = $library
            ->addMedia(UploadedFile::fake()->image('hero.png'))
            ->toMediaCollection('uploads', 'uploads');

        Page::create([
            'title' => 'Landing',
            'slug' => 'landing',
            'type' => 'page',
            'is_published' => true,
            'og_image' => $media->getUrl(),
        ]);

        $this->actingAs($user)
            ->getJson('/admin/media/list?folder=uploads')
            ->assertOk()
            ->assertJsonPath('data.0.usage_count', 1)
            ->assertJsonPath('data.0.usage.0.context', 'Open Graph image');
    }

    public function test_media_delete_is_blocked_when_file_is_still_used(): void
    {
        Storage::fake('uploads');
        Config::set('filesystems.disks.uploads.url', 'https://cdn.example.com/assets');

        $user = $this->adminUser();
        $library = Library::singleton();
        $media = $library
            ->addMedia(UploadedFile::fake()->image('hero.png'))
            ->toMediaCollection('uploads', 'uploads');

        Page::create([
            'title' => 'Landing',
            'slug' => 'landing',
            'type' => 'page',
            'is_published' => true,
            'blocks' => [['id' => 'image', 'type' => 'image', 'data' => ['src' => $media->getUrl()]]],
        ]);

        $this->actingAs($user)
            ->deleteJson("/admin/media/{$media->id}")
            ->assertStatus(422)
            ->assertJsonPath('usage.count', 1);

        $this->assertDatabaseHas('media', [
            'id' => $media->id,
        ]);
    }

    public function test_media_library_accepts_common_document_uploads(): void
    {
        Storage::fake('uploads');
        Config::set('filesystems.disks.uploads.url', 'https://cdn.example.com/assets');

        $user = $this->adminUser();

        $this->actingAs($user)
            ->post('/admin/media/upload', [
                'file' => UploadedFile::fake()->create('manual.pdf', 128, 'application/pdf'),
                'folder' => 'uploads',
            ])
            ->assertCreated()
            ->assertJsonPath('kind', 'pdf')
            ->assertJsonPath('extension', 'pdf')
            ->assertJsonPath('folder', 'uploads');
    }

    public function test_media_library_accepts_browser_uploaded_pdf_with_generic_detected_content(): void
    {
        Storage::fake('uploads');
        Config::set('filesystems.disks.uploads.url', 'https://cdn.example.com/assets');

        $user = $this->adminUser();

        $this->actingAs($user)
            ->post('/admin/media/upload', [
                'file' => UploadedFile::fake()->createWithContent('merged.pdf', '<!DOCTYPE html><html><body>preview</body></html>'),
                'folder' => 'uploads',
            ])
            ->assertCreated()
            ->assertJsonPath('kind', 'pdf')
            ->assertJsonPath('extension', 'pdf');
    }

    public function test_media_item_can_be_moved_to_another_folder(): void
    {
        Storage::fake('uploads');
        Config::set('filesystems.disks.uploads.url', 'https://cdn.example.com/assets');

        $user = $this->adminUser();
        $library = Library::singleton();
        $media = $library
            ->addMedia(UploadedFile::fake()->image('hero.png'))
            ->toMediaCollection('uploads', 'uploads');

        $this->actingAs($user)
            ->patchJson("/admin/media/{$media->id}", [
                'folder' => 'documents',
            ])
            ->assertOk()
            ->assertJsonPath('folder', 'documents');

        $this->assertDatabaseHas('media', [
            'id' => $media->id,
            'collection_name' => 'documents',
        ]);
    }

    protected function adminUser(): User
    {
        $user = User::factory()->create();
        $user->assignRole(Role::findByName('admin', 'web'));

        return $user;
    }
}
