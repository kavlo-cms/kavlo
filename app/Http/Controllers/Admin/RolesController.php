<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class RolesController extends Controller
{
    public function index(): Response
    {
        $this->authorize('manage settings');

        $roles = Role::with('permissions')
            ->orderBy('name')
            ->get()
            ->map(fn(Role $role) => [
                'id'          => $role->id,
                'name'        => $role->name,
                'permissions' => $role->permissions->pluck('name')->sort()->values(),
                'users_count' => $role->users()->count(),
            ]);

        $permissions = Permission::orderBy('name')->pluck('name');

        return Inertia::render('Roles/Index', [
            'roles'       => $roles,
            'permissions' => $permissions,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $this->authorize('manage settings');

        $validated = $request->validate([
            'name'          => ['required', 'string', 'max:64', 'unique:roles,name'],
            'permissions'   => ['array'],
            'permissions.*' => ['string', 'exists:permissions,name'],
        ]);

        $role = Role::create(['name' => $validated['name']]);
        $role->syncPermissions($validated['permissions'] ?? []);

        return back()->with('success', "Role \"{$role->name}\" created.");
    }

    public function update(Request $request, Role $role): RedirectResponse
    {
        $this->authorize('manage settings');

        if ($role->name === 'super-admin') {
            return back()->with('error', 'The super-admin role cannot be modified.');
        }

        $validated = $request->validate([
            'permissions'   => ['array'],
            'permissions.*' => ['string', 'exists:permissions,name'],
        ]);

        $role->syncPermissions($validated['permissions'] ?? []);

        return back()->with('success', "Permissions updated for \"{$role->name}\".");
    }

    public function destroy(Role $role): RedirectResponse
    {
        $this->authorize('manage settings');

        if (in_array($role->name, ['super-admin', 'admin', 'editor', 'author'])) {
            return back()->with('error', 'Built-in roles cannot be deleted.');
        }

        $role->delete();

        return back()->with('success', "Role \"{$role->name}\" deleted.");
    }
}
