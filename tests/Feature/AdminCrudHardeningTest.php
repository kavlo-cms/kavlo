<?php

namespace Tests\Feature;

use App\Models\EmailTemplate;
use App\Models\Form;
use App\Models\FormSubmission;
use App\Models\Library;
use App\Models\Menu;
use App\Models\Page;
use App\Models\Redirect;
use App\Models\User;
use App\Services\ContentRouteRegistry;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class AdminCrudHardeningTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);

        Config::set('cms.storage.public_disk', 'uploads');
        Config::set('media-library.disk_name', 'uploads');
        Config::set('filesystems.disks.uploads', [
            'driver' => 'local',
            'root' => storage_path('framework/testing/disks/uploads'),
            'url' => 'https://cdn.example.com/assets',
            'visibility' => 'public',
            'throw' => false,
        ]);
    }

    public function test_admin_can_create_update_toggle_and_delete_redirects(): void
    {
        $admin = $this->userWithRole('admin');

        $this->actingAs($admin)
            ->post(route('admin.redirects.store'), [
                'from_url' => 'Old-Path/?query=1',
                'to_url' => '/new-path',
                'type' => '301',
                'is_active' => true,
            ])
            ->assertRedirect();

        $redirect = Redirect::query()->firstOrFail();

        $this->assertSame('/old-path', $redirect->from_url);
        $this->assertSame('/new-path', Redirect::findForPath('/OLD-PATH')?->to_url);

        $this->actingAs($admin)
            ->put(route('admin.redirects.update', $redirect), [
                'from_url' => '/moved-path',
                'to_url' => '/destination',
                'type' => '302',
                'is_active' => true,
            ])
            ->assertRedirect();

        $this->assertNull(Redirect::findForPath('/old-path'));
        $this->assertSame('/destination', Redirect::findForPath('/moved-path')?->to_url);

        $this->actingAs($admin)
            ->patch(route('admin.redirects.toggle', $redirect))
            ->assertRedirect();

        $this->assertFalse($redirect->fresh()->is_active);
        $this->assertNull(Redirect::findForPath('/moved-path'));

        $this->actingAs($admin)
            ->delete(route('admin.redirects.destroy', $redirect))
            ->assertRedirect();

        $this->assertDatabaseMissing('redirects', [
            'id' => $redirect->id,
        ]);
    }

    public function test_author_cannot_manage_redirects(): void
    {
        $this->actingAs($this->userWithRole('author'))
            ->post(route('admin.redirects.store'), [
                'from_url' => '/blocked',
                'to_url' => '/target',
                'type' => '301',
                'is_active' => true,
            ])
            ->assertForbidden();
    }

    public function test_admin_can_update_menu_tree_and_route_registry_refreshes(): void
    {
        $admin = $this->userWithRole('admin');
        $page = Page::create([
            'title' => 'About',
            'slug' => 'about',
            'type' => 'page',
            'is_published' => true,
            'blocks' => [],
            'metadata' => [],
        ]);

        $this->actingAs($admin)
            ->post(route('admin.menus.store'), [
                'name' => 'Main Navigation',
                'slug' => 'main',
            ])
            ->assertRedirect();

        $menu = Menu::query()->firstOrFail();
        $routes = app(ContentRouteRegistry::class);

        $this->assertSame([], $routes->menuPayload($routes->resolveMenu('main'))['items']);

        $this->actingAs($admin)
            ->put(route('admin.menus.update', $menu), [
                'name' => 'Main Navigation',
                'slug' => 'main',
                'items' => [
                    [
                        'label' => 'About',
                        'page_id' => $page->id,
                        'target' => '_self',
                        'children' => [
                            [
                                'label' => 'External Docs',
                                'url' => 'https://example.com/docs',
                                'target' => '_blank',
                                'children' => [],
                            ],
                        ],
                    ],
                ],
            ])
            ->assertRedirect();

        $menu->refresh();
        $payload = $routes->menuPayload($routes->resolveMenu('main'));

        $this->assertSame('About', $payload['items'][0]['label']);
        $this->assertSame($page->id, $menu->items()->first()?->page_id);
        $this->assertCount(1, $payload['items'][0]['children']);
        $this->assertSame('External Docs', $payload['items'][0]['children'][0]['label']);
    }

    public function test_author_cannot_manage_menus(): void
    {
        $this->actingAs($this->userWithRole('author'))
            ->post(route('admin.menus.store'), [
                'name' => 'Main Navigation',
                'slug' => 'main',
            ])
            ->assertForbidden();
    }

    public function test_admin_can_delete_form_submission_and_export_csv(): void
    {
        $admin = $this->userWithRole('admin');
        $form = Form::create([
            'name' => 'Contact',
            'slug' => 'contact',
            'submission_action' => 'core.store-submission',
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
            ],
        ]);
        $submission = FormSubmission::create([
            'form_id' => $form->id,
            'data' => ['email' => 'jane@example.com'],
        ]);

        $this->actingAs($admin)
            ->get(route('admin.forms.submissions.export', $form))
            ->assertOk()
            ->assertHeader('Content-Type', 'text/csv; charset=UTF-8');

        $this->actingAs($admin)
            ->delete(route('admin.forms.submissions.destroy', [$form, $submission]))
            ->assertRedirect();

        $this->assertDatabaseMissing('form_submissions', [
            'id' => $submission->id,
        ]);
    }

    public function test_admin_can_update_and_delete_email_template(): void
    {
        $admin = $this->userWithRole('admin');
        $template = EmailTemplate::create([
            'name' => 'Welcome',
            'slug' => 'welcome',
            'description' => null,
            'context_key' => 'core.test-email',
            'subject' => 'Welcome',
            'blocks' => [
                [
                    'id' => 'text-1',
                    'type' => 'text',
                    'data' => ['content' => 'Original body'],
                    'order' => 0,
                ],
            ],
        ]);

        $this->actingAs($admin)
            ->put(route('admin.email-templates.update', $template), [
                'name' => 'Welcome updated',
                'slug' => 'welcome',
                'description' => 'Updated template',
                'context_key' => 'core.test-email',
                'subject' => 'Updated subject',
                'blocks' => [
                    [
                        'id' => 'text-1',
                        'type' => 'text',
                        'data' => ['content' => 'Updated body'],
                        'order' => 0,
                    ],
                ],
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('email_templates', [
            'id' => $template->id,
            'name' => 'Welcome updated',
            'subject' => 'Updated subject',
        ]);

        $this->actingAs($admin)
            ->delete(route('admin.email-templates.destroy', $template))
            ->assertRedirect(route('admin.email-templates.index'));

        $this->assertDatabaseMissing('email_templates', [
            'id' => $template->id,
        ]);
    }

    public function test_admin_can_force_delete_used_media_and_update_metadata(): void
    {
        Storage::fake('uploads');
        Config::set('filesystems.disks.uploads.url', 'https://cdn.example.com/assets');

        $admin = $this->userWithRole('admin');
        $library = Library::singleton();
        $media = $library
            ->addMedia(UploadedFile::fake()->image('hero.png'))
            ->toMediaCollection('uploads', 'uploads');

        Page::create([
            'title' => 'Landing',
            'slug' => 'landing',
            'type' => 'page',
            'is_published' => true,
            'blocks' => [[
                'id' => 'image',
                'type' => 'image',
                'data' => ['src' => $media->getUrl()],
            ]],
        ]);

        $this->actingAs($admin)
            ->patchJson(route('admin.media.update', $media), [
                'name' => 'Hero image',
                'alt' => 'Accessible alt text',
                'folder' => 'graphics',
            ])
            ->assertOk()
            ->assertJsonPath('name', 'Hero image')
            ->assertJsonPath('alt', 'Accessible alt text')
            ->assertJsonPath('folder', 'graphics');

        $this->actingAs($admin)
            ->deleteJson(route('admin.media.destroy', $media), [
                'force' => true,
            ])
            ->assertOk()
            ->assertJsonPath('deleted', true);

        $this->assertDatabaseMissing('media', [
            'id' => $media->id,
        ]);
    }

    private function userWithRole(string $role): User
    {
        $user = User::factory()->create();
        $user->assignRole(Role::findByName($role, 'web'));

        return $user;
    }
}
