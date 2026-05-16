<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\DriverLocation;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DriverLocationController extends Controller
{
    /**
     * POST /api/drivers/location
     * Driver updates their current location. Client throttles POSTs (~20s / 30m); route uses `throttle:live-location-post`.
     * Authorization: Driver-only
     */
     public function updateLocation(Request $request): JsonResponse
     {
         $user = auth()->user();

         // Safety check: Ensure user exists in database (should always be true for authenticated user)
        if (!$user->exists) {
            return response()->json(['error' => 'User not found.'], 404);
        }

        // Validate input
        $validated = $request->validate([
            'latitude' => 'required|numeric|between:-90,90',
            'longitude' => 'required|numeric|between:-180,180',
            'heading' => 'nullable|numeric|between:0,360',
            'accuracy' => 'nullable|numeric|min:0',
        ]);

        // Upsert: Update if exists, create if doesn't
        $location = DriverLocation::where('driver_id', $user->id)->first();
        
        if ($location) {
            $location->update([
                'latitude' => $validated['latitude'],
                'longitude' => $validated['longitude'],
                'heading' => $validated['heading'] ?? null,
                'accuracy' => $validated['accuracy'] ?? null,
                'updated_at' => now(),
            ]);
        } else {
            $location = DriverLocation::create([
                'driver_id' => $user->id,
                'latitude' => $validated['latitude'],
                'longitude' => $validated['longitude'],
                'heading' => $validated['heading'] ?? null,
                'accuracy' => $validated['accuracy'] ?? null,
                'updated_at' => now(),
            ]);
        }
        
        $driverLocation = $location;

        return response()->json([
            'id' => $driverLocation->id,
            'driver_id' => $driverLocation->driver_id,
            'latitude' => $driverLocation->latitude,
            'longitude' => $driverLocation->longitude,
            'heading' => $driverLocation->heading,
            'accuracy' => $driverLocation->accuracy,
            'updated_at' => $driverLocation->updated_at,
        ], 201);
    }

    /**
     * DELETE /api/drivers/location
     * Remove shared driver position when the driver turns off live sharing.
     */
    public function clearLocation(Request $request): JsonResponse
    {
        $user = $request->user();

        DriverLocation::query()->where('driver_id', $user->id)->delete();

        return response()->json([
            'success' => true,
            'message' => 'Driver location sharing stopped.',
        ]);
    }

    /**
     * GET /api/drivers/location
     * Get authenticated driver's current location
     * Authorization: Driver-only
     */
     public function getLocation(Request $request): JsonResponse
     {
         $user = auth()->user();

         // Get driver's current location
        $driverLocation = DriverLocation::where('driver_id', $user->id)->first();

        if (!$driverLocation) {
            return response()->json(['error' => 'Location not found. Please update your location first.'], 404);
        }

        return response()->json([
            'id' => $driverLocation->id,
            'driver_id' => $driverLocation->driver_id,
            'latitude' => $driverLocation->latitude,
            'longitude' => $driverLocation->longitude,
            'heading' => $driverLocation->heading,
            'accuracy' => $driverLocation->accuracy,
            'updated_at' => $driverLocation->updated_at,
        ]);
    }
}
