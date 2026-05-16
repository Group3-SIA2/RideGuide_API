<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Trip;
use App\Models\TripPassenger;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class CommuterTripController extends Controller
{
    /*
     * GET /api/commuter/trips
     * List all trips the authenticated commuter was a passenger on (paginated).
     * Optional query param: ?status=active|completed
     * Authorization: Commuter-only
     */
    public function listMyTrips(Request $request): JsonResponse
    {
        $user = $request->user();

        $commuter = $user->commuter;
        if (! $commuter) {
            return response()->json([
                'success' => false,
                'message' => 'Commuter profile not found.',
            ], 403);
        }

        $validated = $request->validate([
            'status'   => ['nullable', 'in:active,completed'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);

        $query = Trip::whereHas('passengers', function ($q) use ($commuter) {
            $q->where('commuter_id', $commuter->id);
        })->with([
            'passengers' => function ($q) use ($commuter) {
                $q->where('commuter_id', $commuter->id)
                  ->with(['passengerStart.waypoint', 'passengerStop.waypoint']);
            },
            'driver.user:id,first_name,last_name',
        ]);

        if (isset($validated['status'])) {
            if ($validated['status'] === 'active') {
                $query->whereNull('return_time');
            } else {
                $query->whereNotNull('return_time');
            }
        }

        $trips = $query->latest('departure_time')
            ->paginate((int) ($validated['per_page'] ?? 20));

        return response()->json([
            'success' => true,
            'data'    => [
                'trips'      => $trips->map(fn (Trip $trip) => $this->formatTripForCommuter($trip, $commuter->id))->values(),
                'pagination' => [
                    'total'        => $trips->total(),
                    'per_page'     => $trips->perPage(),
                    'current_page' => $trips->currentPage(),
                    'last_page'    => $trips->lastPage(),
                ],
            ],
        ]);
    }

    /*
     * GET /api/commuter/trips/current
     * Get the commuter's current active trip (most recent, return_time is null).
     * Authorization: Commuter-only
     */
    public function getCurrentTrip(Request $request): JsonResponse
    {
        $user = $request->user();

        $commuter = $user->commuter;
        if (! $commuter) {
            return response()->json([
                'success' => false,
                'message' => 'Commuter profile not found.',
            ], 403);
        }

        $trip = Trip::whereHas('passengers', function ($q) use ($commuter) {
            $q->where('commuter_id', $commuter->id);
        })
            ->whereNull('return_time')
            ->with([
                'passengers' => function ($q) use ($commuter) {
                    $q->where('commuter_id', $commuter->id)
                      ->with(['passengerStart.waypoint', 'passengerStop.waypoint']);
                },
                'driver.user:id,first_name,last_name',
            ])
            ->latest('departure_time')
            ->first();

        if (! $trip) {
            return response()->json([
                'success' => false,
                'message' => 'No active trip found.',
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data'    => $this->formatTripForCommuter($trip, $commuter->id),
        ]);
    }

    /*
     * GET /api/commuter/trips/{id}
     * Get the details of a specific trip.
     * Authorization: Commuter-only (must be a passenger on this trip)
     */
    public function showTrip(Request $request, string $id): JsonResponse
    {
        $user = $request->user();

        $commuter = $user->commuter;
        if (! $commuter) {
            return response()->json([
                'success' => false,
                'message' => 'Commuter profile not found.',
            ], 403);
        }

        $trip = Trip::with([
            'passengers' => function ($q) use ($commuter) {
                $q->where('commuter_id', $commuter->id)
                  ->with(['passengerStart.waypoint', 'passengerStop.waypoint']);
            },
            'driver.user:id,first_name,last_name',
        ])->find($id);

        if (! $trip) {
            return response()->json([
                'success' => false,
                'message' => 'Trip not found.',
            ], 404);
        }

        $isPassenger = DB::table('trip_passengers')
            ->where('trip_id', $trip->id)
            ->where('commuter_id', $commuter->id)
            ->whereNull('deleted_at')
            ->exists();

        if (! $isPassenger) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized. You are not a passenger on this trip.',
            ], 403);
        }

        return response()->json([
            'success' => true,
            'data'    => $this->formatTripForCommuter($trip, $commuter->id),
        ]);
    }

    private function formatTripForCommuter(Trip $trip, string $commuterId): array
    {
        $myPassengerRecord = $trip->passengers->first();

        return [
            'id'             => $trip->id,
            'departure_time' => optional($trip->departure_time)->toIso8601String(),
            'return_time'    => optional($trip->return_time)->toIso8601String(),
            'status'         => is_null($trip->return_time) ? 'active' : 'completed',
            'created_at'     => optional($trip->created_at)->toIso8601String(),
            'driver'         => $trip->driver?->user ? [
                'id'         => $trip->driver->user->id,
                'first_name' => $trip->driver->user->first_name,
                'last_name'  => $trip->driver->user->last_name,
            ] : null,
            'my_fare'        => $myPassengerRecord ? (float) $myPassengerRecord->fare : null,
            'my_start'       => $myPassengerRecord?->passengerStart?->waypoint ? [
                'id'        => $myPassengerRecord->passengerStart->id,
                'latitude'  => (float) $myPassengerRecord->passengerStart->waypoint->latitude,
                'longitude' => (float) $myPassengerRecord->passengerStart->waypoint->longitude,
            ] : null,
            'my_stop'        => $myPassengerRecord?->passengerStop?->waypoint ? [
                'id'        => $myPassengerRecord->passengerStop->id,
                'latitude'  => (float) $myPassengerRecord->passengerStop->waypoint->latitude,
                'longitude' => (float) $myPassengerRecord->passengerStop->waypoint->longitude,
            ] : null,
        ];
    }
}
