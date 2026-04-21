<?php

use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

return new class extends Migration
{
    public function up(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        foreach (['view datahub', 'preview datahub'] as $permissionName) {
            Permission::findOrCreate($permissionName, 'web');
        }

        if ($editor = Role::where('name', 'editor')->where('guard_name', 'web')->first()) {
            $editor->givePermissionTo(['view datahub', 'preview datahub']);
        }

        if ($admin = Role::where('name', 'admin')->where('guard_name', 'web')->first()) {
            $admin->givePermissionTo(['view datahub', 'preview datahub']);
        }

        if ($superAdmin = Role::where('name', 'super-admin')->where('guard_name', 'web')->first()) {
            $superAdmin->givePermissionTo(['view datahub', 'preview datahub']);
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        foreach (['editor', 'admin', 'super-admin'] as $roleName) {
            if ($role = Role::where('name', $roleName)->where('guard_name', 'web')->first()) {
                $role->revokePermissionTo(['view datahub', 'preview datahub']);
            }
        }

        Permission::whereIn('name', ['view datahub', 'preview datahub'])
            ->where('guard_name', 'web')
            ->delete();

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
};
