<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Illuminate\Http\Request;

class UserAuthorizationController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth');
    }

    /**
     * Show the authorization management page – list roles with their permissions.
     */
    public function index(Request $request)
    {
        $this->authorizePermissions($request, 'manage_authorization');

        $currentUser = $request->user();
        $roles = Role::with('permissions')->orderBy('name')->get();
        $permissions = Permission::orderBy('group')->orderBy('display_name')->get();
        $permissionGroups = $permissions->groupBy('group');
        $editableRoleNames = $roles
            ->filter(fn (Role $role) => $this->canEditRolePermissions($currentUser, $role))
            ->pluck('name')
            ->values()
            ->all();

        return view('admin.user-authorization.index', compact('roles', 'permissions', 'permissionGroups', 'editableRoleNames'));
    }

    /**
     * Show the permission editor for a specific role (checkboxes).
     */
    public function editRole(Request $request, Role $role)
    {
        $this->authorizePermissions($request, 'manage_authorization');

        if (!$this->canEditRolePermissions($request->user(), $role)) {
            return redirect()->route('admin.user-authorization.index')
                ->with('error', 'You are not allowed to edit permissions for this role.');
        }

        $role->load('permissions');
        $permissions = Permission::orderBy('group')->orderBy('display_name')->get();
        $permissionGroups = $permissions->groupBy('group');
        $rolePermissionIds = $role->permissions->pluck('id')->toArray();

        return view('admin.user-authorization.edit-role', compact('role', 'permissions', 'permissionGroups', 'rolePermissionIds'));
    }

    /**
     * Update the permissions for a specific role.
     */
    public function updateRole(Request $request, Role $role)
    {
        $this->authorizePermissions($request, 'manage_authorization');

        if (!$this->canEditRolePermissions($request->user(), $role)) {
            return redirect()->route('admin.user-authorization.index')
                ->with('error', 'You are not allowed to edit permissions for this role.');
        }

        $validated = $request->validate([
            'permissions'   => ['nullable', 'array'],
            'permissions.*' => ['uuid', 'exists:permissions,id'],
        ]);

        $role->permissions()->sync($validated['permissions'] ?? []);

        return redirect()->route('admin.user-authorization.index')
            ->with('success', "Permissions for \"{$role->name}\" updated successfully.");
    }

    private function canEditRolePermissions(User $currentUser, Role $targetRole): bool
    {
        if ($targetRole->name === Role::SUPER_ADMIN) {
            return false;
        }

        if ($currentUser->hasRole(Role::SUPER_ADMIN)) {
            return true;
        }

        if ($currentUser->hasRole(Role::ADMIN)) {
            return in_array($targetRole->name, [
                Role::DRIVER,
                Role::COMMUTER,
                Role::ORGANIZATION,
            ], true);
        }

        return false;
    }

    /**
     * Show per-user permission override page – manage individual user's role.
     */
    public function editUser(Request $request, User $user)
    {
        $this->authorizePermissions($request, 'manage_authorization');

        $user->load('roles.permissions');
        $roles = Role::orderBy('name')->get();
        $permissions = Permission::orderBy('group')->orderBy('display_name')->get();
        $permissionGroups = $permissions->groupBy('group');

        // Collect all permissions the user currently has through their roles
        $userPermissionIds = [];
        foreach ($user->roles as $role) {
            foreach ($role->permissions as $permission) {
                $userPermissionIds[] = $permission->id;
            }
        }
        $userPermissionIds = array_unique($userPermissionIds);

        return view('admin.user-authorization.edit-user', compact('user', 'roles', 'permissions', 'permissionGroups', 'userPermissionIds'));
    }

    /**
     * Update a user's role assignment.
     */
    public function updateUser(Request $request, User $user)
    {
        $this->authorizePermissions($request, 'manage_authorization');

        $validated = $request->validate([
            'roles'   => ['nullable', 'array'],
            'roles.*' => ['uuid', 'exists:roles,id'],
            'status' => ['required', 'in:active,inactive,suspended'],
            'status_reason' => ['nullable', 'string', 'max:255'],
        ]);

        $user->roles()->sync($validated['roles'] ?? []);

        $statusChanged = $user->status !== $validated['status'];

        $user->update([
            'status' => $validated['status'],
            'status_reason' => $validated['status_reason'] ?? null,
            'status_changed_at' => $statusChanged ? now() : $user->status_changed_at,
        ]);

        return redirect()->route('admin.user-authorization.index')
            ->with('success', "Roles for \"{$user->first_name} {$user->last_name}\" updated successfully.");
    }
}
