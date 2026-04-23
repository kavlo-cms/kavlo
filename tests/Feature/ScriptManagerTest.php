<?php

namespace Tests\Feature;

use App\Models\SiteScript;
use App\Models\User;
use App\Services\ScriptManager;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class ScriptManagerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);
    }

    public function test_admin_can_create_uploaded_script(): void
    {
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
        $user = $this->adminUser();

        $this->actingAs($user)
            ->post(route('admin.scripts.store'), [
                'name' => 'Tag Manager',
                'placement' => 'head',
                'source_type' => 'upload',
                'load_strategy' => 'defer',
                'sort_order' => 10,
                'is_enabled' => true,
                'notes' => 'Loads analytics container.',
                'file' => UploadedFile::fake()->createWithContent('tag-manager.js', 'console.log("tag-manager");'),
            ])
            ->assertRedirect(route('admin.scripts.index'));

        $script = SiteScript::query()->first();

        $this->assertNotNull($script);
        $this->assertSame('Tag Manager', $script->name);
        $this->assertSame('upload', $script->source_type);
        $this->assertSame('defer', $script->load_strategy);
        $this->assertTrue($script->is_enabled);
        $this->assertNotNull($script->file_path);
        Storage::disk('uploads')->assertExists($script->file_path);
    }

    public function test_admin_can_update_uploaded_script_to_external_url_and_old_file_is_removed(): void
    {
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

        $script = SiteScript::query()->create([
            'name' => 'Tag Manager',
            'placement' => 'head',
            'source_type' => 'upload',
            'file_path' => 'scripts/original.js',
            'load_strategy' => 'defer',
            'sort_order' => 10,
            'is_enabled' => true,
        ]);
        Storage::disk('uploads')->put('scripts/original.js', 'console.log("old");');

        $this->actingAs($this->adminUser())
            ->put(route('admin.scripts.update', $script), [
                'name' => 'Tag Manager CDN',
                'placement' => 'head',
                'source_type' => 'url',
                'source_url' => 'https://cdn.example.com/tag-manager.js',
                'load_strategy' => 'async',
                'sort_order' => 1,
                'is_enabled' => true,
                'notes' => 'Switched to CDN.',
            ])
            ->assertRedirect(route('admin.scripts.index'));

        $script->refresh();

        $this->assertSame('url', $script->source_type);
        $this->assertSame('https://cdn.example.com/tag-manager.js', $script->source_url);
        $this->assertNull($script->file_path);
        $this->assertSame('async', $script->load_strategy);
        Storage::disk('uploads')->assertMissing('scripts/original.js');
    }

    public function test_admin_can_delete_uploaded_script_and_its_file(): void
    {
        Config::set('cms.storage.public_disk', 'uploads');
        Config::set('filesystems.disks.uploads', [
            'driver' => 'local',
            'root' => storage_path('framework/testing/disks/uploads'),
            'url' => 'https://cdn.example.com/assets',
            'visibility' => 'public',
            'throw' => false,
        ]);
        Storage::fake('uploads');

        $script = SiteScript::query()->create([
            'name' => 'Footer Upload',
            'placement' => 'body_end',
            'source_type' => 'upload',
            'file_path' => 'scripts/footer.js',
            'load_strategy' => 'blocking',
            'sort_order' => 3,
            'is_enabled' => true,
        ]);
        Storage::disk('uploads')->put('scripts/footer.js', 'console.log("footer");');

        $this->actingAs($this->adminUser())
            ->delete(route('admin.scripts.destroy', $script))
            ->assertRedirect(route('admin.scripts.index'));

        $this->assertDatabaseMissing('site_scripts', [
            'id' => $script->id,
        ]);
        Storage::disk('uploads')->assertMissing('scripts/footer.js');
    }

    public function test_author_cannot_manage_scripts(): void
    {
        $author = User::factory()->create();
        $author->assignRole(Role::findByName('author', 'web'));

        $this->actingAs($author)
            ->post(route('admin.scripts.store'), [
                'name' => 'Blocked Script',
                'placement' => 'head',
                'source_type' => 'inline',
                'inline_content' => 'console.log("nope");',
                'load_strategy' => 'blocking',
            ])
            ->assertForbidden();
    }

    public function test_script_manager_renders_scripts_by_placement(): void
    {
        Config::set('cms.storage.public_disk', 'uploads');
        Config::set('filesystems.disks.uploads', [
            'driver' => 'local',
            'root' => storage_path('framework/testing/disks/uploads'),
            'url' => 'https://cdn.example.com/assets',
            'visibility' => 'public',
            'throw' => false,
        ]);

        SiteScript::query()->create([
            'name' => 'Head CDN',
            'placement' => 'head',
            'source_type' => 'url',
            'source_url' => 'https://cdn.example.com/app.js',
            'load_strategy' => 'defer',
            'sort_order' => 1,
            'is_enabled' => true,
        ]);

        SiteScript::query()->create([
            'name' => 'Body Start Inline',
            'placement' => 'body_start',
            'source_type' => 'inline',
            'inline_content' => 'console.log("body-start");',
            'load_strategy' => 'blocking',
            'sort_order' => 2,
            'is_enabled' => true,
        ]);

        SiteScript::query()->create([
            'name' => 'Uploaded Footer',
            'placement' => 'body_end',
            'source_type' => 'upload',
            'file_path' => 'scripts/footer.js',
            'load_strategy' => 'blocking',
            'sort_order' => 3,
            'is_enabled' => true,
        ]);

        $manager = app(ScriptManager::class);
        $manager->forget();

        $this->assertSame(
            '<script src="https://cdn.example.com/app.js" defer></script>',
            $manager->render('head'),
        );

        $this->assertStringContainsString('console.log("body-start");', $manager->render('body_start'));
        $this->assertStringContainsString('https://cdn.example.com/assets/scripts/footer.js', $manager->render('body_end'));
    }

    private function adminUser(): User
    {
        $user = User::factory()->create();
        $user->assignRole(Role::findByName('admin', 'web'));

        return $user;
    }
}
