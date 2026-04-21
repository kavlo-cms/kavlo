<?php

namespace Tests\Feature;

use App\Models\Page;
use App\Models\PageView;
use App\Models\Theme;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\View;
use Inertia\Testing\AssertableInertia as Assert;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class AnalyticsFeatureTest extends TestCase
{
    use RefreshDatabase;

    public function test_public_page_view_is_tracked(): void
    {
        $page = $this->publishedPage();

        $this->withHeader('referer', 'https://example-referrer.test/somewhere')
            ->get('/'.$page->slug.'?utm_source=newsletter&utm_medium=email')
            ->assertOk();

        $view = PageView::query()->first();

        $this->assertNotNull($view);
        $this->assertSame($page->id, $view->page_id);
        $this->assertSame('/'.$page->slug, $view->path);
        $this->assertSame('example-referrer.test', $view->referrer_host);
        $this->assertSame('newsletter', $view->utm_source);
        $this->assertSame('email', $view->utm_medium);
    }

    public function test_admin_can_view_analytics_dashboard(): void
    {
        $admin = $this->adminUser();
        $page = $this->publishedPage();

        PageView::query()->create([
            'page_id' => $page->id,
            'path' => '/'.$page->slug,
            'viewed_on' => now()->toDateString(),
            'visitor_hash' => hash('sha256', 'visitor-a'),
            'session_id' => 'session-a',
            'referrer_host' => 'search.example',
            'utm_source' => 'newsletter',
            'utm_medium' => 'email',
            'utm_campaign' => 'spring-launch',
            'utm_term' => null,
            'utm_content' => null,
            'device_type' => 'desktop',
            'user_agent' => 'Test Agent',
            'created_at' => now(),
        ]);

        PageView::query()->create([
            'page_id' => $page->id,
            'path' => '/'.$page->slug,
            'viewed_on' => now()->toDateString(),
            'visitor_hash' => hash('sha256', 'visitor-b'),
            'session_id' => 'session-b',
            'referrer_host' => 'search.example',
            'utm_source' => null,
            'utm_medium' => null,
            'utm_campaign' => null,
            'utm_term' => null,
            'utm_content' => null,
            'device_type' => 'mobile',
            'user_agent' => 'Test Agent',
            'created_at' => now(),
        ]);

        $this->actingAs($admin)
            ->get(route('admin.analytics.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $pageAssert) => $pageAssert
                ->component('Analytics/Index')
                ->where('summary.total_views', 2)
                ->where('summary.unique_visitors', 2)
                ->has('top_pages', 1)
                ->where('top_pages.0.title', $page->title)
                ->has('top_referrers', 1)
                ->where('top_referrers.0.host', 'search.example')
            );
    }

    private function publishedPage(): Page
    {
        View::addNamespace('theme', base_path('themes/midnight-blue/views'));

        Theme::query()->create([
            'name' => 'Midnight Blue',
            'slug' => 'midnight-blue',
            'path' => base_path('themes/midnight-blue'),
            'is_active' => true,
        ]);

        return Page::query()->create([
            'title' => 'Analytics Page',
            'slug' => 'analytics-page',
            'type' => 'page',
            'is_published' => true,
            'is_homepage' => false,
            'blocks' => [],
            'metadata' => [],
        ]);
    }

    private function adminUser(): User
    {
        Role::findOrCreate('admin', 'web');

        $user = User::factory()->create();
        $user->assignRole('admin');

        return $user;
    }
}
