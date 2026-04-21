<?php

namespace Tests\Feature;

use App\Models\EmailTemplate;
use App\Models\Form;
use App\Models\Menu;
use App\Models\Page;
use App\Models\Redirect;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\View;
use Inertia\Testing\AssertableInertia as Assert;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class SearchFeatureTest extends TestCase
{
    use RefreshDatabase;

    public function test_public_search_returns_only_published_pages(): void
    {
        View::addNamespace('theme', base_path('themes/midnight-blue/views'));

        Page::create([
            'title' => 'Coffee Roastery',
            'slug' => 'coffee-roastery',
            'type' => 'page',
            'is_published' => true,
            'blocks' => [['id' => 'hero', 'type' => 'text', 'data' => ['text' => 'Single origin coffee beans']]],
        ]);

        Page::create([
            'title' => 'Coffee Draft',
            'slug' => 'coffee-draft',
            'type' => 'page',
            'is_published' => false,
            'blocks' => [['id' => 'hero', 'type' => 'text', 'data' => ['text' => 'Draft content']]],
        ]);

        $response = $this->get('/search?q=coffee');

        $response->assertOk();
        $response->assertSee('Coffee Roastery');
        $response->assertDontSee('Coffee Draft');
    }

    public function test_admin_search_groups_results_across_cms_content(): void
    {
        $user = $this->adminUser();

        Page::create([
            'title' => 'Shipping Policy',
            'slug' => 'shipping-policy',
            'type' => 'page',
            'is_published' => true,
        ]);

        Form::create([
            'name' => 'Shipping Quote',
            'slug' => 'shipping-quote',
            'description' => 'Request a shipping estimate',
            'blocks' => [],
        ]);

        Menu::create([
            'name' => 'Shipping Footer',
            'slug' => 'shipping-footer',
        ]);

        EmailTemplate::create([
            'name' => 'Shipping Notice',
            'slug' => 'shipping-notice',
            'subject' => 'Your shipment is on the way',
            'blocks' => [],
        ]);

        Redirect::create([
            'from_url' => '/shipping-old',
            'to_url' => '/shipping-policy',
            'type' => 301,
            'is_active' => true,
        ]);

        $this->actingAs($user)
            ->get('/admin/search?q=shipping')
            ->assertOk()
            ->assertInertia(fn (Assert $assert) => $assert
                ->component('Search/Index')
                ->where('query', 'shipping')
                ->has('results.pages', 1)
                ->where('results.pages.0.title', 'Shipping Policy')
                ->has('results.forms', 1)
                ->where('results.forms.0.title', 'Shipping Quote')
                ->has('results.menus', 1)
                ->where('results.menus.0.title', 'Shipping Footer')
                ->has('results.emailTemplates', 1)
                ->where('results.emailTemplates.0.title', 'Shipping Notice')
                ->has('results.redirects', 1)
                ->where('results.redirects.0.title', '/shipping-old')
            );
    }

    protected function adminUser(): User
    {
        Role::findOrCreate('admin', 'web');

        $user = User::factory()->create();
        $user->assignRole('admin');

        return $user;
    }
}
