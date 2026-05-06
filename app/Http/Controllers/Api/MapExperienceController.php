<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\CommuterRideRequest;
use App\Models\DriverLocation;
use App\Models\RideRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class MapExperienceController extends Controller
{
    public function experience(Request $request): JsonResponse
    {
        $user = $request->user();
        if (! $user) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthenticated.',
            ], 401);
        }

        $role = strtolower((string) ($request->attributes->get('active_role') ?? ''));
        if (! in_array($role, ['driver', 'commuter', 'organization'], true)) {
            return response()->json([
                'success' => false,
                'message' => 'Role context is required.',
            ], 409);
        }

        return response()->json([
            'success' => true,
            'data' => [
                'active_role' => $role,
                'default_tab' => 'map',
                'force_zoom' => [
                    'enabled' => true,
                    'zoom' => 16,
                ],
                'top_right_controls' => [
                    'role_switch' => true,
                    'role_icon' => true,
                    'settings' => true,
                    'notifications' => true,
                ],
                'tabs' => $this->tabsForRole($role),
            ],
        ]);
    }

    public function overlays(Request $request): JsonResponse
    {
        $user = $request->user();
        if (! $user) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthenticated.',
            ], 401);
        }

        $validated = $request->validate([
            'limit' => ['nullable', 'integer', 'min:1', 'max:200'],
        ]);

        $role = strtolower((string) ($request->attributes->get('active_role') ?? ''));
        $limit = (int) ($validated['limit'] ?? 50);

        $data = match ($role) {
            'driver' => $this->driverOverlayData($limit),
            'commuter' => $this->commuterOverlayData($user->id, $limit),
            'organization' => $this->organizationOverlayData($user->id, $limit),
            default => [
                'driver_locations' => [],
                'active_commuter_requests' => [],
                'recent_transactions' => [],
            ],
        };

        return response()->json([
            'success' => true,
            'data' => [
                'role' => $role,
                ...$data,
            ],
        ]);
    }

    private function tabsForRole(string $role): array
    {
        return match ($role) {
            'organization' => ['map', 'terminals', 'assign', 'profile'],
            default => ['map', 'inquiry', 'history', 'profile'],
        };
    }

    private function driverOverlayData(int $limit): array
    {
        $activeRequests = CommuterRideRequest::query()
            ->where('status', 'active')
            ->notExpired()
            ->latest('created_at')
            ->limit($limit)
            ->get(['id', 'route_id', 'terminal_id', 'destination', 'created_at']);

        return [
            'driver_locations' => [],
            'active_commuter_requests' => $activeRequests,
            'recent_transactions' => [],
        ];
    }

    private function commuterOverlayData(string $userId, int $limit): array
    {
        $locations = DriverLocation::query()
            ->latest('updated_at')
            ->limit($limit)
            ->get(['id', 'driver_id', 'latitude', 'longitude', 'heading', 'accuracy', 'updated_at']);

        $recentTransactions = CommuterRideRequest::query()
            ->where('commuter_id', $userId)
            ->latest('created_at')
            ->limit($limit)
            ->get(['id', 'destination', 'status', 'created_at', 'expires_at']);

        return [
            'driver_locations' => $locations,
            'active_commuter_requests' => [],
            'recent_transactions' => $recentTransactions,
        ];
    }

    private function organizationOverlayData(string $userId, int $limit): array
    {
        $organizationIds = DB::table('organizations')
            ->where('owner_user_id', $userId)
            ->pluck('id')
            ->merge(
                DB::table('organization_user_role')
                    ->where('user_id', $userId)
                    ->where('status', 'active')
                    ->pluck('organization_id')
            )
            ->unique()
            ->values();

        if ($organizationIds->isEmpty()) {
            return [
                'driver_locations' => [],
                'active_commuter_requests' => [],
                'recent_transactions' => [],
            ];
        }

        $driverUserIds = DB::table('driver')
            ->whereIn('organization_id', $organizationIds)
            ->pluck('user_id')
            ->unique()
            ->values();

        if ($driverUserIds->isEmpty()) {
            return [
                'driver_locations' => [],
                'active_commuter_requests' => [],
                'recent_transactions' => [],
            ];
        }

        $locations = DriverLocation::query()
            ->whereIn('driver_id', $driverUserIds)
            ->latest('updated_at')
            ->limit($limit)
            ->get(['id', 'driver_id', 'latitude', 'longitude', 'heading', 'accuracy', 'updated_at']);

        $transactions = RideRequest::query()
            ->whereIn('driver_id', $driverUserIds)
            ->latest('created_at')
            ->limit($limit)
            ->get(['id', 'driver_id', 'commuter_ride_request_id', 'status', 'responded_at', 'created_at']);

        return [
            'driver_locations' => $locations,
            'active_commuter_requests' => [],
            'recent_transactions' => $transactions,
        ];
    }
}
