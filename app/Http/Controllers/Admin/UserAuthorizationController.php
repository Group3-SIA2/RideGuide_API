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
    public function index()
    {
        $this->authorizePermissions(request(), 'manage_authorization');

        $roles = Role::with('permissions')->orderBy('name')->get();
        $permissions = Permission::orderBy('group')->orderBy('display_name')->get();
        $permissionGroups = $permissions->groupBy('group');

        return view('admin.user-management.index', compact('roles', 'permissions', 'permissionGroups'));
    }

    /**
     * Show the permission editor for a specific role (checkboxes).
     */
    public function editRole(Role $role)
    {
        $this->authorizePermissions(request(), 'manage_authorization');

        $role->load('permissions');
        $permissions = Permission::orderBy('group')->orderBy('display_name')->get();
        $permissionGroups = $permissions->groupBy('group');
        $rolePermissionIds = $role->permissions->pluck('id')->toArray();

        return view('admin.user-management.edit-role', compact('role', 'permissions', 'permissionGroups', 'rolePermissionIds'));
    }

    /**
     * Update the permissions for a specific role.
     */
    public function updateRole(Request $request, Role $role)
    {
        $this->authorizePermissions($request, 'manage_authorization');

        // Super admin cannot have permissions changed
        if ($role->name === Role::SUPER_ADMIN) {
            return redirect()->route('admin.user-management.index')
                ->with('error', 'Super Admin permissions cannot be modified.');
        }

        $validated = $request->validate([
            'permissions'   => ['nullable', 'array'],
            'permissions.*' => ['uuid', 'exists:permissions,id'],
        ]);

        $role->permissions()->sync($validated['permissions'] ?? []);

        return redirect()->route('admin.user-management.index')
            ->with('success', "Permissions for \"{$role->name}\" updated successfully.");
    }

    /**
     * Show per-user permission override page – manage individual user's role.
     */
    public function editUser(User $user)
    {
        $this->authorizePermissions(request(), 'manage_authorization');

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

        return view('admin.user-management.edit-user', compact('user', 'roles', 'permissions', 'permissionGroups', 'userPermissionIds'));
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

        return redirect()->route('admin.user-management.index')
            ->with('success', "Roles for \"{$user->first_name} {$user->last_name}\" updated successfully.");
    }

    /**
     * Authorization helper copied from CheckPermission middleware.
     *
     * Usage inside controller methods:
     *     $this->authorizePermissions($request, 'manage_users');
     *     $this->authorizePermissions($request, 'manage_users', 'manage_drivers'); // any of
     *
     * This will abort(403) if the current user is not authenticated or lacks the permission(s).
     * Super admins bypass all checks.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  string  ...$permissions
     * @return void
     */
    protected function authorizePermissions(Request $request, string ...$permissions): void
    {
        $user = $request->user();

        if (!$user) {
            abort(403, 'Unauthorized.');
        }

        // Super admins bypass all permission checks
        if ($user->hasRole(Role::SUPER_ADMIN)) {
            return;
        }

        // Check if the user has any of the required permissions
        if (!$user->hasAnyPermission($permissions)) {
            abort(403, 'You do not have permission to perform this action.');
        }
    }
}
