<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Auth\Events\Registered;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Inertia\Inertia;
use Inertia\Response;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class UsersController extends Controller
{
    public function index(): Response
    {
        $this->authorize('view users');

        $users = User::with('roles', 'permissions')
            ->orderBy('name')
            ->get()
            ->map(fn(User $user) => [
                'id'                 => $user->id,
                'name'               => $user->name,
                'email'              => $user->email,
                'roles'              => $user->roles->pluck('name'),
                'direct_permissions' => $user->getDirectPermissions()->pluck('name')->sort()->values(),
                'created_at'         => $user->created_at,
            ]);

        $allRoles = Role::with('permissions')
            ->orderBy('name')
            ->get()
            ->map(fn(Role $role) => [
                'id'          => $role->id,
                'name'        => $role->name,
                'permissions' => $role->permissions->pluck('name')->sort()->values(),
                'users_count' => $role->users()->count(),
            ]);

        $permissions = Permission::orderBy('name')->get()->map(fn(Permission $p) => [
            'id'   => $p->id,
            'name' => $p->name,
        ]);

        $roleNames = $allRoles->pluck('name');

        return Inertia::render('Users/Index', [
            'users'       => $users,
            'roles'       => $roleNames,
            'allRoles'    => $allRoles,
            'permissions' => $permissions,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $this->authorize('manage users');

        $validated = $request->validate([
            'name'     => ['required', 'string', 'max:255'],
            'email'    => ['required', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
            'roles'    => ['array'],
            'roles.*'  => ['string', 'exists:roles,name'],
        ]);

        $user = User::create([
            'name'     => $validated['name'],
            'email'    => $validated['email'],
            'password' => Hash::make($validated['password']),
        ]);

        if (!empty($validated['roles'])) {
            $user->assignRole($validated['roles']);
        }

        event(new Registered($user));

        return back()->with('success', "{$user->name} has been created.");
    }

    public function updateRoles(Request $request, User $user): RedirectResponse
    {
        $this->authorize('manage users');

        $validated = $request->validate([
            'roles'   => ['array'],
            'roles.*' => ['string', 'exists:roles,name'],
        ]);

        $user->syncRoles($validated['roles'] ?? []);

        return back()->with('success', "Roles updated for {$user->name}.");
    }

    public function updatePermissions(Request $request, User $user): RedirectResponse
    {
        $this->authorize('manage users');

        $validated = $request->validate([
            'permissions'   => ['array'],
            'permissions.*' => ['string', 'exists:permissions,name'],
        ]);

        $user->syncPermissions($validated['permissions'] ?? []);

        return back()->with('success', "Direct permissions updated for {$user->name}.");
    }

    public function destroy(User $user): RedirectResponse
    {
        $this->authorize('manage users');

        if ($user->id === auth()->id()) {
            return back()->with('error', 'You cannot delete your own account.');
        }

        $user->delete();

        return back()->with('success', "{$user->name} has been deleted.");
    }
}
