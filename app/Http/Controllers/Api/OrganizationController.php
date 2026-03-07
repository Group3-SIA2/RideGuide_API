<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Organization;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class OrganizationController extends Controller
{
    /**
     * List all active organizations.
     * GET /api/organizations
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

        $organizations = $query->orderBy('name')->get();

        return response()->json([
            'success' => true,
            'data'    => $organizations,
        ], 200);
    }

    /**
     * Show a single organization.
     * GET /api/organizations/{id}
     */
    public function show(string $id): JsonResponse
    {
        $organization = Organization::withCount('drivers')->find($id);

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
     * Create a new organization (admin only).
     * POST /api/organizations
     */
    public function store(Request $request): JsonResponse
    {
        $user = auth()->user();
        if (!$user || !in_array($user->role->name, ['admin', 'super_admin'])) {
            return response()->json(['error' => 'Unauthorized.'], 403);
        }

        $validated = $request->validate([
            'name'           => ['required', 'string', 'max:255', 'unique:organizations,name'],
            'type'           => ['required', 'string', 'max:100'],
            'address'        => ['nullable', 'string', 'max:500'],
            'contact_number' => ['nullable', 'string', 'max:20'],
        ]);

        $organization = Organization::create($validated);

        return response()->json([
            'success' => true,
            'message' => 'Organization created successfully.',
            'data'    => $organization,
        ], 201);
    }

    /**
     * Update an organization (admin only).
     * PUT /api/organizations/{id}
     */
    public function update(Request $request, string $id): JsonResponse
    {
        $user = auth()->user();
        if (!$user || !in_array($user->role->name, ['admin', 'super_admin'])) {
            return response()->json(['error' => 'Unauthorized.'], 403);
        }

        $organization = Organization::find($id);
        if (!$organization) {
            return response()->json([
                'success' => false,
                'message' => 'Organization not found.',
            ], 404);
        }

        $validated = $request->validate([
            'name'           => ['sometimes', 'string', 'max:255', Rule::unique('organizations', 'name')->ignore($organization->id)],
            'type'           => ['sometimes', 'string', 'max:100'],
            'address'        => ['nullable', 'string', 'max:500'],
            'contact_number' => ['nullable', 'string', 'max:20'],
            'status'         => ['sometimes', Rule::in(['active', 'inactive'])],
        ]);

        $organization->update($validated);

        return response()->json([
            'success' => true,
            'message' => 'Organization updated successfully.',
            'data'    => $organization->fresh(),
        ], 200);
    }

    /**
     * Soft-delete an organization (admin only).
     * DELETE /api/organizations/{id}
     */
    public function destroy(string $id): JsonResponse
    {
        $user = auth()->user();
        if (!$user || !in_array($user->role->name, ['admin', 'super_admin'])) {
            return response()->json(['error' => 'Unauthorized.'], 403);
        }

        $organization = Organization::find($id);
        if (!$organization) {
            return response()->json([
                'success' => false,
                'message' => 'Organization not found.',
            ], 404);
        }

        $organization->delete();

        return response()->json([
            'success' => true,
            'message' => 'Organization deleted successfully.',
        ], 200);
    }

    /**
     * Restore a soft-deleted organization (admin only).
     * PUT /api/organizations/{id}/restore
     */
    public function restore(string $id): JsonResponse
    {
        $user = auth()->user();
        if (!$user || !in_array($user->role->name, ['admin', 'super_admin'])) {
            return response()->json(['error' => 'Unauthorized.'], 403);
        }

        $organization = Organization::withTrashed()->find($id);
        if (!$organization) {
            return response()->json([
                'success' => false,
                'message' => 'Organization not found.',
            ], 404);
        }

        $organization->restore();

        return response()->json([
            'success' => true,
            'message' => 'Organization restored successfully.',
            'data'    => $organization->fresh(),
        ], 200);
    }
}
