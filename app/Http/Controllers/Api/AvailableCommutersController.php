<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\CommuterRideRequest;
use App\Models\RideRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AvailableCommutersController extends Controller
{
    /**
     * GET /api/available-commuters
     * Return commuters with active ride requests visible to current driver
     * Authorization: Driver-only
     */
      public function getAvailableCommuters(Request $request): JsonResponse
      {
          // Verify required params
         $validated = $request->validate([
             'latitude' => 'required|numeric|between:5.5,6.5',
             'longitude' => 'required|numeric|between:124.7,125.7',
             'route_id' => 'nullable|uuid|exists:routes,id',
             'terminal_id' => 'nullable|uuid|exists:terminals,id',
             'radius_meters' => 'nullable|integer|min:100|max:50000',
         ]);

         $user = auth()->user();
         $driverLatitude = $validated['latitude'];
         $driverLongitude = $validated['longitude'];
         $radiusMeters = $validated['radius_meters'] ?? 5000;
         $radiusKm = $radiusMeters / 1000;

         // Get active, non-expired commuter requests
         $query = CommuterRideRequest::where('status', 'active')
            ->notExpired();

        // Optional filters
        if ($request->has('route_id')) {
            $query->where('route_id', $validated['route_id']);
        }
        if ($request->has('terminal_id')) {
            $query->where('terminal_id', $validated['terminal_id']);
        }

        // Get requests with commuter relationship
        $requests = $query->with('commuter', 'terminal')->get();

        // Map to response, excluding commuter personal info (privacy)
        $availableCommuters = $requests->map(function ($request) {
            return [
                'id' => $request->id,
                'current_location' => [
                    'lat' => $request->terminal?->latitude ?? null,
                    'lng' => $request->terminal?->longitude ?? null,
                ],
                'destination' => $request->destination,
                'route_id' => $request->route_id,
                'terminal_id' => $request->terminal_id,
                'wait_time_seconds' => $request->created_at->diffInSeconds(now()),
            ];
        })->values();

        return response()->json($availableCommuters);
    }

    /**
     * POST /api/available-commuters/respond
     * Driver responds to a commuter request
     * Authorization: Driver-only
     */
     public function respondToCommuter(Request $request): JsonResponse
     {
         $user = auth()->user();

         // Validate input
        $validated = $request->validate([
            'commuter_ride_request_id' => 'required|uuid|exists:commuter_ride_requests,id',
            'status' => 'required|in:accepted,rejected',
        ]);

        // Check if commuter request exists and is active
        $commuterRequest = CommuterRideRequest::find($validated['commuter_ride_request_id']);
        if (!$commuterRequest) {
            return response()->json(['error' => 'Commuter request not found.'], 404);
        }

        if ($commuterRequest->status !== 'active' || $commuterRequest->expires_at <= now()) {
            return response()->json(['error' => 'Request is no longer active.'], 400);
        }

        // Create RideRequest with driver response
        $rideRequest = RideRequest::create([
            'driver_id' => $user->id,
            'commuter_ride_request_id' => $commuterRequest->id,
            'status' => $validated['status'],
            'responded_at' => now(),
        ]);

        return response()->json([
            'id' => $rideRequest->id,
            'status' => $rideRequest->status,
            'responded_at' => $rideRequest->responded_at,
        ], 201);
    }
}
