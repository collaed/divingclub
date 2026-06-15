<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class RolePermissionController extends Controller
{
    public function index(): RedirectResponse|View
    {
        $roles = Role::with('permissions')->orderBy('name')->get();
        $permissions = Permission::orderBy('name')->get();

        return view('admin.roles.index', compact('roles', 'permissions'));
    }

    public function update(Request $request): RedirectResponse
    {
        $request->validate([
            'roles' => 'required|array',
            'roles.*' => 'array',
        ]);

        foreach ($request->input('roles', []) as $roleId => $permissionIds) {
            $role = Role::findById((int) $roleId);
            $role->syncPermissions(array_map('intval', $permissionIds));
        }

        // Handle roles with no permissions checked (empty array not sent by form)
        $submittedRoleIds = array_keys($request->input('roles', []));
        Role::whereNotIn('id', $submittedRoleIds)->each(fn ($r) => $r->syncPermissions([]));

        app()->make(PermissionRegistrar::class)->forgetCachedPermissions();

        return back()->with('success', __('Permissions updated.'));
    }

    public function members(Role $role): View
    {
        $role->load('users.detail');
        $availableUsers = User::whereDoesntHave('roles', fn ($q) => $q->where('id', $role->id))
            ->with('detail')
            ->orderBy('username')
            ->get();

        return view('admin.roles.members', compact('role', 'availableUsers'));
    }

    public function addMember(Request $request, Role $role): RedirectResponse
    {
        $request->validate(['user_id' => 'required|integer|exists:users,id']);
        $user = User::findOrFail($request->user_id);
        $user->assignRole($role);

        return back()->with('success', __(':name added to :role.', ['name' => $user->detail?->first_name.' '.$user->detail?->last_name, 'role' => $role->name]));
    }

    public function removeMember(Role $role, User $user): RedirectResponse
    {
        $user->removeRole($role);

        return back()->with('success', __(':name removed from :role.', ['name' => $user->detail?->first_name.' '.$user->detail?->last_name, 'role' => $role->name]));
    }
}
