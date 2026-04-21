<?php

namespace Tests\Feature;

use App\Models\Page;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Activitylog\Models\Activity;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class ActivityLogTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_menu_creation_is_logged(): void
    {
        $user = $this->adminUser();
        $before = Activity::count();

        $this->actingAs($user)
            ->post(route('admin.menus.store'), [
                'name' => 'Main Menu',
                'slug' => 'main-menu',
            ])
            ->assertRedirect();

        $activity = Activity::query()
            ->where('log_name', 'admin')
            ->where('description', 'created menu "Main Menu"')
            ->where('properties->route_name', 'admin.menus.store')
            ->latest()
            ->first();

        $this->assertNotNull($activity);
        $this->assertGreaterThan($before, Activity::count());
        $this->assertSame('admin', $activity->log_name);
        $this->assertSame('created menu "Main Menu"', $activity->description);
        $this->assertSame('admin.menus.store', data_get($activity->properties, 'route_name'));
        $this->assertSame(['name', 'slug'], data_get($activity->properties, 'changed_fields'));
    }

    public function test_admin_settings_update_is_logged(): void
    {
        $user = $this->adminUser();
        $before = Activity::count();

        $this->actingAs($user)
            ->put(route('admin.settings.update'), [
                'site_name' => 'CMS',
                'site_tagline' => 'Better activity log',
                'admin_email' => 'admin@example.com',
                'meta_title_format' => '%page_title% | %site_name%',
                'meta_description' => 'Default description',
                'homepage_id' => null,
                'favicon' => '/favicon.ico',
                'head_scripts' => '',
                'body_scripts' => '',
            ])
            ->assertRedirect();

        $activity = Activity::query()
            ->where('log_name', 'admin')
            ->where('description', 'updated general settings')
            ->where('properties->route_name', 'admin.settings.update')
            ->latest()
            ->first();

        $this->assertNotNull($activity);
        $this->assertGreaterThan($before, Activity::count());
        $this->assertSame('updated general settings', $activity->description);
        $this->assertSame('admin.settings.update', data_get($activity->properties, 'route_name'));
        $this->assertContains('site_name', data_get($activity->properties, 'changed_fields', []));
    }

    public function test_get_requests_do_not_create_activity_entries(): void
    {
        $user = $this->adminUser();
        $before = Activity::count();

        $this->actingAs($user)
            ->get(route('admin.activity.index'))
            ->assertOk();

        $this->assertSame($before, Activity::count());
    }

    public function test_page_content_updates_are_logged_in_admin_activity(): void
    {
        $user = $this->adminUser();
        $page = Page::query()->create([
            'title' => 'Landing Page',
            'slug' => 'landing-page',
            'type' => 'page',
            'is_published' => true,
            'blocks' => [],
            'metadata' => [],
        ]);

        $this->actingAs($user)
            ->put(route('admin.pages.update', $page), [
                'title' => 'Landing Page',
                'slug' => 'landing-page',
                'type' => 'page',
                'is_published' => true,
                'is_homepage' => false,
                'parent_id' => null,
                'blocks' => [
                    [
                        'id' => 'hero-1',
                        'type' => 'hero',
                        'data' => ['headline' => 'Updated hero'],
                        'order' => 0,
                    ],
                ],
                'metadata' => [],
                'create_redirect' => false,
                'meta_title' => null,
                'meta_description' => null,
                'og_image' => null,
                'publish_at' => null,
                'unpublish_at' => null,
            ])
            ->assertRedirect();

        $activity = Activity::query()
            ->where('log_name', 'admin')
            ->where('description', 'updated Page "Landing Page"')
            ->where('properties->route_name', 'admin.pages.update')
            ->latest()
            ->first();

        $this->assertNotNull($activity);
        $this->assertSame('admin.pages.update', data_get($activity->properties, 'route_name'));
    }

    private function adminUser(): User
    {
        Role::findOrCreate('admin', 'web');

        $user = User::factory()->create();
        $user->assignRole('admin');

        return $user;
    }
}
