<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\CommuterRideRequest;
use App\Models\RideRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class RideRequestController extends Controller
{
    /**
     * POST /api/commuter/ride-requests
     * Commuter creates a ride request
     * Authorization: Authenticated user
     */
    public function createRideRequest(Request $request): JsonResponse
    {
        // Verify authenticated user
        $user = auth()->user();
        if (!$user) {
            return response()->json(['error' => 'Unauthenticated.'], 401);
        }

        // Validate input
        $validated = $request->validate([
            'route_id'          => 'nullable|uuid|exists:routes,id',
            'terminal_id'       => 'nullable|uuid|exists:terminals,id',
            'destination'       => 'required_without_all:route_id,terminal_id|string|max:255',
            'pickup_latitude'   => 'nullable|numeric|between:-90,90',
            'pickup_longitude'  => 'nullable|numeric|between:-180,180',
            'dropoff_latitude'  => 'nullable|numeric|between:-90,90',
            'dropoff_longitude' => 'nullable|numeric|between:-180,180',
        ]);

        // Check: Commuter can only have 1 active request at a time
        $activeRequest = CommuterRideRequest::where('commuter_id', $user->id)
            ->where('status', 'active')
            ->notExpired()
            ->first();

        if ($activeRequest) {
            return response()->json([
                'error' => 'You already have an active ride request.',
                'existing_request_id' => $activeRequest->id,
            ], 409);
        }

        // Create new ride request with 10-minute expiry
        $rideRequest = CommuterRideRequest::create([
            'commuter_id'       => $user->id,
            'route_id'          => $validated['route_id'] ?? null,
            'terminal_id'       => $validated['terminal_id'] ?? null,
            'destination'       => $validated['destination'],
            'pickup_latitude'   => $validated['pickup_latitude'] ?? null,
            'pickup_longitude'  => $validated['pickup_longitude'] ?? null,
            'dropoff_latitude'  => $validated['dropoff_latitude'] ?? null,
            'dropoff_longitude' => $validated['dropoff_longitude'] ?? null,
            'status'            => 'active',
            'expires_at'        => now()->addMinutes(10),
        ]);

        return response()->json([
            'id'               => $rideRequest->id,
            'commuter_id'      => $rideRequest->commuter_id,
            'route_id'         => $rideRequest->route_id,
            'terminal_id'      => $rideRequest->terminal_id,
            'destination'      => $rideRequest->destination,
            'pickup_latitude'  => $rideRequest->pickup_latitude,
            'pickup_longitude' => $rideRequest->pickup_longitude,
            'dropoff_latitude' => $rideRequest->dropoff_latitude,
            'dropoff_longitude'=> $rideRequest->dropoff_longitude,
            'status'           => $rideRequest->status,
            'expires_at'       => $rideRequest->expires_at,
        ], 201);
    }

    /**
     * GET /api/commuter/ride-requests
     * Commuter gets their active ride requests
     * Authorization: Authenticated user
     */
    public function listRideRequests(Request $request): JsonResponse
    {
        // Verify authenticated user
        $user = auth()->user();
        if (!$user) {
            return response()->json(['error' => 'Unauthenticated.'], 401);
        }

        // Optional status filter
        $validated = $request->validate([
            'status' => 'nullable|in:active,accepted,completed,cancelled',
        ]);

         $query = CommuterRideRequest::where('commuter_id', $user->id)
             ->visibleToCommuter();

         if ($request->has('status')) {
             $query->where('status', $validated['status']);
         }

         $requests = $query->with(['rideRequests.driver:id,first_name,last_name'])->get();

        return response()->json($requests->map(function ($request) {
            return [
                'id' => $request->id,
                'destination' => $request->destination,
                'route_id' => $request->route_id,
                'terminal_id' => $request->terminal_id,
                'status' => $request->status,
                'expires_at' => $request->expires_at,
                'driver_responses' => $request->rideRequests->map(function ($response) {
                    return [
                        'driver_id' => $response->driver_id,
                        'driver_name' => $response->driver
                            ? trim(($response->driver->first_name ?? '').' '.($response->driver->last_name ?? ''))
                            : null,
                        'status' => $response->status,
                        'responded_at' => $response->responded_at,
                    ];
                })->values(),
            ];
        })->values());
    }

    /**
     * PUT /api/commuter/ride-requests/{id}
     * Commuter accepts or rejects a driver's response
     * Authorization: Authenticated user (must own the request)
     */
    public function updateRideRequestResponse(Request $request, string $id): JsonResponse
    {
        // Verify authenticated user
        $user = auth()->user();
        if (!$user) {
            return response()->json(['error' => 'Unauthenticated.'], 401);
        }

        // Find the commuter ride request
        $commuterRequest = CommuterRideRequest::find($id);
        if (!$commuterRequest) {
            return response()->json(['error' => 'Ride request not found.'], 404);
        }

        // Verify ownership
        if ($commuterRequest->commuter_id !== $user->id) {
            return response()->json(['error' => 'Unauthorized.'], 403);
        }

        // Validate input
        $validated = $request->validate([
            'status' => 'required|in:accepted,rejected,cancelled',
        ]);

        $updates = ['status' => $validated['status']];
        if ($validated['status'] === 'cancelled') {
            $updates['expires_at'] = now();
        }

        // Update commuter's overall response status
        $commuterRequest->update($updates);

        return response()->json([
            'id' => $commuterRequest->id,
            'status' => $commuterRequest->status,
            'responded_at' => $commuterRequest->updated_at,
            'ride_request_id' => $commuterRequest->id,
        ]);
    }
}
