<?php

namespace Tests\Feature;

use App\Models\Theme;
use App\Models\User;
use App\Services\PublicPageCache;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Mockery;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class AdminOperationsHardeningTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);
    }

    public function test_admin_can_activate_a_theme(): void
    {
        $currentTheme = Theme::query()->create([
            'name' => 'Current Theme',
            'slug' => 'current-theme',
            'path' => base_path('themes/current-theme'),
            'is_active' => true,
            'version' => '1.0.0',
        ]);
        $newTheme = Theme::query()->create([
            'name' => 'Midnight Blue',
            'slug' => Theme::DEFAULT_THEME_SLUG,
            'path' => base_path('themes/'.Theme::DEFAULT_THEME_SLUG),
            'is_active' => false,
            'version' => '1.0.0',
        ]);

        $this->actingAs($this->userWithRole('admin'))
            ->post(route('admin.themes.activate', $newTheme))
            ->assertRedirect();

        $this->assertFalse($currentTheme->fresh()->is_active);
        $this->assertTrue($newTheme->fresh()->is_active);
    }

    public function test_author_cannot_activate_a_theme(): void
    {
        $theme = Theme::query()->create([
            'name' => 'Midnight Blue',
            'slug' => Theme::DEFAULT_THEME_SLUG,
            'path' => base_path('themes/'.Theme::DEFAULT_THEME_SLUG),
            'is_active' => true,
            'version' => '1.0.0',
        ]);

        $this->actingAs($this->userWithRole('author'))
            ->post(route('admin.themes.activate', $theme))
            ->assertForbidden();
    }

    public function test_admin_can_enable_maintenance_mode_with_themed_rendering(): void
    {
        Artisan::shouldReceive('call')
            ->once()
            ->with('down', Mockery::on(function (array $args): bool {
                return $args['--render'] === 'errors::503'
                    && $args['--message'] === 'Scheduled maintenance'
                    && $args['--secret'] === 'preview-secret'
                    && $args['--retry'] === 120;
            }))
            ->andReturn(0);

        $this->actingAsConfirmed($this->userWithRole('admin'))
            ->post(route('admin.maintenance.enable'), [
                'message' => 'Scheduled maintenance',
                'secret' => 'preview-secret',
                'retry' => 120,
            ])
            ->assertRedirect();
    }

    public function test_admin_can_disable_maintenance_mode(): void
    {
        Artisan::shouldReceive('call')
            ->once()
            ->with('up')
            ->andReturn(0);

        $this->actingAsConfirmed($this->userWithRole('admin'))
            ->post(route('admin.maintenance.disable'))
            ->assertRedirect();
    }

    public function test_author_cannot_manage_maintenance_mode(): void
    {
        $this->actingAs($this->userWithRole('author'))
            ->post(route('admin.maintenance.enable'), [
                'message' => 'Blocked',
            ])
            ->assertForbidden();
    }

    public function test_admin_can_clear_public_page_cache(): void
    {
        $pageCache = Mockery::mock(PublicPageCache::class);
        $pageCache->shouldReceive('status')->andReturn([
            'enabled' => false,
            'driver' => 'file',
        ]);
        $pageCache->shouldReceive('flush')->once();
        $this->app->instance(PublicPageCache::class, $pageCache);

        $this->actingAsConfirmed($this->userWithRole('admin'))
            ->post(route('admin.cache.clear'), [
                'type' => 'page_html',
            ])
            ->assertRedirect();
    }

    public function test_admin_can_clear_all_caches(): void
    {
        Artisan::shouldReceive('call')->once()->with('cache:clear')->andReturn(0);
        Artisan::shouldReceive('call')->once()->with('view:clear')->andReturn(0);
        Artisan::shouldReceive('call')->once()->with('route:clear')->andReturn(0);
        Artisan::shouldReceive('call')->once()->with('config:clear')->andReturn(0);

        $pageCache = Mockery::mock(PublicPageCache::class);
        $pageCache->shouldReceive('status')->andReturn([
            'enabled' => false,
            'driver' => 'file',
        ]);
        $pageCache->shouldReceive('flush')->once();
        $this->app->instance(PublicPageCache::class, $pageCache);

        $this->actingAsConfirmed($this->userWithRole('admin'))
            ->post(route('admin.cache.clear'), [
                'type' => 'all',
            ])
            ->assertRedirect();
    }

    public function test_author_cannot_clear_cache(): void
    {
        $this->actingAs($this->userWithRole('author'))
            ->post(route('admin.cache.clear'), [
                'type' => 'all',
            ])
            ->assertForbidden();
    }

    protected function actingAsConfirmed(User $user): static
    {
        return $this->actingAs($user)->withSession([
            'auth.password_confirmed_at' => time(),
        ]);
    }

    private function userWithRole(string $role): User
    {
        $user = User::factory()->create();
        $user->assignRole(Role::findByName($role, 'web'));

        return $user;
    }
}
