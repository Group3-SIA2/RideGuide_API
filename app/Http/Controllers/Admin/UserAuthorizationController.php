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
            return redirect()->route($this->panelRouteName($request, 'user-authorization.index'))
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
            return redirect()->route($this->panelRouteName($request, 'user-authorization.index'))
                ->with('error', 'You are not allowed to edit permissions for this role.');
        }

        // prevent admin and super admin removing thier role's permissions to avoid locking themselves out
        if (in_array($role->name, [Role::SUPER_ADMIN, Role::ADMIN], true) && empty($request->input('permissions'))) {
            return redirect()->route($this->panelRouteName($request, 'user-authorization.edit-role'), $role)
                ->with('error', 'You cannot remove all permissions from this role. At least one permission must be assigned to prevent locking yourself out.');
        }

        $validated = $request->validate([
            'permissions'   => ['nullable', 'array'],
            'permissions.*' => ['uuid', 'exists:permissions,id'],
        ]);

        $role->permissions()->sync($validated['permissions'] ?? []);

        return redirect()->route($this->panelRouteName($request, 'user-authorization.index'))
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
            return redirect()->route($this->panelRouteName($request, 'user-authorization.index'))
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
        $disabledRoleNames = $this->disabledRoleNamesForEdit($request->user(), $user);
        $lockedRoleIds = $roles
            ->filter(fn (Role $role) => $isAdminEditingSelf && $role->name === Role::ADMIN)
            ->pluck('id')
            ->values()
            ->all();
        $disabledRoleIds = $roles
            ->filter(fn (Role $role) => in_array($role->name, $disabledRoleNames, true))
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

        return view('admin.user-authorization.edit-user', compact('user', 'roles', 'permissions', 'permissionGroups', 'userPermissionIds', 'lockedRoleIds', 'disabledRoleIds'));
    }

    /**
     * Update a user's role assignment.
     */
    public function updateUser(Request $request, User $user)
    {
        $this->authorizePermissions($request, 'manage_authorization');

        $currentUser = $request->user();

        if (!$this->canManageUserRoles($currentUser, $user)) {
            return redirect()->route($this->panelRouteName($request, 'user-authorization.index'))
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

        $selectedRoleNames = Role::query()
            ->whereIn('id', $selectedRoleIds)
            ->pluck('name')
            ->all();

        $disabledRoleNames = $this->disabledRoleNamesForEdit($currentUser, $user);
        $blockedSelections = array_values(array_intersect($selectedRoleNames, $disabledRoleNames));

        if (!empty($blockedSelections)) {
            $label = collect($blockedSelections)
                ->map(fn (string $roleName) => ucwords(str_replace('_', ' ', $roleName)))
                ->implode(', ');

            return redirect()->route($this->panelRouteName($request, 'user-authorization.edit-user'), $user)
                ->withInput()
                ->with('error', "These roles are not allowed for this user context: {$label}.");
        }

        if ($roleCombinationError = $this->roleCombinationError($selectedRoleIds)) {
            return redirect()->route($this->panelRouteName($request, 'user-authorization.edit-user'), $user)
                ->withInput()
                ->with('error', $roleCombinationError);
        }

        $user->roles()->sync($selectedRoleIds);
        
        return redirect()->route($this->panelRouteName($request, 'user-authorization.index'))
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

    private function roleCombinationError(array $selectedRoleIds): ?string
    {
        $selectedRoleNames = Role::query()
            ->whereIn('id', $selectedRoleIds)
            ->pluck('name')
            ->all();

        if (in_array(Role::SUPER_ADMIN, $selectedRoleNames, true) && count($selectedRoleNames) > 1) {
            return 'Super Admin cannot be combined with other roles.';
        }

        if (in_array(Role::ADMIN, $selectedRoleNames, true)) {
            $adminConflicts = array_values(array_intersect($selectedRoleNames, [
                Role::DRIVER,
                Role::COMMUTER,
                Role::ORGANIZATION,
            ]));

            if (!empty($adminConflicts)) {
                $label = collect($adminConflicts)
                    ->map(fn (string $roleName) => ucwords(str_replace('_', ' ', $roleName)))
                    ->implode(', ');

                return "Admin cannot be combined with: {$label}.";
            }
        }

        return null;
    }

    private function disabledRoleNamesForEdit(User $currentUser, User $targetUser): array
    {
        $disabled = [];

        // Admin-profile users should only stay within admin track.
        if ($targetUser->hasRole(Role::ADMIN)) {
            $disabled = array_merge($disabled, [
                Role::DRIVER,
                Role::COMMUTER,
                Role::ORGANIZATION,
            ]);
        }

        // Organization-profile users can combine with driver/commuter but not admin tracks.
        if ($targetUser->hasRole(Role::ORGANIZATION)) {
            $disabled = array_merge($disabled, [
                Role::SUPER_ADMIN,
                Role::ADMIN,
            ]);
        }

        // When a plain admin edits self, keep admin role isolated.
        if ($currentUser->hasRole(Role::ADMIN)
            && !$currentUser->hasRole(Role::SUPER_ADMIN)
            && $currentUser->is($targetUser)
        ) {
            $disabled = array_merge($disabled, [
                Role::DRIVER,
                Role::COMMUTER,
                Role::ORGANIZATION,
            ]);
        }

        return array_values(array_unique($disabled));
    }

    private function panelRouteName(Request $request, string $suffix): string
    {
        $routeName = (string) optional($request->route())->getName();

        if (str_starts_with($routeName, 'super-admin.')) {
            return 'super-admin.' . $suffix;
        }

        return 'admin.' . $suffix;
    }
}
