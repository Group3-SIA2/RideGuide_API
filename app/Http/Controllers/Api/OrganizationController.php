<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreOrganizationRequest;
use App\Models\Organization;
use App\Models\OrganizationUserRole;
use App\Models\Role;
use App\Models\User;
use App\Models\Driver;
use App\Models\DriverOrganizationAssignmentLog;
use App\Rules\OrganizationOwnerEligible;
use App\Support\DashboardCache;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class OrganizationController extends Controller
{
    /**
     * List active organizations (paginated).
     * GET /api/organizations
     * Access: Any authenticated user.
     */
    public function index(Request $request): JsonResponse
    {
        $query = Organization::where('status', 'active');

        if ($search = $request->input('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('type', 'like', "%{$search}%")
                  ->orWhere('hq_address', 'like', "%{$search}%");
            });
        }

        if ($type = $request->input('type')) {
            $query->where('type', $type);
        }

        $perPage       = min((int) $request->input('per_page', 20), 100);
        $organizations = $query->orderBy('name')->paginate($perPage);

        return response()->json([
            'success' => true,
            'data'    => $organizations,
        ], 200);
    }

    /**
     * Show a single organization.
     * GET /api/organizations/{id}
     * Access: Any authenticated user; non-admins only see active organizations.
     */
    public function show(string $id): JsonResponse
    {
        $user  = auth()->user();
        $query = Organization::withCount('drivers');

        if (!$user->hasRole('admin') && !$user->hasRole('super_admin')) {
            $query->where('status', 'active');
        }

        $organization = $query->find($id);

        if (!$organization) {
            return response()->json([
                'success' => false,
                'message' => 'Organization not found.',
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data'    => $organization,
        ], 200);
    }

    /**
     * Create a new organization.
     * POST /api/organizations
     * Access: admin/super_admin (any org), organization role (one org per user).
     */
    public function store(StoreOrganizationRequest $request): JsonResponse
    {
        $this->authorize('create', Organization::class);

        $user = auth()->user();

        // Organization-role users are limited to one organization.
        if ($user->hasRole('organization')) {
            if (Organization::where('owner_user_id', $user->id)->exists()) {
                return response()->json([
                    'success' => false,
                    'message' => 'You already have a registered organization.',
                ], 409);
            }
        }

        $data = $request->validated();

        // Automatically assign ownership when an organization-role user creates.
        if ($user->hasRole('organization')) {
            $data['owner_user_id'] = $user->id;
        }

        if (array_key_exists('owner_user_id', $data)) {
            $ownerUser = User::withTrashed()->find($data['owner_user_id']);
            $this->authorize('assignOwner', [Organization::class, $ownerUser]);
        }

        $organization = Organization::create($data);

        if (!empty($organization->owner_user_id)) {
            $organizationRoleId = Role::getIdbyName(Role::ORGANIZATION);

            if ($organizationRoleId) {
                OrganizationUserRole::query()->updateOrCreate(
                    [
                        'organization_id' => $organization->id,
                        'user_id' => $organization->owner_user_id,
                        'role_id' => $organizationRoleId,
                    ],
                    [
                        'status' => 'active',
                        'invited_by_user_id' => $user->id,
                    ]
                );
            }
        }

        return response()->json([
            'success' => true,
            'message' => 'Organization created successfully.',
            'data'    => $organization,
        ], 201);
    }

    /**
     * Create an organization profile for the authenticated user while supporting multi-role assignments.
     * POST /api/organizations/create-profile
     * Access: admin/super_admin/organization. Automatically attaches/keeps the organization role.
     */
    public function createProfile(Request $request): JsonResponse
    {
        $user = $request->user();

        $hasEligibleRole = $user->hasRole(Role::ORGANIZATION)
            || $user->hasRole(Role::ADMIN)
            || $user->hasRole(Role::SUPER_ADMIN);

        if (!$hasEligibleRole) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized. Only organization, admin, or super_admin users can create an organization profile.',
            ], 403);
        }

        if (Organization::where('owner_user_id', $user->id)->exists()) {
            return response()->json([
                'success' => false,
                'message' => 'You already have a registered organization.',
            ], 409);
        }

        $validated = $request->validate([
            'name'        => ['required', 'string', 'max:255', 'unique:organizations,name'],
            'type'        => ['required', 'string', 'max:100'],
            'description' => ['nullable', 'string', 'max:1000'],
            'hq_address'  => ['nullable', 'string', 'max:500'],
            'roles'       => ['sometimes', 'array', 'min:1'],
            'roles.*'     => ['string', Rule::in([Role::DRIVER, Role::COMMUTER])],
        ]);

        $additionalRoles = array_unique($validated['roles'] ?? []);
        unset($validated['roles']);

        $roleNamesToSync = array_unique(array_merge([Role::ORGANIZATION], $additionalRoles));
        $roles = Role::whereIn('name', $roleNamesToSync)->get();

        if ($roles->count() !== count($roleNamesToSync)) {
            return response()->json([
                'success' => false,
                'message' => 'One or more selected roles are not available.',
            ], 422);
        }

        $organization = DB::transaction(function () use ($validated, $user, $roles) {
            $organization = Organization::create([
                'name'           => $validated['name'],
                'type'           => $validated['type'],
                'description'    => $validated['description'] ?? null,
                'hq_address'     => $validated['hq_address'] ?? null,
                'status'         => 'active',
                'owner_user_id'  => $user->id,
            ]);

            $organizationRoleId = Role::getIdbyName(Role::ORGANIZATION);
            if ($organizationRoleId) {
                OrganizationUserRole::query()->updateOrCreate(
                    [
                        'organization_id' => $organization->id,
                        'user_id' => $user->id,
                        'role_id' => $organizationRoleId,
                    ],
                    [
                        'status' => 'active',
                        'invited_by_user_id' => $user->id,
                    ]
                );
            }

            $user->roles()->syncWithoutDetaching($roles->pluck('id')->toArray());

            return $organization;
        });

        DashboardCache::forgetUserDashboards($user->id);

        $user->load('roles');

        return response()->json([
            'success' => true,
            'message' => 'Organization profile created successfully.',
            'data'    => [
                'organization' => $organization->fresh(),
                'roles'        => $user->roles->pluck('name'),
            ],
        ], 201);
    }

    /**
     * Update an organization (partial update supported).
     * PUT /api/organizations/{id}
     * Access: admin/super_admin (any), organization role (own org only).
     *         Organization-role users cannot change status.
     */
    public function update(Request $request, string $id): JsonResponse
    {
        $organization = Organization::find($id);
        $user = auth()->user();

        if (!$organization) {
            return response()->json([
                'success' => false,
                'message' => 'Organization not found.',
            ], 404);
        }

        $this->authorize('update', $organization);

        $validated = $request->validate([
            'name'           => ['sometimes', 'string', 'max:255', Rule::unique('organizations', 'name')->ignore($organization->id)],
            'type'           => ['sometimes', 'string', 'max:100'],
            'description'    => ['nullable', 'string', 'max:1000'],
            'hq_address'     => ['nullable', 'string', 'max:500'],
            'owner_user_id'  => [
                'sometimes',
                'nullable',
                'uuid',
                new OrganizationOwnerEligible(),
            ],
            'status' => ['sometimes', Rule::in(['active', 'inactive'])],
        ]);

        // Organization-role users and organization managers cannot toggle owner or status.
        if ($user->hasRole('organization') || $user->isOrganizationManagerFor($organization->id)) {
            unset($validated['status']);
            unset($validated['owner_user_id']);
        }

        if (array_key_exists('owner_user_id', $validated)) {
            $ownerUser = User::withTrashed()->find($validated['owner_user_id']);
            $this->authorize('assignOwner', [Organization::class, $ownerUser]);
        }

        $organization->update($validated);

        if (array_key_exists('owner_user_id', $validated) && !empty($validated['owner_user_id'])) {
            $organizationRoleId = Role::getIdbyName(Role::ORGANIZATION);

            if ($organizationRoleId) {
                OrganizationUserRole::query()->updateOrCreate(
                    [
                        'organization_id' => $organization->id,
                        'user_id' => $validated['owner_user_id'],
                        'role_id' => $organizationRoleId,
                    ],
                    [
                        'status' => 'active',
                        'invited_by_user_id' => $user->id,
                    ]
                );
            }
        }

        return response()->json([
            'success' => true,
            'message' => 'Organization updated successfully.',
            'data'    => $organization->fresh(),
        ], 200);
    }

    /**
     * Soft-delete an organization.
     * DELETE /api/organizations/{id}
     * Access: admin/super_admin (any), organization role (own org only).
     */
    public function destroy(string $id): JsonResponse
    {
        $organization = Organization::find($id);

        if (!$organization) {
            return response()->json([
                'success' => false,
                'message' => 'Organization not found.',
            ], 404);
        }

        $this->authorize('delete', $organization);

        $organization->delete();

        return response()->json([
            'success' => true,
            'message' => 'Organization deleted successfully.',
        ], 200);
    }

    /**
     * Restore a soft-deleted organization.
     * PUT /api/organizations/{id}/restore
     * Access: admin/super_admin only.
     */
    public function restore(string $id): JsonResponse
    {
        $organization = Organization::withTrashed()->find($id);

        if (!$organization) {
            return response()->json([
                'success' => false,
                'message' => 'Organization not found.',
            ], 404);
        }

        $this->authorize('restore', $organization);

        $organization->restore();

        return response()->json([
            'success' => true,
            'message' => 'Organization restored successfully.',
            'data'    => $organization->fresh(),
        ], 200);
    }

    /**
     * Get drivers assigned to the logged-in user's managed organization.
     * GET /api/organizations/assigned-drivers
     * Access: organization role owner OR active organization manager.
     */
    public function getAssignedDrivers(Request $request): JsonResponse
    {
        $user = $request->user();

        if (! $user) {
            return response()->json(['success' => false, 'message' => 'Unauthenticated.'], 401);
        }

        if (! $user->hasRole(Role::ORGANIZATION) && ! $user->hasAnyActiveOrganizationManagement()) {
            return response()->json(['success' => false, 'message' => 'Unauthorized.'], 403);
        }

        $organization = Organization::query()
            ->where(function ($organizationScope) use ($user) {
                $organizationScope->where('owner_user_id', $user->id)
                    ->orWhereHas('organizationUserRoles', function ($organizationUserRoleQuery) use ($user) {
                        $organizationUserRoleQuery->where('user_id', $user->id)
                            ->where('status', 'active');
                    });
            })
            ->first();

        if (! $organization) {
            return response()->json(['success' => false, 'message' => 'Organization not found for this user.'], 404);
        }

        $perPage = min((int) $request->input('per_page', 20), 100);

        $drivers = Driver::with(['user', 'organization'])
            ->where('organization_id', $organization->id)
            ->orderBy('created_at', 'desc')
            ->paginate($perPage);

        // Format drivers
        $data = $drivers->through(function ($driver) {
            return [
                'id' => $driver->id,
                'user_id' => $driver->user_id,
                'user' => $driver->user ? [
                    'id' => $driver->user->id,
                    'first_name' => $driver->user->first_name,
                    'last_name' => $driver->user->last_name,
                    'email' => $driver->user->email,
                ] : null,
                'organization' => $driver->organization ? [
                    'id' => $driver->organization->id,
                    'name' => $driver->organization->name,
                ] : null,
                'driver_license_id' => $driver->driver_license_id,
                'verification_status' => $driver->verification_status ?? null,
                'created_at' => $driver->created_at,
                'updated_at' => $driver->updated_at,
            ];
        });

        return response()->json([
            'success' => true,
            'data' => $drivers,
        ], 200);
    }
}
