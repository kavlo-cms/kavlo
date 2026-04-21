<?php

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class FormBuilderSchemaFeatureTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);
    }

    public function test_admin_cannot_create_form_with_invalid_nested_blocks(): void
    {
        $user = $this->adminUser();

        $this->actingAs($user)->post('/admin/forms', [
            'name' => 'Contact',
            'slug' => 'contact',
            'description' => 'Lead capture form.',
            'submission_action' => 'core.store-submission',
            'action_config' => [],
            'blocks' => [
                [
                    'id' => 'columns-1',
                    'type' => 'columns',
                    'data' => [
                        'count' => '2',
                        'col_0' => [
                            [
                                'type' => 'input',
                                'data' => [
                                    'key' => 'email',
                                    'label' => 'Email',
                                    'input_type' => 'email',
                                ],
                            ],
                        ],
                    ],
                    'order' => 0,
                ],
            ],
        ])->assertSessionHasErrors('blocks');
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
