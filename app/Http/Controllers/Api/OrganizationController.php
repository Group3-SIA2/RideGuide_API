<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreOrganizationRequest;
use App\Models\Organization;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
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
                  ->orWhere('address', 'like', "%{$search}%");
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

        $organization = Organization::create($data);

        return response()->json([
            'success' => true,
            'message' => 'Organization created successfully.',
            'data'    => $organization,
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
            'address'        => ['nullable', 'string', 'max:500'],
            'contact_number' => ['nullable', 'string', 'max:20'],
            'status'         => ['sometimes', Rule::in(['active', 'inactive'])],
        ]);

        // Organization-role users cannot toggle their own status.
        if (auth()->user()->hasRole('organization')) {
            unset($validated['status']);
        }

        $organization->update($validated);

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
}
