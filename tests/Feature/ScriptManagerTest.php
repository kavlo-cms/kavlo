<?php

namespace Tests\Feature;

use App\Models\SiteScript;
use App\Models\User;
use App\Services\ScriptManager;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class ScriptManagerTest extends TestCase
{
    use RefreshDatabase;

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
        Role::findOrCreate('admin', 'web');

        $user = User::factory()->create();
        $user->assignRole('admin');

        return $user;
    }
}
