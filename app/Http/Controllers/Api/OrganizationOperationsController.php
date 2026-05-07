<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Driver;
use App\Models\DriverOrganizationAssignmentLog;
use App\Models\Organization;
use App\Models\OrganizationTerminal;
use App\Models\Role;
use App\Models\Terminal;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class OrganizationOperationsController extends Controller
{
    public function listTerminals(Request $request): JsonResponse
    {
        $organization = $this->resolveOrganizationForUser($request);
        if (! $organization) {
            return response()->json(['success' => false, 'message' => 'Organization not found for this user.'], 404);
        }

        $terminals = OrganizationTerminal::query()
            ->with('terminal')
            ->where('organization_id', $organization->id)
            ->latest('created_at')
            ->get()
            ->map(function (OrganizationTerminal $organizationTerminal) {
                return [
                    'id' => $organizationTerminal->id,
                    'organization_id' => $organizationTerminal->organization_id,
                    'terminal' => $organizationTerminal->terminal,
                ];
            })->values();

        return response()->json(['success' => true, 'data' => $terminals], 200);
    }

    public function createTerminal(Request $request): JsonResponse
    {
        $organization = $this->resolveOrganizationForUser($request);
        if (! $organization) {
            return response()->json(['success' => false, 'message' => 'Organization not found for this user.'], 404);
        }

        $validated = $request->validate([
            'terminal_name' => ['required', 'string', 'max:255'],
            'barangay' => ['required', 'string', 'max:255'],
            'city' => ['required', 'string', 'max:255'],
            'latitude' => ['nullable', 'numeric', 'between:-90,90'],
            'longitude' => ['nullable', 'numeric', 'between:-180,180'],
        ]);

        $terminal = null;
        DB::transaction(function () use (&$terminal, $validated, $organization): void {
            $terminal = Terminal::query()->create($validated);
            OrganizationTerminal::query()->create([
                'organization_id' => $organization->id,
                'terminal_id' => $terminal->id,
            ]);
        });

        return response()->json([
            'success' => true,
            'message' => 'Terminal created and assigned to organization.',
            'data' => $terminal,
        ], 201);
    }

    public function updateTerminal(Request $request, string $terminalId): JsonResponse
    {
        $organization = $this->resolveOrganizationForUser($request);
        if (! $organization) {
            return response()->json(['success' => false, 'message' => 'Organization not found for this user.'], 404);
        }

        $validated = $request->validate([
            'terminal_name' => ['required', 'string', 'max:255'],
            'barangay' => ['required', 'string', 'max:255'],
            'city' => ['required', 'string', 'max:255'],
            'latitude' => ['nullable', 'numeric', 'between:-90,90'],
            'longitude' => ['nullable', 'numeric', 'between:-180,180'],
        ]);

        $terminal = Terminal::query()->find($terminalId);
        if (! $terminal) {
            return response()->json(['success' => false, 'message' => 'Terminal not found.'], 404);
        }

        $organizationTerminal = OrganizationTerminal::query()
            ->where('organization_id', $organization->id)
            ->where('terminal_id', $terminalId)
            ->first();
        if (! $organizationTerminal) {
            return response()->json(['success' => false, 'message' => 'Terminal is not assigned to your organization.'], 403);
        }

        $terminal->update($validated);

        return response()->json([
            'success' => true,
            'message' => 'Terminal updated successfully.',
            'data' => $terminal->fresh(),
        ], 200);
    }

    public function deleteTerminal(Request $request, string $terminalId): JsonResponse
    {
        $organization = $this->resolveOrganizationForUser($request);
        if (! $organization) {
            return response()->json(['success' => false, 'message' => 'Organization not found for this user.'], 404);
        }

        $organizationTerminal = OrganizationTerminal::query()
            ->where('organization_id', $organization->id)
            ->where('terminal_id', $terminalId)
            ->first();
        if (! $organizationTerminal) {
            return response()->json(['success' => false, 'message' => 'Terminal is not assigned to your organization.'], 404);
        }

        $organizationTerminal->delete();

        return response()->json([
            'success' => true,
            'message' => 'Terminal removed from organization.',
        ], 200);
    }

    public function listAssignableDrivers(Request $request): JsonResponse
    {
        $organization = $this->resolveOrganizationForUser($request);
        if (! $organization) {
            return response()->json(['success' => false, 'message' => 'Organization not found for this user.'], 404);
        }

        $drivers = Driver::query()
            ->with('user:id,first_name,last_name,email')
            ->latest('created_at')
            ->limit(200)
            ->get()
            ->map(function (Driver $driver) use ($organization) {
                return [
                    'id' => $driver->id,
                    'user_id' => $driver->user_id,
                    'organization_id' => $driver->organization_id,
                    'is_assigned_to_current_org' => $driver->organization_id !== null && $driver->organization_id === $organization->id,
                    'user' => $driver->user,
                ];
            })->values();

        return response()->json(['success' => true, 'data' => $drivers], 200);
    }

    public function assignDriver(Request $request): JsonResponse
    {
        $organization = $this->resolveOrganizationForUser($request);
        if (! $organization) {
            return response()->json(['success' => false, 'message' => 'Organization not found for this user.'], 404);
        }

        $validated = $request->validate([
            'driver_id' => ['required', 'uuid', 'exists:driver,id'],
        ]);

        $driver = Driver::query()->findOrFail($validated['driver_id']);
        $oldOrganizationId = $driver->organization_id;

        if ($oldOrganizationId === $organization->id) {
            return response()->json(['success' => true, 'message' => 'Driver already assigned to this organization.'], 200);
        }

        $driver->update(['organization_id' => $organization->id]);
        DriverOrganizationAssignmentLog::query()->create([
            'driver_id' => $driver->id,
            'old_organization_id' => $oldOrganizationId,
            'new_organization_id' => $organization->id,
            'acted_by_user_id' => $request->user()?->id,
            'action' => $oldOrganizationId ? DriverOrganizationAssignmentLog::ACTION_REASSIGN : DriverOrganizationAssignmentLog::ACTION_ASSIGN,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Driver assigned successfully.',
            'data' => [
                'driver_id' => $driver->id,
                'organization_id' => $organization->id,
            ],
        ], 200);
    }

    public function unassignDriver(Request $request, string $driverId): JsonResponse
    {
        $organization = $this->resolveOrganizationForUser($request);
        if (! $organization) {
            return response()->json(['success' => false, 'message' => 'Organization not found for this user.'], 404);
        }

        $driver = Driver::query()->find($driverId);
        if (! $driver) {
            return response()->json(['success' => false, 'message' => 'Driver not found.'], 404);
        }
        if ($driver->organization_id !== $organization->id) {
            return response()->json(['success' => false, 'message' => 'Driver is not assigned to your organization.'], 403);
        }

        $driver->update(['organization_id' => null]);
        DriverOrganizationAssignmentLog::query()->create([
            'driver_id' => $driver->id,
            'old_organization_id' => $organization->id,
            'new_organization_id' => null,
            'acted_by_user_id' => $request->user()?->id,
            'action' => DriverOrganizationAssignmentLog::ACTION_UNASSIGN,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Driver unassigned successfully.',
        ], 200);
    }

    private function resolveOrganizationForUser(Request $request): ?Organization
    {
        $user = $request->user();
        if (! $user) {
            return null;
        }
        if (! $user->hasRole(Role::ORGANIZATION) && ! $user->hasAnyActiveOrganizationManagement()) {
            return null;
        }

        return Organization::query()
            ->where(function ($organizationScope) use ($user) {
                $organizationScope->where('owner_user_id', $user->id)
                    ->orWhereHas('organizationUserRoles', function ($organizationUserRoleQuery) use ($user) {
                        $organizationUserRoleQuery->where('user_id', $user->id)
                            ->where('status', 'active');
                    });
            })
            ->first();
    }
}
