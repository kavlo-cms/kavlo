<?php

namespace Tests\Feature;

use App\Models\Page;
use App\Models\Setting;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Inertia\Testing\AssertableInertia as Assert;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class PublicPageCacheFeatureTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Cache::flush();
        $this->seed(RolesAndPermissionsSeeder::class);
        config()->set('cms.cache.public_pages.enabled', true);
        config()->set('cms.cache.public_pages.ttl_seconds', 300);
    }

    public function test_guest_queryless_page_requests_use_the_public_page_cache(): void
    {
        Setting::set('site_name', 'Cached Version A');
        $page = $this->publishedPage([
            'slug' => 'cached-page',
            'editor_mode' => 'content',
            'content' => '<p>{{ $site[\'name\'] }}</p>',
        ]);

        $this->get('/'.$page->slug)
            ->assertOk()
            ->assertHeader('X-CMS-Page-Cache', 'miss')
            ->assertSee('Cached Version A', false);

        Setting::query()->updateOrCreate(['key' => 'site_name'], ['value' => 'Cached Version B']);
        Cache::forget('settings.all');

        $this->get('/'.$page->slug)
            ->assertOk()
            ->assertHeader('X-CMS-Page-Cache', 'hit')
            ->assertSee('Cached Version A', false)
            ->assertDontSee('Cached Version B', false);
    }

    public function test_query_string_requests_bypass_the_public_page_cache(): void
    {
        $page = $this->publishedPage([
            'slug' => 'uncached-query-page',
            'editor_mode' => 'content',
            'content' => '<p>Query page</p>',
        ]);

        $this->get('/'.$page->slug.'?preview=1')
            ->assertOk()
            ->assertHeaderMissing('X-CMS-Page-Cache');

        $this->get('/'.$page->slug.'?preview=1')
            ->assertOk()
            ->assertHeaderMissing('X-CMS-Page-Cache');
    }

    public function test_setting_updates_invalidate_the_public_page_cache(): void
    {
        Setting::set('site_name', 'Alpha');
        $page = $this->publishedPage([
            'slug' => 'settings-invalidated-page',
            'editor_mode' => 'content',
            'content' => '<p>{{ $site[\'name\'] }}</p>',
        ]);

        $this->get('/'.$page->slug)->assertHeader('X-CMS-Page-Cache', 'miss');
        $this->get('/'.$page->slug)->assertHeader('X-CMS-Page-Cache', 'hit');

        Setting::set('site_name', 'Beta');

        $this->get('/'.$page->slug)
            ->assertOk()
            ->assertHeader('X-CMS-Page-Cache', 'miss')
            ->assertSee('Beta', false);
    }

    public function test_admin_cache_screen_exposes_page_cache_status_and_can_clear_it(): void
    {
        $page = $this->publishedPage([
            'slug' => 'clearable-page',
            'editor_mode' => 'content',
            'content' => '<p>Clearable</p>',
        ]);

        $this->get('/'.$page->slug)->assertHeader('X-CMS-Page-Cache', 'miss');
        $this->get('/'.$page->slug)->assertHeader('X-CMS-Page-Cache', 'hit');

        $admin = $this->adminUser();

        $this->actingAs($admin)
            ->get(route('admin.cache.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $assert) => $assert
                ->component('Cache/Index')
                ->where('publicPageCache.enabled', true)
                ->where('publicPageCache.ttl_seconds', 300)
                ->where('publicPageCache.cache_scope', 'Guest GET page requests without query strings')
            );

        $this->actingAsConfirmed($admin)
            ->post(route('admin.cache.clear'), ['type' => 'page_html'])
            ->assertRedirect();

        auth()->guard()->logout();

        $this->get('/'.$page->slug)
            ->assertOk()
            ->assertHeader('X-CMS-Page-Cache', 'miss');
    }

    private function publishedPage(array $attributes = []): Page
    {
        return Page::create(array_merge([
            'title' => 'Cached page',
            'slug' => 'cached-page',
            'type' => 'page',
            'editor_mode' => 'content',
            'content' => '<p>Cached</p>',
            'blocks' => [],
            'metadata' => [],
            'is_published' => true,
        ], $attributes));
    }

    private function adminUser(): User
    {
        $user = User::factory()->create();
        $user->assignRole(Role::findByName('admin', 'web'));

        return $user;
    }

    private function actingAsConfirmed(User $user): static
    {
        return $this->actingAs($user)->withSession([
            'auth.password_confirmed_at' => time(),
        ]);
    }
}
