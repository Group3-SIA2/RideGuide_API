<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreOrganizationRequest;
use App\Models\Driver;
use App\Models\HqAddress;
use App\Models\Organization;
use App\Models\OrganizationType;
use App\Models\OrganizationUserRole;
use App\Models\Role;
use App\Models\User;
use App\Rules\OrganizationOwnerEligible;
use App\Support\DashboardCache;
use App\Support\InputValidation;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class OrganizationController extends Controller
{
    /**
     * List active organizations (paginated).
     * GET /api/organizations
     * Access: Any authenticated user.
     */
    public function index(Request $request): JsonResponse
    {
        $filters = $request->validate([
            'include_deleted' => ['nullable', 'boolean'],
            'status' => ['nullable', Rule::in(['active', 'inactive'])],
            'search' => InputValidation::safeSearchRules(120),
            'organization_type' => InputValidation::safeStringRules(required: false, max: 100),
            'organization_type_id' => ['nullable', 'uuid'],
            'owner_user_id' => ['nullable', 'uuid'],
            'sort_by' => ['nullable', Rule::in(['name', 'status', 'created_at', 'updated_at'])],
            'sort_dir' => ['nullable', Rule::in(['asc', 'desc'])],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);

        $user = $request->user();
        $isAdmin = $this->isAdminUser($user);

        $query = Organization::query()->with([
            'organizationType',
            'hqAddress',
        ]);

        if (! $isAdmin) {
            $query->where('status', 'active');
        } else {
            if (($filters['include_deleted'] ?? false) === true) {
                $query->withTrashed();
            }

            if ($status = ($filters['status'] ?? null)) {
                if (in_array($status, ['active', 'inactive'], true)) {
                    $query->where('status', $status);
                }
            }
        }

        $search = trim((string) ($filters['search'] ?? ''));
        if ($search !== '') {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhereHas('organizationType', function ($typeQ) use ($search) {
                        $typeQ->where('name', 'like', "%{$search}%")
                            ->orWhere('description', 'like', "%{$search}%");
                    })
                    ->orWhereHas('hqAddress', function ($addressQ) use ($search) {
                        $addressQ->where('barangay', 'like', "%{$search}%")
                            ->orWhere('street', 'like', "%{$search}%")
                            ->orWhere('subdivision', 'like', "%{$search}%")
                            ->orWhere('floor_unit_room', 'like', "%{$search}%");
                    });
            });
        }

        if ($organizationType = ($filters['organization_type'] ?? null)) {
            $query->whereHas('organizationType', function ($typeQ) use ($organizationType) {
                $typeQ->where('name', $organizationType);
            });
        }

        if ($organizationTypeId = ($filters['organization_type_id'] ?? null)) {
            $query->where('organization_type_id', $organizationTypeId);
        }

        if ($ownerUserId = ($filters['owner_user_id'] ?? null)) {
            $query->where('owner_user_id', $ownerUserId);
        }

        $sortableColumns = ['name', 'status', 'created_at', 'updated_at'];
        $sortBy = $filters['sort_by'] ?? 'name';
        if (! in_array($sortBy, $sortableColumns, true)) {
            $sortBy = 'name';
        }

        $sortDir = strtolower((string) ($filters['sort_dir'] ?? 'asc')) === 'desc' ? 'desc' : 'asc';

        $perPage = max(1, min((int) ($filters['per_page'] ?? 20), 100));
        $organizations = $query->orderBy($sortBy, $sortDir)->paginate($perPage)->withQueryString();

        return response()->json([
            'success' => true,
            'data' => $organizations,
            'meta' => [
                'filters' => [
                    'search' => $search !== '' ? $search : null,
                    'organization_type' => $filters['organization_type'] ?? null,
                    'organization_type_id' => $filters['organization_type_id'] ?? null,
                    'owner_user_id' => $filters['owner_user_id'] ?? null,
                    'status' => $filters['status'] ?? null,
                    'include_deleted' => $isAdmin ? (($filters['include_deleted'] ?? false) === true) : false,
                ],
                'sort' => [
                    'by' => $sortBy,
                    'dir' => $sortDir,
                ],
            ],
        ], 200);
    }

    /**
     * Show a single organization.
     * GET /api/organizations/{id}
     * Access: Any authenticated user; non-admins only see active organizations.
     */
    public function show(string $id): JsonResponse
    {
        $user = auth()->user();
        $query = Organization::withCount('drivers')->with(['organizationType', 'hqAddress']);

        if ($this->isAdminUser($user) && request()->boolean('include_deleted')) {
            $query->withTrashed();
        }

        if (! $user->hasRole('admin') && ! $user->hasRole('super_admin')) {
            $query->where('status', 'active');
        }

        $organization = $query->find($id);

        if (! $organization) {
            return response()->json([
                'success' => false,
                'message' => 'Organization not found.',
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data' => $organization,
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

        if (array_key_exists('organization_type', $data)) {
            $data['organization_type'] = trim($data['organization_type']);
        }

        $organizationTypeName = $data['organization_type'] ?? null;
        $resolvedOrganizationTypeId = $this->resolveOrganizationTypeIdFromPayload($data, $data['organization_type_id'] ?? null);
        $data['organization_type_id'] = $resolvedOrganizationTypeId;
        unset($data['organization_type']);

        if (array_key_exists('description', $data)) {
            if (! empty($resolvedOrganizationTypeId)) {
                $this->syncOrganizationTypeDescriptionById($resolvedOrganizationTypeId, $data['description'], true);
            } else {
                $this->syncOrganizationTypeDescriptionByName($organizationTypeName, $data['description'], true);
            }

            unset($data['description']);
        }

        $data['hq_address'] = $this->resolveHqAddressIdFromPayload($data);
        unset(
            $data['hq_street'],
            $data['hq_barangay'],
            $data['hq_subdivision'],
            $data['hq_floor_unit_room'],
            $data['hq_lat'],
            $data['hq_lng']
        );

        // Automatically assign ownership when an organization-role user creates.
        if ($user->hasRole('organization')) {
            $data['owner_user_id'] = $user->id;
        }

        if (array_key_exists('owner_user_id', $data)) {
            $ownerUser = User::withTrashed()->find($data['owner_user_id']);
            $this->authorize('assignOwner', [Organization::class, $ownerUser]);
        }

        $organization = Organization::create($data);

        if (! empty($organization->owner_user_id)) {
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
            'data' => $organization,
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

        if (! $hasEligibleRole) {
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
            'name' => ['required', 'string', 'max:255', 'unique:organizations,name'],
            'organization_type_id' => [
                'required_without:organization_type',
                'uuid',
                Rule::exists('organization_types', 'id')->whereNull('deleted_at'),
            ],
            'organization_type' => [
                'required_without:organization_type_id',
                'string',
                'max:100',
                Rule::exists('organization_types', 'name')->whereNull('deleted_at'),
            ],
            'description' => ['nullable', 'string', 'max:1000'],
            'hq_address' => ['nullable', 'string', 'max:500'],
            'hq_street' => ['nullable', 'string', 'max:255'],
            'hq_barangay' => ['nullable', 'string', 'max:255'],
            'hq_subdivision' => ['nullable', 'string', 'max:255'],
            'hq_floor_unit_room' => ['nullable', 'string', 'max:255'],
            'hq_lat' => ['nullable', 'string', 'max:50'],
            'hq_lng' => ['nullable', 'string', 'max:50'],
            'roles' => ['sometimes', 'array', 'min:1'],
            'roles.*' => ['string', Rule::in([Role::DRIVER, Role::COMMUTER])],
        ]);

        $additionalRoles = array_unique($validated['roles'] ?? []);
        unset($validated['roles']);
        if (array_key_exists('organization_type', $validated)) {
            $validated['organization_type'] = trim((string) $validated['organization_type']);
        }

        $resolvedOrganizationTypeId = $this->resolveOrganizationTypeIdFromPayload($validated, $validated['organization_type_id'] ?? null);
        $resolvedHqAddressId = $this->resolveHqAddressIdFromPayload($validated);

        if (array_key_exists('description', $validated)) {
            if (! empty($resolvedOrganizationTypeId)) {
                $this->syncOrganizationTypeDescriptionById($resolvedOrganizationTypeId, $validated['description'], true);
            } else {
                $this->syncOrganizationTypeDescriptionByName($validated['organization_type'] ?? null, $validated['description'], true);
            }
        }

        $roleNamesToSync = array_unique(array_merge([Role::ORGANIZATION], $additionalRoles));
        $roles = Role::whereIn('name', $roleNamesToSync)->get();

        if ($roles->count() !== count($roleNamesToSync)) {
            return response()->json([
                'success' => false,
                'message' => 'One or more selected roles are not available.',
            ], 422);
        }

        $organization = DB::transaction(function () use ($validated, $user, $roles, $resolvedOrganizationTypeId, $resolvedHqAddressId) {
            $organization = Organization::create([
                'name' => $validated['name'],
                'organization_type_id' => $resolvedOrganizationTypeId,
                'hq_address' => $resolvedHqAddressId,
                'status' => 'active',
                'owner_user_id' => $user->id,
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
            'data' => [
                'organization' => $organization->fresh(),
                'roles' => $user->roles->pluck('name'),
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

        if (! $organization) {
            return response()->json([
                'success' => false,
                'message' => 'Organization not found.',
            ], 404);
        }

        $this->authorize('update', $organization);

        $validated = $request->validate([
            'name' => ['sometimes', 'string', 'max:255', Rule::unique('organizations', 'name')->ignore($organization->id)],
            'organization_type_id' => [
                'sometimes',
                'uuid',
                Rule::exists('organization_types', 'id')->whereNull('deleted_at'),
            ],
            'organization_type' => [
                'sometimes',
                'string',
                'max:100',
                Rule::exists('organization_types', 'name')->whereNull('deleted_at'),
            ],
            'description' => ['nullable', 'string', 'max:1000'],
            'hq_address' => ['nullable', 'string', 'max:500'],
            'hq_street' => ['nullable', 'string', 'max:255'],
            'hq_barangay' => ['nullable', 'string', 'max:255'],
            'hq_subdivision' => ['nullable', 'string', 'max:255'],
            'hq_floor_unit_room' => ['nullable', 'string', 'max:255'],
            'hq_lat' => ['nullable', 'string', 'max:50'],
            'hq_lng' => ['nullable', 'string', 'max:50'],
            'owner_user_id' => [
                'sometimes',
                'nullable',
                'uuid',
                new OrganizationOwnerEligible,
            ],
            'status' => ['sometimes', Rule::in(['active', 'inactive'])],
        ]);

        if (array_key_exists('organization_type', $validated)) {
            $validated['organization_type'] = trim($validated['organization_type']);
        }

        if (
            array_key_exists('organization_type_id', $validated)
            || array_key_exists('organization_type', $validated)
        ) {
            $validated['organization_type_id'] = $this->resolveOrganizationTypeIdFromPayload($validated, $organization->organization_type_id);
            unset($validated['organization_type']);
        }

        if (array_key_exists('description', $validated)) {
            if (! empty($validated['organization_type_id'])) {
                $this->syncOrganizationTypeDescriptionById($validated['organization_type_id'], $validated['description'], true);
            } else {
                $this->syncOrganizationTypeDescriptionById($organization->organization_type_id, $validated['description'], true);
            }

            unset($validated['description']);
        }

        $hasAddressPayload =
            array_key_exists('hq_address', $validated)
            || array_key_exists('hq_street', $validated)
            || array_key_exists('hq_barangay', $validated)
            || array_key_exists('hq_subdivision', $validated)
            || array_key_exists('hq_floor_unit_room', $validated)
            || array_key_exists('hq_lat', $validated)
            || array_key_exists('hq_lng', $validated);

        if ($hasAddressPayload) {
            $validated['hq_address'] = $this->resolveHqAddressIdFromPayload($validated, $organization);
        }

        unset(
            $validated['hq_street'],
            $validated['hq_barangay'],
            $validated['hq_subdivision'],
            $validated['hq_floor_unit_room'],
            $validated['hq_lat'],
            $validated['hq_lng']
        );

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

        if (array_key_exists('owner_user_id', $validated) && ! empty($validated['owner_user_id'])) {
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
            'data' => $organization->fresh(),
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

        if (! $organization) {
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

        if (! $organization) {
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
            'data' => $organization->fresh(),
        ], 200);
    }

    private function syncOrganizationTypeDescriptionByName(?string $organizationTypeName, ?string $description, bool $shouldUpdateDescription = true): void
    {
        $typeName = trim((string) $organizationTypeName);
        if ($typeName === '') {
            return;
        }

        $organizationType = OrganizationType::withTrashed()->firstOrNew(['name' => $typeName]);

        if (! $organizationType->exists) {
            $organizationType->save();
        } elseif ($organizationType->trashed()) {
            $organizationType->restore();
        }

        if ($shouldUpdateDescription) {
            $organizationType->description = $this->normalizeDescription($description);
            $organizationType->save();
        }
    }

    private function syncOrganizationTypeDescriptionById(?string $organizationTypeId, ?string $description, bool $shouldUpdateDescription = true): void
    {
        if (empty($organizationTypeId)) {
            return;
        }

        $organizationType = OrganizationType::withTrashed()->find($organizationTypeId);
        if (! $organizationType) {
            return;
        }

        if ($organizationType->trashed()) {
            $organizationType->restore();
        }

        if ($shouldUpdateDescription) {
            $organizationType->description = $this->normalizeDescription($description);
            $organizationType->save();
        }
    }

    private function normalizeDescription(?string $description): ?string
    {
        $normalized = trim((string) $description);

        return $normalized === '' ? null : $normalized;
    }

    private function resolveOrganizationTypeIdFromPayload(array $payload, ?string $fallbackOrganizationTypeId = null): ?string
    {
        $organizationTypeId = $payload['organization_type_id'] ?? null;
        if (! empty($organizationTypeId)) {
            return $organizationTypeId;
        }

        if (array_key_exists('organization_type', $payload)) {
            $organizationTypeName = trim((string) ($payload['organization_type'] ?? ''));

            if ($organizationTypeName === '') {
                return $fallbackOrganizationTypeId;
            }

            $resolvedOrganizationTypeId = OrganizationType::query()
                ->where('name', $organizationTypeName)
                ->whereNull('deleted_at')
                ->value('id');

            if (empty($resolvedOrganizationTypeId)) {
                throw ValidationException::withMessages([
                    'organization_type' => 'Selected organization type does not exist.',
                ]);
            }

            return $resolvedOrganizationTypeId;
        }

        return $fallbackOrganizationTypeId;
    }

    private function resolveHqAddressIdFromPayload(array $payload, ?Organization $organization = null): ?string
    {
        $street = trim((string) ($payload['hq_street'] ?? ''));
        $barangay = trim((string) ($payload['hq_barangay'] ?? ''));
        $subdivision = $this->normalizeDescription($payload['hq_subdivision'] ?? null);
        $floorUnitRoom = $this->normalizeDescription($payload['hq_floor_unit_room'] ?? null);
        $latitude = $this->normalizeDescription($payload['hq_lat'] ?? null);
        $longitude = $this->normalizeDescription($payload['hq_lng'] ?? null);

        $hasStructuredAddressInput =
            $street !== ''
            || $barangay !== ''
            || ! is_null($subdivision)
            || ! is_null($floorUnitRoom)
            || ! is_null($latitude)
            || ! is_null($longitude);

        if ($hasStructuredAddressInput) {
            if ($street === '' || $barangay === '') {
                throw ValidationException::withMessages([
                    'hq_street' => 'Both hq_street and hq_barangay are required when providing HQ address details.',
                    'hq_barangay' => 'Both hq_street and hq_barangay are required when providing HQ address details.',
                ]);
            }

            $addressData = [
                'street' => $street,
                'barangay' => $barangay,
                'subdivision' => $subdivision,
                'floor_unit_room' => $floorUnitRoom,
                'lat' => $latitude,
                'lng' => $longitude,
            ];

            if ($organization && $organization->hqAddress) {
                $organization->hqAddress->update($addressData);

                return $organization->hqAddress->id;
            }

            $hqAddress = HqAddress::query()->firstOrCreate(
                [
                    'barangay' => $barangay,
                    'street' => $street,
                ],
                [
                    'subdivision' => $subdivision,
                    'floor_unit_room' => $floorUnitRoom,
                    'lat' => $latitude,
                    'lng' => $longitude,
                ]
            );

            return $hqAddress->id;
        }

        if (! array_key_exists('hq_address', $payload)) {
            return $organization?->hq_address;
        }

        $hqAddressInput = trim((string) ($payload['hq_address'] ?? ''));
        if ($hqAddressInput === '') {
            return null;
        }

        if (Str::isUuid($hqAddressInput)) {
            $existingHqAddress = HqAddress::query()->find($hqAddressInput);
            if (! $existingHqAddress) {
                throw ValidationException::withMessages([
                    'hq_address' => 'Selected HQ address does not exist.',
                ]);
            }

            return $existingHqAddress->id;
        }

        // Backward-compatible fallback for legacy free-text payloads.
        $parts = array_values(array_filter(array_map('trim', explode(',', $hqAddressInput)), function ($value) {
            return $value !== '';
        }));

        $derivedStreet = $parts[0] ?? 'Unspecified Street';
        $derivedBarangay = $parts[1] ?? $derivedStreet;
        $derivedSubdivision = $parts[2] ?? null;

        $legacyAddress = HqAddress::query()->firstOrCreate(
            [
                'barangay' => $derivedBarangay,
                'street' => $derivedStreet,
            ],
            [
                'subdivision' => $derivedSubdivision,
                'floor_unit_room' => null,
                'lat' => null,
                'lng' => null,
            ]
        );

        return $legacyAddress->id;
    }

    private function isAdminUser(?User $user): bool
    {
        if (! $user) {
            return false;
        }

        return $user->hasRole(Role::ADMIN) || $user->hasRole(Role::SUPER_ADMIN);
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

        $drivers = Driver::with(['user', 'organization.organizationType'])
            ->where('organization_id', $organization->id)
            ->orderBy('created_at', 'desc')
            ->paginate($perPage);

        // Format drivers
        $drivers = $drivers->through(function ($driver) {
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
                    'organization_type' => $driver->organization->organization_type,
                    'description' => $driver->organization->description,
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
