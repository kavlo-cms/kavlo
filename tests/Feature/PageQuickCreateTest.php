<?php

namespace Tests\Feature;

use App\Models\Page;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class PageQuickCreateTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);
    }

    public function test_quick_create_makes_draft_and_redirects_to_editor(): void
    {
        $user = $this->adminUser();

        Page::create([
            'title' => 'Untitled page',
            'slug' => 'untitled-page',
            'type' => 'page',
            'order' => 0,
        ]);

        $deleted = Page::create([
            'title' => 'Untitled page 2',
            'slug' => 'untitled-page-1',
            'type' => 'page',
            'order' => 1,
        ]);
        $deleted->delete();

        $response = $this->actingAs($user)
            ->post(route('admin.pages.quick-create'));

        $page = Page::latest('id')->first();

        $response->assertRedirect(route('admin.pages.edit', $page));
        $this->assertNotNull($page);
        $this->assertSame('Untitled page', $page->title);
        $this->assertSame('untitled-page-2', $page->slug);
        $this->assertSame('page', $page->type);
        $this->assertSame('builder', $page->editor_mode);
        $this->assertFalse($page->is_published);
        $this->assertFalse($page->is_homepage);
        $this->assertSame([], $page->blocks);
        $this->assertSame([], $page->metadata);
        $this->assertSame($user->id, $page->author_id);
        $this->assertSame(1, $page->order);
    }

    private function adminUser(): User
    {
        /** @var Role $role */
        $role = Role::findByName('admin', 'web');

        $user = User::factory()->create();
        $user->assignRole($role);

        return $user;
    }
}
