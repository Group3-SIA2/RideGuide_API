<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\CommuterRideRequest;
use App\Models\RideRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class TransactionHistoryController extends Controller
{
    public function index(Request $request): JsonResponse
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

        $activeRole = strtolower((string) ($request->attributes->get('active_role') ?? ''));
        $limit = (int) ($validated['limit'] ?? 50);

        $transactions = match ($activeRole) {
            'driver' => $this->driverTransactions($user->id, $limit),
            'commuter' => $this->commuterTransactions($user->id, $limit),
            'organization' => $this->organizationTransactions($user->id, $limit),
            default => collect(),
        };

        return response()->json([
            'success' => true,
            'data' => [
                'role' => $activeRole,
                'transactions' => $transactions->values(),
            ],
        ]);
    }

    private function driverTransactions(string $userId, int $limit)
    {
        return RideRequest::query()
            ->where('driver_id', $userId)
            ->with(['commuterRideRequest.terminal:id,terminal_name,latitude,longitude'])
            ->latest('created_at')
            ->limit($limit)
            ->get()
            ->map(fn (RideRequest $item) => [
                'id' => $item->id,
                'transaction_type' => 'ride_request',
                'status' => $item->status,
                'responded_at' => optional($item->responded_at)?->toIso8601String(),
                'created_at' => optional($item->created_at)?->toIso8601String(),
                'commuter_ride_request_id' => $item->commuter_ride_request_id,
                'destination' => $item->commuterRideRequest?->destination,
                'terminal' => $item->commuterRideRequest?->terminal ? [
                    'id' => $item->commuterRideRequest->terminal->id,
                    'name' => $item->commuterRideRequest->terminal->terminal_name,
                    'latitude' => $item->commuterRideRequest->terminal->latitude,
                    'longitude' => $item->commuterRideRequest->terminal->longitude,
                ] : null,
            ]);
    }

    private function commuterTransactions(string $userId, int $limit)
    {
        return CommuterRideRequest::query()
            ->where('commuter_id', $userId)
            ->with(['terminal:id,terminal_name,latitude,longitude'])
            ->withCount('rideRequests')
            ->latest('created_at')
            ->limit($limit)
            ->get()
            ->map(fn (CommuterRideRequest $item) => [
                'id' => $item->id,
                'transaction_type' => 'commuter_ride_request',
                'status' => $item->status,
                'expires_at' => optional($item->expires_at)?->toIso8601String(),
                'created_at' => optional($item->created_at)?->toIso8601String(),
                'destination' => $item->destination,
                'driver_response_count' => $item->ride_requests_count,
                'terminal' => $item->terminal ? [
                    'id' => $item->terminal->id,
                    'name' => $item->terminal->terminal_name,
                    'latitude' => $item->terminal->latitude,
                    'longitude' => $item->terminal->longitude,
                ] : null,
            ]);
    }

    private function organizationTransactions(string $userId, int $limit)
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
            return collect();
        }

        $driverUserIds = DB::table('driver')
            ->whereIn('organization_id', $organizationIds)
            ->pluck('user_id')
            ->unique()
            ->values();

        if ($driverUserIds->isEmpty()) {
            return collect();
        }

        return RideRequest::query()
            ->whereIn('driver_id', $driverUserIds)
            ->with(['driver:id,first_name,last_name', 'commuterRideRequest.terminal:id,terminal_name,latitude,longitude'])
            ->latest('created_at')
            ->limit($limit)
            ->get()
            ->map(fn (RideRequest $item) => [
                'id' => $item->id,
                'transaction_type' => 'organization_driver_ride_request',
                'status' => $item->status,
                'responded_at' => optional($item->responded_at)?->toIso8601String(),
                'created_at' => optional($item->created_at)?->toIso8601String(),
                'driver' => $item->driver ? [
                    'id' => $item->driver->id,
                    'first_name' => $item->driver->first_name,
                    'last_name' => $item->driver->last_name,
                ] : null,
                'destination' => $item->commuterRideRequest?->destination,
                'terminal' => $item->commuterRideRequest?->terminal ? [
                    'id' => $item->commuterRideRequest->terminal->id,
                    'name' => $item->commuterRideRequest->terminal->terminal_name,
                    'latitude' => $item->commuterRideRequest->terminal->latitude,
                    'longitude' => $item->commuterRideRequest->terminal->longitude,
                ] : null,
            ]);
    }
}
