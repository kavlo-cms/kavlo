<?php

namespace Tests\Feature;

use App\Models\Form;
use App\Models\Menu;
use App\Models\MenuItem;
use App\Models\Page;
use App\Models\Setting;
use App\Services\ContentRouteRegistry;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ContentDataHubTest extends TestCase
{
    use RefreshDatabase;

    public function test_route_registry_and_graphql_expose_pages_menus_and_forms(): void
    {
        $home = Page::create([
            'title' => 'Home',
            'slug' => 'home',
            'type' => 'page',
            'is_homepage' => true,
            'is_published' => true,
        ]);

        $about = Page::create([
            'title' => 'About',
            'slug' => 'about',
            'type' => 'page',
            'is_published' => true,
        ]);

        Setting::set('homepage_id', $home->id);

        $menu = Menu::create([
            'name' => 'Main Navigation',
            'slug' => 'main',
        ]);

        MenuItem::create([
            'menu_id' => $menu->id,
            'label' => 'About',
            'page_id' => $about->id,
            'url' => '/about',
            'target' => '_self',
            'order' => 0,
        ]);

        Form::create([
            'name' => 'Contact',
            'slug' => 'contact',
            'blocks' => [
                [
                    'id' => 'field-1',
                    'type' => 'input',
                    'data' => [
                        'input_type' => 'email',
                        'label' => 'Email',
                        'key' => 'email',
                        'required' => true,
                    ],
                    'order' => 0,
                ],
                [
                    'id' => 'button-1',
                    'type' => 'button',
                    'data' => [
                        'label' => 'Send',
                    ],
                    'order' => 1,
                ],
            ],
        ]);

        $registry = app(ContentRouteRegistry::class);

        $this->assertSame('/', $registry->route('/')['path']);
        $this->assertSame('/about', $registry->route('/about')['path']);
        $this->assertSame('/forms/contact/submit', $registry->route('/forms/contact/submit')['path']);

        $response = $this->postJson('/graphql', [
            'query' => <<<'GRAPHQL'
                query ContentDataHub {
                  routes(types: ["page", "form", "menu"]) {
                    type
                    key
                    path
                  }
                  page(path: "/") {
                    title
                    path
                    isHomepage
                  }
                  menu(slug: "main") {
                    slug
                    items {
                      label
                      url
                    }
                  }
                  form(slug: "contact") {
                    slug
                    submissionAction
                    submissionPath
                    fields {
                      key
                      type
                      required
                    }
                  }
                }
            GRAPHQL,
        ]);

        $response->assertOk();
        $response->assertJsonMissingPath('errors.0');
        $response->assertJsonPath('data.page.title', 'Home');
        $response->assertJsonPath('data.page.path', '/home');
        $response->assertJsonPath('data.page.isHomepage', true);
        $response->assertJsonPath('data.menu.slug', 'main');
        $response->assertJsonPath('data.menu.items.0.label', 'About');
        $response->assertJsonPath('data.menu.items.0.url', url('about'));
        $response->assertJsonPath('data.form.slug', 'contact');
        $response->assertJsonPath('data.form.submissionAction', 'core.store-submission');
        $response->assertJsonPath('data.form.submissionPath', '/forms/contact/submit');
        $response->assertJsonPath('data.form.fields.0.key', 'email');
        $response->assertJsonPath('data.form.fields.0.required', true);
    }
}
