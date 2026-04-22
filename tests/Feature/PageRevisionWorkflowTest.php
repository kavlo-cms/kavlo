<?php

namespace Tests\Feature;

use App\Models\Page;
use App\Models\Revision;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class PageRevisionWorkflowTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);
    }

    public function test_page_edit_exposes_revision_history(): void
    {
        $user = $this->adminUser();
        $page = Page::create([
            'title' => 'Landing',
            'slug' => 'landing',
            'type' => 'page',
            'blocks' => [['id' => 'hero', 'type' => 'heading', 'data' => ['text' => 'Before']]],
            'metadata' => ['robots' => 'index,follow'],
            'meta_title' => 'Before title',
        ]);

        Revision::create([
            'page_id' => $page->id,
            'user_id' => $user->id,
            'label' => 'Saved 2026-04-17 16:30',
            'content_snapshot' => $page->blocks,
            'meta_snapshot' => $page->metadata,
            'page_snapshot' => $page->revisionSnapshot(),
        ]);

        $this->actingAs($user)
            ->get(route('admin.pages.edit', $page))
            ->assertOk()
            ->assertInertia(fn (Assert $assert) => $assert
                ->component('Pages/Edit')
                ->has('revisions', 1)
                ->where('page.editor_mode', 'builder')
                ->where('themeConfig.blockStyles.textColorPresets.0.label', 'Moonlight')
                ->where('themeConfig.blockStyles.textColorPresets.0.value', '#e2e8f0')
                ->where('revisions.0.label', 'Saved 2026-04-17 16:30')
                ->where('revisions.0.user.name', $user->name)
                ->where('revisions.0.preview_url', route('admin.pages.revisions.preview', [$page, $page->revisions()->first()]))
            );
    }

    public function test_restoring_revision_restores_page_snapshot_and_creates_restore_point(): void
    {
        $user = $this->adminUser();
        $page = Page::create([
            'title' => 'Landing',
            'slug' => 'landing',
            'type' => 'page',
            'editor_mode' => 'builder',
            'is_published' => false,
            'blocks' => [['id' => 'hero', 'type' => 'heading', 'data' => ['text' => 'Before']]],
            'metadata' => ['robots' => 'index,follow'],
            'meta_title' => 'Before title',
            'meta_description' => 'Before description',
        ]);

        $this->actingAs($user)->put(route('admin.pages.update', $page), [
            'title' => 'Landing updated',
            'slug' => 'landing-updated',
            'type' => 'page',
            'editor_mode' => 'content',
            'content' => '<p>Rich text</p>',
            'is_published' => true,
            'is_homepage' => false,
            'parent_id' => null,
            'blocks' => [['id' => 'hero', 'type' => 'heading', 'data' => ['text' => 'After']]],
            'metadata' => ['robots' => 'noindex,nofollow'],
            'meta_title' => 'After title',
            'meta_description' => 'After description',
            'og_image' => null,
            'publish_at' => null,
            'unpublish_at' => null,
        ])->assertRedirect(route('admin.pages.edit', $page));

        $revision = $page->fresh()->revisions()->first();

        $this->assertNotNull($revision);
        $this->assertSame('Landing', $revision->page_snapshot['title']);
        $this->assertSame('landing', $revision->page_snapshot['slug']);

        $this->actingAs($user)
            ->post(route('admin.pages.revisions.restore', [$page, $revision]))
            ->assertRedirect(route('admin.pages.edit', $page));

        $page->refresh();

        $this->assertSame('Landing', $page->title);
        $this->assertSame('landing', $page->slug);
        $this->assertSame('builder', $page->editor_mode);
        $this->assertFalse($page->is_published);
        $this->assertSame('Before title', $page->meta_title);
        $this->assertSame('Before description', $page->meta_description);
        $this->assertSame('Before', data_get($page->blocks, '0.data.text'));
        $this->assertSame('index,follow', data_get($page->metadata, 'robots'));
        $this->assertSame(2, $page->revisions()->count());
    }

    public function test_revision_preview_renders_saved_snapshot(): void
    {
        $user = $this->adminUser();
        $page = Page::create([
            'title' => 'Landing updated',
            'slug' => 'landing-updated',
            'type' => 'page',
            'editor_mode' => 'content',
            'content' => '<p>After</p>',
            'blocks' => [['id' => 'hero', 'type' => 'heading', 'data' => ['text' => 'After']]],
            'metadata' => ['robots' => 'noindex,nofollow'],
        ]);

        $revision = Revision::create([
            'page_id' => $page->id,
            'user_id' => $user->id,
            'label' => 'Saved 2026-04-17 16:30',
            'content_snapshot' => [['id' => 'hero', 'type' => 'heading', 'data' => ['text' => 'Before']]],
            'meta_snapshot' => ['robots' => 'index,follow'],
            'page_snapshot' => [
                ...$page->revisionSnapshot(),
                'title' => 'Landing',
                'slug' => 'landing',
                'editor_mode' => 'builder',
                'content' => null,
            ],
        ]);

        $this->actingAs($user)
            ->get(route('admin.pages.revisions.preview', [$page, $revision]))
            ->assertOk()
            ->assertSee('Landing', false)
            ->assertSee('Before', false)
            ->assertDontSee('Landing updated', false)
            ->assertDontSee('After', false);
    }

    public function test_live_preview_renders_saved_block_text_color(): void
    {
        $user = $this->adminUser();
        $page = Page::create([
            'title' => 'Landing',
            'slug' => 'landing',
            'type' => 'page',
            'editor_mode' => 'builder',
            'blocks' => [['id' => 'hero', 'type' => 'heading', 'data' => ['text' => 'Before']]],
        ]);

        $this->actingAs($user)
            ->post(route('admin.pages.preview.live', $page), [
                'title' => 'Landing',
                'slug' => 'landing',
                'type' => 'page',
                'editor_mode' => 'builder',
                'blocks' => [[
                    'id' => 'hero',
                    'type' => 'heading',
                    'order' => 1,
                    'data' => [
                        'text' => 'Colored heading',
                        'text_color' => '#22c55e',
                    ],
                ]],
            ])
            ->assertOk()
            ->assertSee('Colored heading', false)
            ->assertSee('style="color: #22c55e"', false);
    }

    public function test_live_preview_renders_saved_heading_gradient(): void
    {
        $user = $this->adminUser();
        $page = Page::create([
            'title' => 'Landing',
            'slug' => 'landing',
            'type' => 'page',
            'editor_mode' => 'builder',
            'blocks' => [['id' => 'hero', 'type' => 'heading', 'data' => ['text' => 'Before']]],
        ]);

        $this->actingAs($user)
            ->post(route('admin.pages.preview.live', $page), [
                'title' => 'Landing',
                'slug' => 'landing',
                'type' => 'page',
                'editor_mode' => 'builder',
                'blocks' => [[
                    'id' => 'hero',
                    'type' => 'heading',
                    'order' => 1,
                    'data' => [
                        'text' => 'Gradient heading',
                        'text_gradient' => [
                            'start' => '#38bdf8',
                            'end' => '#818cf8',
                            'angle' => 90,
                        ],
                    ],
                ]],
            ])
            ->assertOk()
            ->assertSee('Gradient heading', false)
            ->assertSee(
                'background-image: linear-gradient(90deg, #38bdf8, #818cf8);',
                false,
            )
            ->assertSee('color: transparent;', false);
    }

    public function test_live_preview_renders_saved_button_gradient(): void
    {
        $user = $this->adminUser();
        $page = Page::create([
            'title' => 'Landing',
            'slug' => 'landing',
            'type' => 'page',
            'editor_mode' => 'builder',
            'blocks' => [['id' => 'cta', 'type' => 'button', 'data' => ['text' => 'Before']]],
        ]);

        $this->actingAs($user)
            ->post(route('admin.pages.preview.live', $page), [
                'title' => 'Landing',
                'slug' => 'landing',
                'type' => 'page',
                'editor_mode' => 'builder',
                'blocks' => [[
                    'id' => 'cta',
                    'type' => 'button',
                    'order' => 1,
                    'data' => [
                        'text' => 'Gradient button',
                        'gradient' => [
                            'start' => '#2563eb',
                            'end' => '#7c3aed',
                            'angle' => 135,
                        ],
                    ],
                ]],
            ])
            ->assertOk()
            ->assertSee('Gradient button', false)
            ->assertSee(
                'background-image: linear-gradient(135deg, #2563eb, #7c3aed);',
                false,
            )
            ->assertSee('border-color: transparent;', false);
    }

    protected function adminUser(): User
    {
        $user = User::factory()->create();
        $user->assignRole(Role::findByName('admin', 'web'));

        return $user;
    }
}
