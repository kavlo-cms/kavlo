<?php

namespace Tests\Feature;

use App\Models\Form;
use App\Models\Page;
use App\Models\Setting;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\View;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class PageContentEditorFeatureTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);
    }

    public function test_content_mode_page_renders_saved_html(): void
    {
        View::addNamespace('theme', base_path('themes/midnight-blue/views'));

        Page::create([
            'title' => 'Content page',
            'slug' => 'content-page',
            'type' => 'page',
            'editor_mode' => 'content',
            'content' => '<h1>Formatted content</h1><p>Rendered from the content editor.</p>',
            'is_published' => true,
            'blocks' => [],
        ]);

        $this->get('/content-page')
            ->assertOk()
            ->assertSee('<h1>Formatted content</h1>', false)
            ->assertSee('Rendered from the content editor.', false);
    }

    public function test_content_mode_page_can_render_blade_variables_and_loops(): void
    {
        View::addNamespace('theme', base_path('themes/midnight-blue/views'));

        Setting::set('site_name', 'Blade CMS');

        Page::create([
            'title' => 'About',
            'slug' => 'about',
            'type' => 'page',
            'editor_mode' => 'content',
            'content' => '<p>About page</p>',
            'is_published' => true,
            'blocks' => [],
        ]);

        Page::create([
            'title' => 'Landing',
            'slug' => 'landing',
            'type' => 'page',
            'editor_mode' => 'content',
            'content' => <<<'BLADE'
<h1>{{ $site['name'] }}</h1>
<ul>
@foreach ($pages as $entry)
    <li>{{ $entry['title'] }}</li>
@endforeach
</ul>
BLADE,
            'is_published' => true,
            'blocks' => [],
        ]);

        $this->get('/landing')
            ->assertOk()
            ->assertSee('Blade CMS')
            ->assertSee('Landing')
            ->assertSee('About');
    }

    public function test_content_mode_page_can_render_a_saved_form_with_helper(): void
    {
        View::addNamespace('theme', base_path('themes/midnight-blue/views'));

        Form::create([
            'name' => 'Contact',
            'slug' => 'contact',
            'blocks' => [
                [
                    'id' => 'field-1',
                    'type' => 'input',
                    'data' => [
                        'input_type' => 'email',
                        'label' => 'Email address',
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
            'submission_action' => 'core.store-submission',
            'action_config' => [],
        ]);

        Page::create([
            'title' => 'Contact',
            'slug' => 'contact-page',
            'type' => 'page',
            'editor_mode' => 'content',
            'content' => <<<'BLADE'
<section class="content-form">
    {!! kavlo_form('contact') !!}
</section>
BLADE,
            'is_published' => true,
            'blocks' => [],
        ]);

        $this->get('/contact-page')
            ->assertOk()
            ->assertSee('form-', false)
            ->assertSee('Email address')
            ->assertSee('Send')
            ->assertSee('content-form', false);
    }

    public function test_content_mode_page_renders_content_and_builder_blocks_when_both_exist(): void
    {
        View::addNamespace('theme', base_path('themes/midnight-blue/views'));

        Page::create([
            'title' => 'Hybrid content page',
            'slug' => 'hybrid-content-page',
            'type' => 'page',
            'editor_mode' => 'content',
            'content' => '<p>Intro paragraph from the content editor.</p>',
            'is_published' => true,
            'blocks' => [
                [
                    'id' => 'heading-1',
                    'type' => 'heading',
                    'data' => [
                        'text' => 'Builder heading',
                    ],
                    'order' => 0,
                ],
            ],
        ]);

        $this->get('/hybrid-content-page')
            ->assertOk()
            ->assertSeeInOrder([
                'Intro paragraph from the content editor.',
                'Builder heading',
            ], false);
    }

    public function test_builder_mode_page_renders_builder_blocks_and_content_when_both_exist(): void
    {
        View::addNamespace('theme', base_path('themes/midnight-blue/views'));

        Page::create([
            'title' => 'Hybrid builder page',
            'slug' => 'hybrid-builder-page',
            'type' => 'page',
            'editor_mode' => 'builder',
            'content' => '<p>Trailing content from the content editor.</p>',
            'is_published' => true,
            'blocks' => [
                [
                    'id' => 'heading-1',
                    'type' => 'heading',
                    'data' => [
                        'text' => 'Builder heading first',
                    ],
                    'order' => 0,
                ],
            ],
        ]);

        $this->get('/hybrid-builder-page')
            ->assertOk()
            ->assertSeeInOrder([
                'Builder heading first',
                'Trailing content from the content editor.',
            ], false);
    }

    public function test_hero_can_render_full_page_width_with_constrained_content(): void
    {
        View::addNamespace('theme', base_path('themes/midnight-blue/views'));

        Page::create([
            'title' => 'Hero constrained page',
            'slug' => 'hero-constrained-page',
            'type' => 'page',
            'editor_mode' => 'builder',
            'is_published' => true,
            'blocks' => [[
                'id' => 'hero-1',
                'type' => 'hero',
                'data' => [
                    'headline' => 'Constrained hero',
                    'width_mode' => 'full-page-constrained',
                ],
                'order' => 0,
            ]],
        ]);

        $this->get('/hero-constrained-page')
            ->assertOk()
            ->assertSee('w-screen max-w-none -translate-x-1/2', false)
            ->assertSee('mx-auto max-w-screen-xl px-6', false)
            ->assertSee('Constrained hero');
    }

    public function test_hero_can_render_full_page_width_with_unconstrained_content(): void
    {
        View::addNamespace('theme', base_path('themes/midnight-blue/views'));

        Page::create([
            'title' => 'Hero unconstrained page',
            'slug' => 'hero-unconstrained-page',
            'type' => 'page',
            'editor_mode' => 'builder',
            'is_published' => true,
            'blocks' => [[
                'id' => 'hero-1',
                'type' => 'hero',
                'data' => [
                    'headline' => 'Unconstrained hero',
                    'width_mode' => 'full-page-unconstrained',
                ],
                'order' => 0,
            ]],
        ]);

        $this->get('/hero-unconstrained-page')
            ->assertOk()
            ->assertSee('w-screen max-w-none -translate-x-1/2', false)
            ->assertSee('w-full px-6', false)
            ->assertDontSee('mx-auto max-w-screen-xl px-6', false)
            ->assertSee('Unconstrained hero');
    }

    public function test_hero_can_render_at_full_content_width(): void
    {
        View::addNamespace('theme', base_path('themes/midnight-blue/views'));

        Page::create([
            'title' => 'Hero content width page',
            'slug' => 'hero-content-width-page',
            'type' => 'page',
            'editor_mode' => 'builder',
            'is_published' => true,
            'blocks' => [[
                'id' => 'hero-1',
                'type' => 'hero',
                'data' => [
                    'headline' => 'Content width hero',
                    'width_mode' => 'full-content-width',
                ],
                'order' => 0,
            ]],
        ]);

        $this->get('/hero-content-width-page')
            ->assertOk()
            ->assertSee('mx-auto max-w-screen-xl', false)
            ->assertDontSee('w-screen max-w-none -translate-x-1/2', false)
            ->assertSee('Content width hero');
    }

    public function test_page_update_remains_compatible_before_editor_mode_migration_runs(): void
    {
        Schema::table('pages', function ($table) {
            $table->dropColumn('editor_mode');
        });

        Page::resetEditorModeSupportCache();

        $user = $this->adminUser();

        $page = Page::create([
            'title' => 'Draft Product Landing',
            'slug' => 'draft-product-landing',
            'type' => 'page',
            'content' => null,
            'is_published' => true,
            'is_homepage' => true,
            'blocks' => [],
            'metadata' => [],
        ]);

        $this->actingAs($user)
            ->put(route('admin.pages.update', $page), [
                'title' => 'Draft Product Landing',
                'slug' => 'draft-product-landing',
                'type' => 'page',
                'editor_mode' => 'content',
                'content' => "hello\n\nthis is me",
                'is_published' => true,
                'is_homepage' => true,
                'parent_id' => null,
                'blocks' => [],
                'metadata' => [],
                'meta_title' => null,
                'meta_description' => null,
                'og_image' => null,
                'publish_at' => null,
                'unpublish_at' => null,
            ])
            ->assertRedirect(route('admin.pages.edit', $page));

        $page->refresh();

        $this->assertSame("hello\n\nthis is me", $page->content);
        $this->assertSame('content', $page->editor_mode);

        Page::resetEditorModeSupportCache();
    }

    public function test_page_update_rejects_invalid_nested_builder_blocks(): void
    {
        $user = $this->adminUser();

        $page = Page::create([
            'title' => 'Draft Product Landing',
            'slug' => 'draft-product-landing',
            'type' => 'page',
            'editor_mode' => 'builder',
            'blocks' => [],
            'metadata' => [],
        ]);

        $this->actingAs($user)
            ->put(route('admin.pages.update', $page), [
                'title' => 'Draft Product Landing',
                'slug' => 'draft-product-landing',
                'type' => 'page',
                'editor_mode' => 'builder',
                'content' => null,
                'is_published' => false,
                'is_homepage' => false,
                'parent_id' => null,
                'blocks' => [
                    [
                        'id' => 'columns-1',
                        'type' => 'columns',
                        'data' => [
                            'count' => '2',
                            'col_0' => [
                                [
                                    'type' => 'heading',
                                    'data' => ['text' => 'Broken child'],
                                ],
                            ],
                        ],
                        'order' => 0,
                    ],
                ],
                'metadata' => [],
                'meta_title' => null,
                'meta_description' => null,
                'og_image' => null,
                'publish_at' => null,
                'unpublish_at' => null,
            ])
            ->assertSessionHasErrors('blocks');
    }

    protected function adminUser(): User
    {
        $user = User::factory()->create();
        $user->assignRole(Role::findByName('admin', 'web'));

        return $user;
    }
}
