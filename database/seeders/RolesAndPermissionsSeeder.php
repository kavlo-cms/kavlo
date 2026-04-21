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
            'restore page revisions',

            // Media
            'view media',
            'upload media',
            'delete media',

            // Menus
            'view menus',
            'manage menus',

            // Forms
            'view forms',
            'manage forms',

            // Email templates
            'view email templates',
            'manage email templates',

            // Redirects
            'view redirects',
            'manage redirects',

            // Settings
            'view settings',
            'manage settings',
            'view scripts',
            'manage scripts',
            'view themes',
            'manage themes',
            'view plugins',
            'manage plugins',

            // Users
            'view users',
            'manage users',

            // DataHub
            'view datahub',
            'preview datahub',

            // System
            'view analytics',
            'view activity log',
            'manage backups',
            'manage cache',
            'manage maintenance',
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

        // User — frontend/customer/forum account with no admin access
        $user = Role::firstOrCreate(['name' => 'user']);
        $user->syncPermissions([]);

        // Editor — everything content-related + publish
        $editor = Role::firstOrCreate(['name' => 'editor']);
        $editor->syncPermissions([
            'view pages', 'create pages', 'edit pages', 'delete pages', 'publish pages', 'restore page revisions',
            'view media', 'upload media', 'delete media',
            'view menus', 'manage menus',
            'view forms', 'manage forms',
            'view email templates', 'manage email templates',
            'view redirects', 'manage redirects',
            'view datahub', 'preview datahub',
        ]);

        // Admin — everything except managing other users' roles
        $admin = Role::firstOrCreate(['name' => 'admin']);
        $admin->syncPermissions(Permission::whereNotIn('name', ['manage users'])->pluck('name'));

        // Super Admin — assigned via gate (bypasses all checks), but also gets all permissions
        $superAdmin = Role::firstOrCreate(['name' => 'super-admin']);
        $superAdmin->syncPermissions(Permission::all());
    }
}
