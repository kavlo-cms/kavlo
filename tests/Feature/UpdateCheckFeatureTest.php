<?php

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Inertia\Testing\AssertableInertia as Assert;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class UpdateCheckFeatureTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);
    }

    public function test_admin_dashboard_includes_available_update_notice(): void
    {
        config()->set('app.version', '1.0.0');

        Http::fake([
            'https://api.github.com/repos/kavlo-cms/kavlo/releases/latest' => Http::response([
                'tag_name' => 'v1.1.0',
                'html_url' => 'https://github.com/kavlo-cms/kavlo/releases/tag/v1.1.0',
                'published_at' => now()->subDay()->toIso8601String(),
            ]),
        ]);

        $this->actingAs($this->adminUser())
            ->get(route('admin.dashboard'))
            ->assertOk()
            ->assertInertia(fn (Assert $assert) => $assert
                ->component('Dashboard/Index')
                ->where('updateCheck.available', true)
                ->where('updateCheck.currentVersion', '1.0.0')
                ->where('updateCheck.latestVersion', '1.1.0')
                ->where('updateCheck.releaseUrl', 'https://github.com/kavlo-cms/kavlo/releases/tag/v1.1.0')
            );
    }

    public function test_admin_dashboard_reports_no_update_when_current_version_is_latest(): void
    {
        config()->set('app.version', '1.1.0');

        Http::fake([
            'https://api.github.com/repos/kavlo-cms/kavlo/releases/latest' => Http::response([
                'tag_name' => 'v1.1.0',
                'html_url' => 'https://github.com/kavlo-cms/kavlo/releases/tag/v1.1.0',
                'published_at' => now()->subDay()->toIso8601String(),
            ]),
        ]);

        $this->actingAs($this->adminUser())
            ->get(route('admin.dashboard'))
            ->assertOk()
            ->assertInertia(fn (Assert $assert) => $assert
                ->component('Dashboard/Index')
                ->where('updateCheck.available', false)
                ->where('updateCheck.currentVersion', '1.1.0')
                ->where('updateCheck.latestVersion', '1.1.0')
            );
    }

    public function test_admin_dashboard_falls_back_to_tagged_release_page(): void
    {
        config()->set('app.version', '1.0.0');

        Http::fake([
            'https://api.github.com/repos/kavlo-cms/kavlo/releases/latest' => Http::response([
                'tag_name' => 'v1.1.0',
                'published_at' => now()->subDay()->toIso8601String(),
            ]),
        ]);

        $this->actingAs($this->adminUser())
            ->get(route('admin.dashboard'))
            ->assertOk()
            ->assertInertia(fn (Assert $assert) => $assert
                ->component('Dashboard/Index')
                ->where('updateCheck.available', true)
                ->where('updateCheck.releaseUrl', 'https://github.com/kavlo-cms/kavlo/releases/tag/v1.1.0')
            );
    }

    private function adminUser(): User
    {
        $user = User::factory()->create();
        $user->assignRole(Role::findByName('admin', 'web'));

        return $user;
    }
}
