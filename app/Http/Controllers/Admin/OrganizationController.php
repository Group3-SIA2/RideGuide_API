<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreOrganizationRequest;
use App\Http\Requests\UpdateOrganizationRequest;
use App\Models\Driver;
use App\Models\DriverOrganizationAssignmentLog;
use App\Models\Organization;
use App\Models\Role;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class OrganizationController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth');
    }

    public function index(Request $request)
    {
        $this->authorizePermissions($request, 'view_organizations');
        $currentUser = $request->user();

        $showDeleted = $request->input('status') === 'deleted';

        $query = $showDeleted
            ? Organization::onlyTrashed()->withCount('drivers')
            : Organization::withCount('drivers');

        if ($search = $request->input('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('type', 'like', "%{$search}%")
                  ->orWhere('hq_address', 'like', "%{$search}%");
            });
        }

        if ($request->input('type')) {
            $query->where('type', $request->input('type'));
        }

        if (!$showDeleted && $request->input('status')) {
            $query->where('status', $request->input('status'));
        }

        // Organization managers can only view organizations they own.
        if ($currentUser->hasRole(Role::ORGANIZATION)
            && !$currentUser->hasRole(Role::ADMIN)
            && !$currentUser->hasRole(Role::SUPER_ADMIN)
        ) {
            $query->where('owner_user_id', $currentUser->id);
        }

        $organizations = $query->orderBy('name')->paginate(15)->withQueryString();
        $types         = Organization::distinct()->pluck('type')->filter();

        if ($request->ajax()) {
            return response()->json([
                'rows'        => view('admin.organizations._rows', compact('organizations', 'showDeleted'))->render(),
                'pagination'  => $organizations->hasPages() ? (string) $organizations->links() : '',
                'total'       => $organizations->total(),
            ]);
        }

        return view('admin.organizations.index', compact('organizations', 'types', 'showDeleted'));
    }

    public function managerDashboard(Request $request)
    {
        $this->authorizePermissions($request, 'view_organization_dashboard');

        $currentUser = $request->user();
        $organizationsForAdmin = collect();
        $selectedOrganizationId = $request->input('organization_id');

        if ($currentUser->hasRole(Role::ADMIN) || $currentUser->hasRole(Role::SUPER_ADMIN)) {
            $organizationsForAdmin = Organization::query()
                ->whereNull('deleted_at')
                ->orderBy('name')
                ->get(['id', 'name']);

            $managedOrganization = $selectedOrganizationId
                ? Organization::query()->whereNull('deleted_at')->find($selectedOrganizationId)
                : null;
        } else {
            $managedOrganization = $this->managedOrganizationFor($currentUser);
        }

        $totalAssignedDrivers = 0;
        $unverifiedDriverLicenses = 0;
        $availableDriversCount = 0;
        $recentlyAssignedDrivers = collect();

        if ($managedOrganization) {
            $totalAssignedDrivers = Driver::where('organization_id', $managedOrganization->id)->count();

            $unverifiedDriverLicenses = Driver::where('organization_id', $managedOrganization->id)
                ->whereHas('licenseId', function ($query) {
                    $query->where('verification_status', '!=', 'verified');
                })
                ->count();

            $availableDriversCount = Driver::whereNull('organization_id')->count();

            $recentlyAssignedDrivers = Driver::with(['user', 'licenseId'])
                ->where('organization_id', $managedOrganization->id)
                ->latest('updated_at')
                ->take(10)
                ->get();
        }

        return view('admin.organizations.manager-dashboard', compact(
            'managedOrganization',
            'totalAssignedDrivers',
            'unverifiedDriverLicenses',
            'availableDriversCount',
            'recentlyAssignedDrivers',
            'organizationsForAdmin',
            'selectedOrganizationId'
        ));
    }

    public function assignmentIndex(Request $request)
    {
        $this->authorizePermissions($request, 'assign_drivers_to_organization');

        $currentUser = $request->user();
        $organizationsForAdmin = collect();
        $selectedOrganizationId = $request->input('organization_id');

        if ($currentUser->hasRole(Role::ADMIN) || $currentUser->hasRole(Role::SUPER_ADMIN)) {
            $organizationsForAdmin = Organization::query()
                ->whereNull('deleted_at')
                ->orderBy('name')
                ->get(['id', 'name']);

            $managedOrganization = $selectedOrganizationId
                ? Organization::query()->whereNull('deleted_at')->find($selectedOrganizationId)
                : null;
        } else {
            $managedOrganization = $this->managedOrganizationFor($currentUser);
        }

        if (!$managedOrganization) {
            return view('admin.organizations.assignments', [
                'managedOrganization' => null,
                'assignedDrivers' => Driver::query()->whereRaw('1 = 0')->paginate(10, ['*'], 'assigned_page'),
                'availableDrivers' => Driver::query()->whereRaw('1 = 0')->paginate(10, ['*'], 'available_page'),
                'organizationsForAdmin' => $organizationsForAdmin,
                'selectedOrganizationId' => $selectedOrganizationId,
            ]);
        }

        $search = $request->input('search');
        $status = $request->input('status');

        $assignedDriversQuery = Driver::with(['user', 'licenseId', 'organization'])
            ->where('organization_id', $managedOrganization->id);

        if ($search) {
            $assignedDriversQuery->where(function ($query) use ($search) {
                $query->whereHas('user', function ($userQuery) use ($search) {
                    $userQuery->where('first_name', 'like', "%{$search}%")
                        ->orWhere('last_name', 'like', "%{$search}%")
                        ->orWhere('email', 'like', "%{$search}%");
                });
            });
        }

        if ($status) {
            $assignedDriversQuery->whereHas('licenseId', function ($licenseQuery) use ($status) {
                $licenseQuery->where('verification_status', $status);
            });
        }

        $assignedDrivers = $assignedDriversQuery
            ->latest('updated_at')
            ->paginate(10, ['*'], 'assigned_page')
            ->withQueryString();

        $availableDriversQuery = Driver::with(['user', 'licenseId'])
            ->whereNull('organization_id');

        if ($search) {
            $availableDriversQuery->where(function ($query) use ($search) {
                $query->whereHas('user', function ($userQuery) use ($search) {
                    $userQuery->where('first_name', 'like', "%{$search}%")
                        ->orWhere('last_name', 'like', "%{$search}%")
                        ->orWhere('email', 'like', "%{$search}%");
                });
            });
        }

        if ($status) {
            $availableDriversQuery->whereHas('licenseId', function ($licenseQuery) use ($status) {
                $licenseQuery->where('verification_status', $status);
            });
        }

        $availableDrivers = $availableDriversQuery
            ->latest('created_at')
            ->paginate(10, ['*'], 'available_page')
            ->withQueryString();

        return view('admin.organizations.assignments', compact(
            'managedOrganization',
            'assignedDrivers',
            'availableDrivers',
            'organizationsForAdmin',
            'selectedOrganizationId'
        ));
    }

    public function assignDriver(Request $request, Driver $driver)
    {
        $this->authorizePermissions($request, 'assign_drivers_to_organization');

        $targetOrganization = $this->resolveTargetOrganizationForAssignment($request);

        $this->authorize('assignToOwnedOrganization', [$driver, $targetOrganization]);

        $oldOrganizationId = $driver->organization_id;
        $driver->update(['organization_id' => $targetOrganization->id]);

        $this->logDriverAssignmentAction(
            $driver,
            $oldOrganizationId,
            $targetOrganization->id,
            $request->user()->id,
            $oldOrganizationId ? DriverOrganizationAssignmentLog::ACTION_REASSIGN : DriverOrganizationAssignmentLog::ACTION_ASSIGN
        );

        $query = [];
        if ($request->filled('organization_id')) {
            $query['organization_id'] = $request->input('organization_id');
        }

        return redirect()->route('admin.organizations.assignments.index', $query)
            ->with('success', 'Driver assigned to your organization successfully.');
    }

    public function updateDriverAssignment(Request $request, Driver $driver)
    {
        $this->authorizePermissions($request, 'assign_drivers_to_organization');

        $targetOrganization = $this->resolveTargetOrganizationForAssignment($request);

        $this->authorize('assignToOwnedOrganization', [$driver, $targetOrganization]);

        $oldOrganizationId = $driver->organization_id;
        $driver->update(['organization_id' => $targetOrganization->id]);

        $this->logDriverAssignmentAction(
            $driver,
            $oldOrganizationId,
            $targetOrganization->id,
            $request->user()->id,
            $oldOrganizationId ? DriverOrganizationAssignmentLog::ACTION_REASSIGN : DriverOrganizationAssignmentLog::ACTION_ASSIGN
        );

        $query = [];
        if ($request->filled('organization_id')) {
            $query['organization_id'] = $request->input('organization_id');
        }

        return redirect()->route('admin.organizations.assignments.index', $query)
            ->with('success', 'Driver assignment updated successfully.');
    }

    public function unassignDriver(Request $request, Driver $driver)
    {
        $this->authorizePermissions($request, 'assign_drivers_to_organization');

        if (!$driver->organization_id) {
            return redirect()->route('admin.organizations.assignments.index', [
                'organization_id' => $request->input('organization_id'),
            ])->with('error', 'Driver is not assigned to any organization.');
        }

        $organizationForPolicy = Organization::query()->findOrFail($driver->organization_id);

        $this->authorize('unassignFromOwnedOrganization', [$driver, $organizationForPolicy]);

        $query = [];
        if ($request->filled('organization_id')) {
            $query['organization_id'] = $request->input('organization_id');
        }

        $oldOrganizationId = $driver->organization_id;
        $driver->update(['organization_id' => null]);

        $this->logDriverAssignmentAction(
            $driver,
            $oldOrganizationId,
            null,
            $request->user()->id,
            DriverOrganizationAssignmentLog::ACTION_UNASSIGN
        );

        return redirect()->route('admin.organizations.assignments.index', $query)
            ->with('success', 'Driver unassigned successfully.');
    }

    public function create(Request $request)
    {
        $this->authorizePermissions($request, 'create_organizations');
        $this->authorize('create', Organization::class);

        $eligibleOwners = User::query()
            ->whereNull('deleted_at')
            ->where('status', User::STATUS_ACTIVE)
            ->whereHas('roles', function ($query) {
                $query->whereIn('name', [Role::ADMIN, Role::SUPER_ADMIN, Role::ORGANIZATION]);
            })
            ->orderBy('first_name')
            ->orderBy('last_name')
            ->get(['id', 'first_name', 'last_name', 'email']);

        $existingNames = Organization::query()
            ->orderBy('name')
            ->pluck('name')
            ->filter()
            ->values();

        $existingTypes = Organization::query()
            ->orderBy('type')
            ->distinct()
            ->pluck('type')
            ->filter()
            ->values();

        return view('admin.organizations.create', compact('eligibleOwners', 'existingNames', 'existingTypes'));
    }

    public function store(StoreOrganizationRequest $request)
    {
        $this->authorizePermissions($request, 'create_organizations');
        $this->authorize('create', Organization::class);

        $validated = $request->validated();

        if (array_key_exists('owner_user_id', $validated)) {
            $ownerUser = User::withTrashed()->find($validated['owner_user_id']);
            $this->authorize('assignOwner', [Organization::class, $ownerUser]);
            $this->ensureOwnerHasOrganizationRole($validated['owner_user_id']);
        }

        Organization::create($validated);

        return redirect()->route('admin.organizations.index')
            ->with('success', 'Organization created successfully.');
    }

    public function edit(Request $request, string $id)
    {
        $this->authorizePermissions($request, 'edit_organizations');
        $organization = Organization::findOrFail($id);
        $this->authorize('update', $organization);

        $eligibleOwners = User::query()
            ->whereNull('deleted_at')
            ->where('status', User::STATUS_ACTIVE)
            ->whereHas('roles', function ($query) {
                $query->whereIn('name', [Role::ADMIN, Role::SUPER_ADMIN, Role::ORGANIZATION]);
            })
            ->orderBy('first_name')
            ->orderBy('last_name')
            ->get(['id', 'first_name', 'last_name', 'email']);

        $existingNames = Organization::query()
            ->orderBy('name')
            ->pluck('name')
            ->filter()
            ->values();

        $existingTypes = Organization::query()
            ->orderBy('type')
            ->distinct()
            ->pluck('type')
            ->filter()
            ->values();

        return view('admin.organizations.edit', compact('organization', 'eligibleOwners', 'existingNames', 'existingTypes'));
    }

    public function update(UpdateOrganizationRequest $request, string $id)
    {
        $this->authorizePermissions($request, 'edit_organizations');
        $organization = Organization::findOrFail($id);
        $this->authorize('update', $organization);

        $validated = $request->validated();

        if (array_key_exists('owner_user_id', $validated)) {
            $ownerUser = User::withTrashed()->find($validated['owner_user_id']);
            $this->authorize('assignOwner', [Organization::class, $ownerUser]);
            $this->ensureOwnerHasOrganizationRole($validated['owner_user_id']);
        }

        $organization->update($validated);

        return redirect()->route('admin.organizations.index')
            ->with('success', 'Organization updated successfully.');
    }

    public function destroy(Request $request, string $id)
    {
        $this->authorizePermissions($request, 'delete_organizations');
        $organization = Organization::findOrFail($id);
        $this->authorize('delete', $organization);

        $organization->delete();

        return redirect()->route('admin.organizations.index')
            ->with('success', 'Organization deleted successfully.');
    }

    public function restore(Request $request, string $id)
    {
        $this->authorizePermissions($request, 'delete_organizations');
        $organization = Organization::withTrashed()->findOrFail($id);
        $this->authorize('restore', $organization);

        $organization->restore();

        return redirect()->route('admin.organizations.index')
            ->with('success', 'Organization restored successfully.');
    }

    private function ensureOwnerHasOrganizationRole(?string $ownerUserId): void
    {
        if (!$ownerUserId) {
            return;
        }

        $organizationRoleId = Role::getIdbyName(Role::ORGANIZATION);
        if (!$organizationRoleId) {
            return;
        }

        $ownerUser = User::withTrashed()->find($ownerUserId);
        if (!$ownerUser || $ownerUser->trashed() || $ownerUser->status !== User::STATUS_ACTIVE) {
            return;
        }

        $ownerUser->roles()->syncWithoutDetaching([$organizationRoleId]);
    }

    private function managedOrganizationFor(User $user): ?Organization
    {
        if ($user->hasRole(Role::ADMIN) || $user->hasRole(Role::SUPER_ADMIN)) {
            return null;
        }

        return Organization::query()
            ->where('owner_user_id', $user->id)
            ->whereNull('deleted_at')
            ->first();
    }

    private function resolveTargetOrganizationForAssignment(Request $request): Organization
    {
        $currentUser = $request->user();

        if ($currentUser->hasRole(Role::ADMIN) || $currentUser->hasRole(Role::SUPER_ADMIN)) {
            $validated = $request->validate([
                'organization_id' => ['required', 'uuid', Rule::exists('organizations', 'id')],
            ]);

            return Organization::query()->findOrFail($validated['organization_id']);
        }

        $managedOrganization = $this->managedOrganizationFor($currentUser);
        if (!$managedOrganization) {
            abort(403, 'No managed organization is assigned to your account.');
        }

        return $managedOrganization;
    }

    private function logDriverAssignmentAction(
        Driver $driver,
        ?string $oldOrganizationId,
        ?string $newOrganizationId,
        ?string $actedByUserId,
        string $action
    ): void {
        DriverOrganizationAssignmentLog::create([
            'driver_id' => $driver->id,
            'old_organization_id' => $oldOrganizationId,
            'new_organization_id' => $newOrganizationId,
            'acted_by_user_id' => $actedByUserId,
            'action' => $action,
        ]);
    }
}
