<?php

namespace Tests\Feature;

use App\Models\Page;
use App\Models\Revision;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class AdminPermissionWorkflowTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);
    }

    public function test_author_cannot_access_sensitive_admin_modules(): void
    {
        $author = $this->userWithRole('author');

        $this->actingAs($author)->get(route('admin.plugins.index'))->assertForbidden();
        $this->actingAs($author)->get(route('admin.analytics.index'))->assertForbidden();
        $this->actingAs($author)->get(route('admin.redirects.index'))->assertForbidden();
    }

    public function test_author_can_edit_draft_page_but_cannot_publish_it(): void
    {
        $author = $this->userWithRole('author');
        $page = Page::create([
            'title' => 'Draft page',
            'slug' => 'draft-page',
            'type' => 'page',
            'is_published' => false,
            'blocks' => [],
            'metadata' => [],
        ]);

        $this->actingAs($author)
            ->put(route('admin.pages.update', $page), [
                'title' => 'Draft page updated',
                'slug' => 'draft-page',
                'type' => 'page',
                'content' => null,
                'is_published' => false,
                'is_homepage' => false,
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

        $this->actingAs($author)
            ->put(route('admin.pages.update', $page), [
                'title' => 'Draft page updated',
                'slug' => 'draft-page',
                'type' => 'page',
                'content' => null,
                'is_published' => true,
                'is_homepage' => false,
                'parent_id' => null,
                'blocks' => [],
                'metadata' => [],
                'meta_title' => null,
                'meta_description' => null,
                'og_image' => null,
                'publish_at' => null,
                'unpublish_at' => null,
            ])
            ->assertForbidden();
    }

    public function test_editor_can_restore_page_revision(): void
    {
        $editor = $this->userWithRole('editor');
        $page = Page::create([
            'title' => 'Landing updated',
            'slug' => 'landing-updated',
            'type' => 'page',
            'is_published' => true,
            'blocks' => [['id' => 'hero', 'type' => 'heading', 'data' => ['text' => 'After']]],
            'metadata' => [],
        ]);

        $revision = Revision::create([
            'page_id' => $page->id,
            'user_id' => $editor->id,
            'label' => 'Saved revision',
            'content_snapshot' => [['id' => 'hero', 'type' => 'heading', 'data' => ['text' => 'Before']]],
            'meta_snapshot' => [],
            'page_snapshot' => [
                ...$page->revisionSnapshot(),
                'title' => 'Landing',
                'slug' => 'landing',
                'is_published' => false,
            ],
        ]);

        $this->actingAs($editor)
            ->post(route('admin.pages.revisions.restore', [$page, $revision]))
            ->assertRedirect(route('admin.pages.edit', $page));
    }

    private function userWithRole(string $role): User
    {
        /** @var Role $existingRole */
        $existingRole = Role::findByName($role, 'web');

        $user = User::factory()->create();
        $user->assignRole($existingRole);

        return $user;
    }
}
