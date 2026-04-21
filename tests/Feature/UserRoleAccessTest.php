<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class UserRoleAccessTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_role_cannot_access_admin_interface(): void
    {
        Role::findOrCreate('user', 'web');

        $user = User::factory()->create();
        $user->assignRole('user');

        $this->actingAs($user)
            ->get('/admin/account')
            ->assertForbidden();
    }

    public function test_unverified_admin_role_can_access_admin_interface(): void
    {
        Role::findOrCreate('admin', 'web');

        $user = User::factory()->unverified()->create();
        $user->assignRole('admin');

        $this->actingAs($user)
            ->get('/admin')
            ->assertOk();
    }
}
