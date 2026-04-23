<?php

namespace Tests\Feature;

use App\Models\Page;
use App\Models\SiteLanguage;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\View;
use Inertia\Testing\AssertableInertia as Assert;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class PageLocalizationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);
        View::addNamespace('theme', base_path('themes/midnight-blue/views'));
    }

    public function test_public_site_resolves_default_and_localized_page_paths(): void
    {
        SiteLanguage::query()->update(['name' => 'English']);
        SiteLanguage::query()->create([
            'code' => 'no',
            'name' => 'Norwegian',
            'is_default' => false,
            'is_active' => true,
        ]);

        $page = Page::create([
            'title' => 'About',
            'slug' => 'about',
            'type' => 'page',
            'editor_mode' => 'content',
            'content' => '<h1>About</h1>',
            'is_published' => true,
            'blocks' => [],
            'metadata' => [],
        ]);

        $page->translations()->create([
            'locale' => 'no',
            'title' => 'Om oss',
            'slug' => 'om-oss',
            'content' => '<h1>Om oss</h1>',
            'is_published' => true,
            'blocks' => [],
            'metadata' => [],
        ]);

        $this->get('/about')
            ->assertOk()
            ->assertSee('About', false)
            ->assertDontSee('Om oss', false);

        $this->get('/no/om-oss')
            ->assertOk()
            ->assertSee('Om oss', false)
            ->assertDontSee('About', false);

        $this->get('/no/about')->assertNotFound();
    }

    public function test_page_editor_loads_the_requested_locale_variant(): void
    {
        SiteLanguage::query()->update(['name' => 'English']);
        SiteLanguage::query()->create([
            'code' => 'no',
            'name' => 'Norwegian',
            'is_default' => false,
            'is_active' => true,
        ]);

        $page = Page::create([
            'title' => 'About',
            'slug' => 'about',
            'type' => 'page',
            'editor_mode' => 'content',
            'content' => '<p>About</p>',
            'blocks' => [],
            'metadata' => [],
        ]);

        $page->translations()->create([
            'locale' => 'no',
            'title' => 'Om oss',
            'slug' => 'om-oss',
            'content' => '<p>Om oss</p>',
            'is_published' => true,
            'blocks' => [],
            'metadata' => [],
        ]);

        $this->actingAs($this->adminUser())
            ->get(route('admin.pages.edit', ['page' => $page, 'locale' => 'no']))
            ->assertOk()
            ->assertInertia(fn (Assert $assert) => $assert
                ->component('Pages/Edit')
                ->where('selectedLocale', 'no')
                ->where('page.title', 'Om oss')
                ->where('page.slug', 'om-oss')
                ->where('page.translation_exists', true)
                ->has('locales', 2)
            );
    }

    public function test_general_settings_can_add_languages_and_change_the_default_locale(): void
    {
        $this->actingAs($this->adminUser())
            ->put(route('admin.settings.update'), [
                'site_name' => 'Kavlo',
                'site_tagline' => 'Builder first',
                'admin_email' => 'admin@example.com',
                'meta_title_format' => '%page_title% | %site_name%',
                'meta_description' => 'A multilingual CMS.',
                'homepage_id' => null,
                'favicon' => '/favicon.ico',
                'default_locale' => 'en',
                'languages' => [
                    [
                        'code' => 'en',
                        'name' => 'English',
                        'is_active' => true,
                    ],
                    [
                        'code' => 'no',
                        'name' => 'Norwegian',
                        'is_active' => true,
                    ],
                ],
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('site_languages', [
            'code' => 'en',
            'name' => 'English',
            'is_default' => true,
        ]);

        $this->assertDatabaseHas('site_languages', [
            'code' => 'no',
            'name' => 'Norwegian',
            'is_active' => true,
        ]);
    }

    private function adminUser(): User
    {
        $user = User::factory()->create();
        $user->assignRole(Role::findByName('admin', 'web'));

        return $user;
    }
}
