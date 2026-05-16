<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Trip;
use App\Models\TripPassenger;
use App\Models\TripWaypoint;
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

    /*
     * GET /api/commuter/trips/nearby
     * Discover active trips whose route passes through the commuter's pickup and drop-off.
     * Required query params: start_lat, start_lng, stop_lat, stop_lng
     * Optional: radius_km (default 0.5)
     * Authorization: Commuter-only
     */
    public function findNearbyTrips(Request $request): JsonResponse
    {
        $commuter = $request->user()?->commuter;
        if (! $commuter) {
            return response()->json([
                'success' => false,
                'message' => 'Commuter profile not found.',
            ], 403);
        }

        $validated = $request->validate([
            'start_lat'  => ['required', 'numeric', 'between:-90,90'],
            'start_lng'  => ['required', 'numeric', 'between:-180,180'],
            'stop_lat'   => ['required', 'numeric', 'between:-90,90'],
            'stop_lng'   => ['required', 'numeric', 'between:-180,180'],
            'radius_km'  => ['nullable', 'numeric', 'min:0.1', 'max:10'],
        ]);

        $startLat  = (float) $validated['start_lat'];
        $startLng  = (float) $validated['start_lng'];
        $stopLat   = (float) $validated['stop_lat'];
        $stopLng   = (float) $validated['stop_lng'];
        $radiusKm  = (float) ($validated['radius_km'] ?? 0.5);

        $activeTrips = Trip::with([
            'waypoints.waypoint',
            'driver.user:id,first_name,last_name',
        ])->withCount('passengers')
            ->whereNull('return_time')
            ->get();

        $matched = [];

        foreach ($activeTrips as $trip) {
            $waypoints = $trip->waypoints;

            if ($waypoints->isEmpty()) {
                continue;
            }

            $startSeq = null;
            $stopSeq  = null;

            foreach ($waypoints as $tw) {
                if ($tw->waypoint === null) {
                    continue;
                }

                $wpLat = (float) $tw->waypoint->latitude;
                $wpLng = (float) $tw->waypoint->longitude;

                if ($startSeq === null && $this->haversineKm($startLat, $startLng, $wpLat, $wpLng) <= $radiusKm) {
                    $startSeq = $tw->sequence;
                }

                if ($this->haversineKm($stopLat, $stopLng, $wpLat, $wpLng) <= $radiusKm) {
                    if ($startSeq !== null && $tw->sequence > $startSeq) {
                        $stopSeq = $tw->sequence;
                        break;
                    }
                }
            }

            if ($startSeq !== null && $stopSeq !== null) {
                $matched[] = [
                    'id'              => $trip->id,
                    'departure_time'  => optional($trip->departure_time)->toIso8601String(),
                    'status'          => 'active',
                    'passenger_count' => $trip->passengers_count,
                    'driver'          => $trip->driver?->user ? [
                        'id'         => $trip->driver->user->id,
                        'first_name' => $trip->driver->user->first_name,
                        'last_name'  => $trip->driver->user->last_name,
                    ] : null,
                    'route_match' => [
                        'pickup_sequence' => $startSeq,
                        'dropoff_sequence' => $stopSeq,
                    ],
                    'waypoints' => $waypoints->map(fn (TripWaypoint $w) => [
                        'id'        => $w->id,
                        'sequence'  => $w->sequence,
                        'latitude'  => $w->waypoint ? (float) $w->waypoint->latitude : null,
                        'longitude' => $w->waypoint ? (float) $w->waypoint->longitude : null,
                    ])->values(),
                ];
            }
        }

        return response()->json([
            'success' => true,
            'data'    => [
                'trips'      => $matched,
                'total'      => count($matched),
                'radius_km'  => $radiusKm,
            ],
        ]);
    }

    private function haversineKm(float $lat1, float $lon1, float $lat2, float $lon2): float
    {
        $earthRadius = 6371;
        $dLat        = deg2rad($lat2 - $lat1);
        $dLon        = deg2rad($lon2 - $lon1);
        $a           = sin($dLat / 2) ** 2
            + cos(deg2rad($lat1)) * cos(deg2rad($lat2))
            * sin($dLon / 2) ** 2;
        $c           = 2 * atan2(sqrt($a), sqrt(1 - $a));
        return $earthRadius * $c;
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
