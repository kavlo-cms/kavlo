<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class RolesAndPermissionsSeeder extends Seeder
{
    public function run(): void
    {
        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        $permissions = [
            // Pages
            'view pages',
            'create pages',
            'edit pages',
            'delete pages',
            'publish pages',

            // Media
            'view media',
            'upload media',
            'delete media',

            // Menus
            'view menus',
            'manage menus',

            // Settings
            'view settings',
            'manage settings',

            // Users
            'view users',
            'manage users',

            // Plugins & Themes
            'manage plugins',
            'manage themes',
        ];

        foreach ($permissions as $permission) {
            Permission::firstOrCreate(['name' => $permission]);
        }

        // Author — write own content, use media
        $author = Role::firstOrCreate(['name' => 'author']);
        $author->syncPermissions([
            'view pages', 'create pages', 'edit pages',
            'view media', 'upload media',
        ]);

        // Editor — everything content-related + publish
        $editor = Role::firstOrCreate(['name' => 'editor']);
        $editor->syncPermissions([
            'view pages', 'create pages', 'edit pages', 'delete pages', 'publish pages',
            'view media', 'upload media', 'delete media',
            'view menus', 'manage menus',
        ]);

        // Admin — everything except managing other users' roles
        $admin = Role::firstOrCreate(['name' => 'admin']);
        $admin->syncPermissions(Permission::whereNotIn('name', ['manage users'])->pluck('name'));

        // Super Admin — assigned via gate (bypasses all checks), but also gets all permissions
        $superAdmin = Role::firstOrCreate(['name' => 'super-admin']);
        $superAdmin->syncPermissions(Permission::all());
    }
}
