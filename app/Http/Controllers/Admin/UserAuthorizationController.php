<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

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

        // prevent admin and super admin removing thier role's permissions to avoid locking themselves out
        if (in_array($role->name, [Role::SUPER_ADMIN, Role::ADMIN], true) && empty($request->input('permissions'))) {
            return redirect()->route('admin.user-authorization.edit-role', $role)
                ->with('error', 'You cannot remove all permissions from this role. At least one permission must be assigned to prevent locking yourself out.');
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

        $currentUser = $request->user();

        if (!$this->canManageUserRoles($currentUser, $user)) {
            return redirect()->route('admin.user-authorization.index')
                ->with('error', 'You are not allowed to manage roles for this user.');
        }

        $user->load('roles.permissions');
        $assignableRoleNames = $this->assignableRoleNamesFor($currentUser, $user);
        $displayRoleNames = $assignableRoleNames;

        $isAdminEditingSelf = $currentUser->hasRole(Role::ADMIN)
            && !$currentUser->hasRole(Role::SUPER_ADMIN)
            && $currentUser->is($user);

        if ($isAdminEditingSelf && !in_array(Role::ADMIN, $displayRoleNames, true)) {
            $displayRoleNames[] = Role::ADMIN;
        }

        $roles = Role::whereIn('name', $displayRoleNames)->orderBy('name')->get();
        $lockedRoleIds = $roles
            ->filter(fn (Role $role) => $isAdminEditingSelf && $role->name === Role::ADMIN)
            ->pluck('id')
            ->values()
            ->all();
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

        return view('admin.user-authorization.edit-user', compact('user', 'roles', 'permissions', 'permissionGroups', 'userPermissionIds', 'lockedRoleIds'));
    }

    /**
     * Update a user's role assignment.
     */
    public function updateUser(Request $request, User $user)
    {
        $this->authorizePermissions($request, 'manage_authorization');

        $currentUser = $request->user();

        if (!$this->canManageUserRoles($currentUser, $user)) {
            return redirect()->route('admin.user-authorization.index')
                ->with('error', 'You are not allowed to manage roles for this user.');
        }

        $assignableRoleIds = Role::whereIn('name', $this->assignableRoleNamesFor($currentUser, $user))
            ->pluck('id')
            ->all();

        $validated = $request->validate([
            'roles'   => ['nullable', 'array'],
            'roles.*' => ['uuid', Rule::in($assignableRoleIds)],
        ]);
        $selectedRoleIds = $validated['roles'] ?? [];

        $isAdminEditingSelf = $currentUser->hasRole(Role::ADMIN)
            && !$currentUser->hasRole(Role::SUPER_ADMIN)
            && $currentUser->is($user);

        if ($isAdminEditingSelf) {
            $adminRoleId = Role::getIdbyName(Role::ADMIN);

            if ($adminRoleId && !in_array($adminRoleId, $selectedRoleIds, true)) {
                $selectedRoleIds[] = $adminRoleId;
            }
        }

        $user->roles()->sync($selectedRoleIds);
        
        return redirect()->route('admin.user-authorization.index')
            ->with('success', "Roles for \"{$user->first_name} {$user->last_name}\" updated successfully.");
    }

    private function canManageUserRoles(User $currentUser, User $targetUser): bool
    {
        if ($targetUser->hasRole(Role::SUPER_ADMIN)) {
            return false;
        }

        if ($currentUser->hasRole(Role::SUPER_ADMIN)) {
            return true;
        }

        if ($currentUser->hasRole(Role::ADMIN)) {
            if ($currentUser->is($targetUser)) {
                return true;
            }

            if ($targetUser->hasRole(Role::ADMIN)) {
                return false;
            }

            return $targetUser->roles()
                ->whereIn('name', [Role::COMMUTER, Role::DRIVER, Role::ORGANIZATION])
                ->exists();
        }

        return false;
    }

    private function assignableRoleNamesFor(User $currentUser, ?User $targetUser = null): array
    {
        if ($currentUser->hasRole(Role::SUPER_ADMIN)) {
            return [
                Role::SUPER_ADMIN,
                Role::ADMIN,
                Role::DRIVER,
                Role::COMMUTER,
                Role::ORGANIZATION,
            ];
        }

        if ($currentUser->hasRole(Role::ADMIN)) {
            return [
                Role::DRIVER,
                Role::COMMUTER,
                Role::ORGANIZATION,
            ];
        }

        return [];
    }
}
