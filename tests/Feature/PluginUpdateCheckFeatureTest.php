<?php

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use Inertia\Testing\AssertableInertia as Assert;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class PluginUpdateCheckFeatureTest extends TestCase
{
    use RefreshDatabase;

    private string $pluginDirectory;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);
        Cache::flush();

        $this->pluginDirectory = base_path('plugins/TestPluginUpdates');
        File::deleteDirectory($this->pluginDirectory);
        File::ensureDirectoryExists($this->pluginDirectory);

        File::put($this->pluginDirectory.'/plugin.json', json_encode([
            '$schema' => '../../resources/schemas/plugin.schema.json',
            'name' => 'Test Plugin Updates',
            'entrypoint' => 'Plugins\\TestPluginUpdates\\TestPluginUpdatesPlugin',
            'version' => '1.0.0',
            'update_url' => 'https://plugins.example.test/test-plugin-updates.json',
            'scopes' => ['hooks:write'],
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
    }

    protected function tearDown(): void
    {
        File::deleteDirectory($this->pluginDirectory);

        parent::tearDown();
    }

    public function test_plugins_screen_reports_available_plugin_updates(): void
    {
        Http::fake([
            'https://plugins.example.test/test-plugin-updates.json' => Http::response([
                'version' => '1.1.0',
                'release_url' => 'https://plugins.example.test/releases/test-plugin-updates-1.1.0',
            ]),
        ]);

        $this->actingAs($this->adminUser())
            ->get(route('admin.plugins.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $assert) => $assert
                ->component('Plugins/Index')
                ->where('pluginUpdateChecks.TestPluginUpdates.available', true)
                ->where('pluginUpdateChecks.TestPluginUpdates.currentVersion', '1.0.0')
                ->where('pluginUpdateChecks.TestPluginUpdates.latestVersion', '1.1.0')
                ->where('pluginUpdateChecks.TestPluginUpdates.releaseUrl', 'https://plugins.example.test/releases/test-plugin-updates-1.1.0')
            );
    }

    public function test_plugins_without_newer_versions_do_not_report_updates(): void
    {
        Http::fake([
            'https://plugins.example.test/test-plugin-updates.json' => Http::response([
                'version' => '1.0.0',
                'release_url' => 'https://plugins.example.test/releases/test-plugin-updates-1.0.0',
            ]),
        ]);

        $this->actingAs($this->adminUser())
            ->get(route('admin.plugins.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $assert) => $assert
                ->component('Plugins/Index')
                ->where('pluginUpdateChecks.TestPluginUpdates.available', false)
                ->where('pluginUpdateChecks.TestPluginUpdates.latestVersion', '1.0.0')
            );
    }

    private function adminUser(): User
    {
        $user = User::factory()->create();
        $user->assignRole(Role::findByName('admin', 'web'));

        return $user;
    }
}
