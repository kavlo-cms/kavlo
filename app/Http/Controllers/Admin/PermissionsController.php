<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;

class PermissionsController extends Controller
{
    public function store(Request $request): RedirectResponse
    {
        $this->authorize('manage settings');

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:128', 'unique:permissions,name'],
        ]);

        Permission::create(['name' => $validated['name']]);
        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        return back()->with('success', "Permission \"{$validated['name']}\" created.");
    }

    public function destroy(Permission $permission): RedirectResponse
    {
        $this->authorize('manage settings');

        $permission->delete();
        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        return back()->with('success', "Permission \"{$permission->name}\" deleted.");
    }
}
