<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class RolePermissionController extends Controller
{
    public function index(): View
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
}
