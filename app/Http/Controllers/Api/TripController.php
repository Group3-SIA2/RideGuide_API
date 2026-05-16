<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Commuter;
use App\Models\Organization;
use App\Models\OrganizationFareRate;
use App\Models\PassengerStart;
use App\Models\PassengerStop;
use App\Models\Terminal;
use App\Models\Trip;
use App\Models\TripPassenger;
use App\Models\TripWaypoint;
use App\Models\Waypoint;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class TripController extends Controller
{
    /** Stops closer than this (meters) are treated as duplicates. */
    private const DUPLICATE_WAYPOINT_METERS = 80;

    /*
     * POST /api/trips
     * Driver starts a new trip.
     * Authorization: Driver-only
     */
    public function startTrip(Request $request): JsonResponse
    {
        $user = $request->user();

        $driver = $user->driver;
        if (! $driver) {
            return response()->json([
                'success' => false,
                'message' => 'Driver profile not found.',
            ], 403);
        }

        $activeTrip = Trip::where('driver_id', $driver->id)
            ->active()
            ->first();

        if ($activeTrip) {
            return response()->json([
                'success' => false,
                'message' => 'You already have an active trip.',
                'data' => ['existing_trip_id' => $activeTrip->id],
            ], 409);
        }

        // Unassigned drivers may supply optional waypoints at trip start
        $validated = $request->validate([
            'waypoints'             => ['nullable', 'array', 'max:50'],
            'waypoints.*.latitude'  => ['required_with:waypoints', 'numeric', 'between:-90,90'],
            'waypoints.*.longitude' => ['required_with:waypoints', 'numeric', 'between:-180,180'],
        ]);

        $trip = Trip::create([
            'driver_id'      => $driver->id,
            'departure_time' => now(),
            'return_time'    => null,
        ]);

        if ($driver->organization_id) {
            $this->seedOrganizationWaypoints($trip, $driver);
        } elseif (! empty($validated['waypoints'])) {
            $seq           = 0;
            $acceptedStops = [];
            foreach ($validated['waypoints'] as $wp) {
                $lat = (float) $wp['latitude'];
                $lng = (float) $wp['longitude'];
                if ($this->coordinatesNearAny($lat, $lng, $acceptedStops)) {
                    continue;
                }
                $acceptedStops[] = ['latitude' => $lat, 'longitude' => $lng];
                $waypoint        = Waypoint::create([
                    'latitude'  => (string) $lat,
                    'longitude' => (string) $lng,
                ]);
                TripWaypoint::create([
                    'trip_id'     => $trip->id,
                    'waypoint_id' => $waypoint->id,
                    'sequence'    => $seq,
                ]);
                $seq++;
            }
        }

        $trip->load('waypoints.waypoint');

        return response()->json([
            'success' => true,
            'message' => 'Trip started successfully.',
            'data'    => [
                'id'             => $trip->id,
                'driver_id'      => $trip->driver_id,
                'departure_time' => optional($trip->departure_time)->toIso8601String(),
                'return_time'    => null,
                'created_at'     => optional($trip->created_at)->toIso8601String(),
                'waypoints'      => $trip->waypoints
                    ->map(fn (TripWaypoint $w) => $this->formatWaypoint($w))
                    ->values(),
            ],
        ], 201);
    }

    /*
     * PATCH /api/trips/{id}/end
     * Driver ends an active trip.
     * Authorization: Driver-only
     */
    public function endTrip(Request $request, string $id): JsonResponse
    {
        $user = $request->user();

        $driver = $user->driver;
        if (! $driver) {
            return response()->json([
                'success' => false,
                'message' => 'Driver profile not found.',
            ], 403);
        }

        $trip = Trip::find($id);
        if (! $trip) {
            return response()->json([
                'success' => false,
                'message' => 'Trip not found.',
            ], 404);
        }

        if ($trip->driver_id !== $driver->id) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized. This trip does not belong to you.',
            ], 403);
        }

        if (! is_null($trip->return_time)) {
            return response()->json([
                'success' => false,
                'message' => 'Trip is already ended.',
            ], 409);
        }

        $trip->update(['return_time' => now()]);

        return response()->json([
            'success' => true,
            'message' => 'Trip ended successfully.',
            'data'    => [
                'id'             => $trip->id,
                'driver_id'      => $trip->driver_id,
                'departure_time' => $trip->departure_time,
                'return_time'    => $trip->return_time,
                'updated_at'     => $trip->updated_at,
            ],
        ]);
    }

    /*
     * POST /api/trips/{id}/passengers
     * Driver adds a commuter as a passenger to the trip.
     * Creates Waypoint → PassengerStart and Waypoint → PassengerStop records,
     * calculates fare using the driver's organization fare rate, then
     * creates the TripPassenger record.
     * Authorization: Driver-only
     */
    public function addPassenger(Request $request, string $id): JsonResponse
    {
        $user = $request->user();

        $driver = $user->driver;
        if (! $driver) {
            return response()->json([
                'success' => false,
                'message' => 'Driver profile not found.',
            ], 403);
        }

        $trip = Trip::find($id);
        if (! $trip) {
            return response()->json([
                'success' => false,
                'message' => 'Trip not found.',
            ], 404);
        }

        if ($trip->driver_id !== $driver->id) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized. This trip does not belong to you.',
            ], 403);
        }

        if (! is_null($trip->return_time)) {
            return response()->json([
                'success' => false,
                'message' => 'Cannot add passengers to an ended trip.',
            ], 409);
        }

        $validated = $request->validate([
            'commuter_id'       => ['required', 'uuid', 'exists:commuter,id'],
            'start_latitude'    => ['required', 'numeric', 'between:-90,90'],
            'start_longitude'   => ['required', 'numeric', 'between:-180,180'],
            'stop_latitude'     => ['required', 'numeric', 'between:-90,90'],
            'stop_longitude'    => ['required', 'numeric', 'between:-180,180'],
        ]);

        $alreadyOnboard = TripPassenger::where('trip_id', $trip->id)
            ->where('commuter_id', $validated['commuter_id'])
            ->whereNull('deleted_at')
            ->exists();

        if ($alreadyOnboard) {
            return response()->json([
                'success' => false,
                'message' => 'This commuter is already a passenger on this trip.',
            ], 409);
        }

        $fare = $this->computeFare(
            $driver,
            (float) $validated['start_latitude'],
            (float) $validated['start_longitude'],
            (float) $validated['stop_latitude'],
            (float) $validated['stop_longitude'],
            $validated['commuter_id']
        );

        if ($fare === null) {
            return response()->json([
                'success' => false,
                'message' => 'No fare rate configured for the driver\'s organization.',
            ], 422);
        }

        $tripPassenger = DB::transaction(function () use ($validated, $trip, $fare) {
            $startWaypoint = Waypoint::create([
                'latitude'  => (string) $validated['start_latitude'],
                'longitude' => (string) $validated['start_longitude'],
            ]);

            $passengerStart = PassengerStart::create([
                'waypoint_id' => $startWaypoint->id,
            ]);

            $stopWaypoint = Waypoint::create([
                'latitude'  => (string) $validated['stop_latitude'],
                'longitude' => (string) $validated['stop_longitude'],
            ]);

            $passengerStop = PassengerStop::create([
                'waypoint_id' => $stopWaypoint->id,
            ]);

            return TripPassenger::create([
                'commuter_id'        => $validated['commuter_id'],
                'trip_id'            => $trip->id,
                'passenger_start_id' => $passengerStart->id,
                'passenger_stop_id'  => $passengerStop->id,
                'fare'               => $fare,
            ]);
        });

        $tripPassenger->load([
            'commuter:id,first_name,last_name',
            'passengerStart.waypoint',
            'passengerStop.waypoint',
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Passenger added successfully.',
            'data'    => $this->formatPassenger($tripPassenger),
        ], 201);
    }

    /*
     * DELETE /api/trips/{id}/passengers/{passengerId}
     * Driver removes a passenger (soft-delete) from the trip.
     * Authorization: Driver-only
     */
    public function removePassenger(Request $request, string $id, string $passengerId): JsonResponse
    {
        $user = $request->user();

        $driver = $user->driver;
        if (! $driver) {
            return response()->json([
                'success' => false,
                'message' => 'Driver profile not found.',
            ], 403);
        }

        $trip = Trip::find($id);
        if (! $trip) {
            return response()->json([
                'success' => false,
                'message' => 'Trip not found.',
            ], 404);
        }

        if ($trip->driver_id !== $driver->id) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized. This trip does not belong to you.',
            ], 403);
        }

        $tripPassenger = TripPassenger::where('id', $passengerId)
            ->where('trip_id', $trip->id)
            ->first();

        if (! $tripPassenger) {
            return response()->json([
                'success' => false,
                'message' => 'Passenger not found on this trip.',
            ], 404);
        }

        $tripPassenger->delete();

        return response()->json([
            'success' => true,
            'message' => 'Passenger removed from trip.',
        ]);
    }

    /*
     * POST /api/trips/{id}/waypoints
     * Add a waypoint to an active trip (unassigned drivers only).
     * Authorization: Driver-only
     */
    public function addWaypoint(Request $request, string $id): JsonResponse
    {
        $user = $request->user();

        $driver = $user->driver;
        if (! $driver) {
            return response()->json([
                'success' => false,
                'message' => 'Driver profile not found.',
            ], 403);
        }

        if ($driver->organization_id) {
            return response()->json([
                'success' => false,
                'message' => 'Waypoints for organization-assigned drivers are managed automatically.',
            ], 403);
        }

        $trip = Trip::find($id);
        if (! $trip) {
            return response()->json([
                'success' => false,
                'message' => 'Trip not found.',
            ], 404);
        }

        if ($trip->driver_id !== $driver->id) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized. This trip does not belong to you.',
            ], 403);
        }

        if (! is_null($trip->return_time)) {
            return response()->json([
                'success' => false,
                'message' => 'Cannot add waypoints to an ended trip.',
            ], 409);
        }

        $validated = $request->validate([
            'latitude'  => ['required', 'numeric', 'between:-90,90'],
            'longitude' => ['required', 'numeric', 'between:-180,180'],
            'sequence'  => ['nullable', 'integer', 'min:0'],
        ]);

        $maxSequence = TripWaypoint::where('trip_id', $trip->id)
            ->whereNull('deleted_at')
            ->max('sequence') ?? -1;

        $sequence = isset($validated['sequence']) ? $validated['sequence'] : ($maxSequence + 1);

        $lat = (float) $validated['latitude'];
        $lng = (float) $validated['longitude'];

        if ($this->tripHasWaypointNear($trip, $lat, $lng)) {
            return response()->json([
                'success' => false,
                'message' => 'This route stop is already on your trip (same or very nearby location).',
            ], 422);
        }

        $waypoint = Waypoint::create([
            'latitude'  => (string) $lat,
            'longitude' => (string) $lng,
        ]);

        $tripWaypoint = TripWaypoint::create([
            'trip_id'     => $trip->id,
            'waypoint_id' => $waypoint->id,
            'sequence'    => $sequence,
        ]);

        $tripWaypoint->load('waypoint');

        return response()->json([
            'success' => true,
            'message' => 'Waypoint added.',
            'data'    => $this->formatWaypoint($tripWaypoint),
        ], 201);
    }

    /*
     * DELETE /api/trips/{id}/waypoints/{waypointId}
     * Remove a waypoint from an active trip (unassigned drivers only).
     * Authorization: Driver-only
     */
    public function removeWaypoint(Request $request, string $id, string $waypointId): JsonResponse
    {
        $user = $request->user();

        $driver = $user->driver;
        if (! $driver) {
            return response()->json([
                'success' => false,
                'message' => 'Driver profile not found.',
            ], 403);
        }

        if ($driver->organization_id) {
            return response()->json([
                'success' => false,
                'message' => 'Waypoints for organization-assigned drivers are managed automatically.',
            ], 403);
        }

        $trip = Trip::find($id);
        if (! $trip) {
            return response()->json([
                'success' => false,
                'message' => 'Trip not found.',
            ], 404);
        }

        if ($trip->driver_id !== $driver->id) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized. This trip does not belong to you.',
            ], 403);
        }

        $tripWaypoint = TripWaypoint::where('id', $waypointId)
            ->where('trip_id', $trip->id)
            ->first();

        if (! $tripWaypoint) {
            return response()->json([
                'success' => false,
                'message' => 'Waypoint not found on this trip.',
            ], 404);
        }

        $tripWaypoint->delete();

        return response()->json([
            'success' => true,
            'message' => 'Waypoint removed from trip.',
        ]);
    }

    /*
     * GET /api/trips
     * List all trips for the authenticated driver (paginated).
     * Optional query param: ?status=active|completed
     * Authorization: Driver-only
     */
    public function listTrips(Request $request): JsonResponse
    {
        $user = $request->user();

        $driver = $user->driver;
        if (! $driver) {
            return response()->json([
                'success' => false,
                'message' => 'Driver profile not found.',
            ], 403);
        }

        $validated = $request->validate([
            'status'   => ['nullable', 'in:active,completed'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);

        $query = Trip::where('driver_id', $driver->id)
            ->withCount('passengers');

        if (isset($validated['status'])) {
            if ($validated['status'] === 'active') {
                $query->active();
            } else {
                $query->completed();
            }
        }

        $trips = $query->latest('departure_time')
            ->paginate((int) ($validated['per_page'] ?? 20));

        return response()->json([
            'success' => true,
            'data'    => [
                'trips'        => $trips->map(fn (Trip $trip) => [
                    'id'               => $trip->id,
                    'driver_id'        => $trip->driver_id,
                    'departure_time'   => optional($trip->departure_time)->toIso8601String(),
                    'return_time'      => optional($trip->return_time)->toIso8601String(),
                    'status'           => is_null($trip->return_time) ? 'active' : 'completed',
                    'passenger_count'  => $trip->passengers_count,
                    'created_at'       => optional($trip->created_at)->toIso8601String(),
                ])->values(),
                'pagination'   => [
                    'total'        => $trips->total(),
                    'per_page'     => $trips->perPage(),
                    'current_page' => $trips->currentPage(),
                    'last_page'    => $trips->lastPage(),
                ],
            ],
        ]);
    }

    /*
     * GET /api/trips/{id}
     * Get a specific trip with all passengers and their waypoints.
     * Authorization: Driver-only (must own the trip)
     */
    public function showTrip(Request $request, string $id): JsonResponse
    {
        $user = $request->user();

        $driver = $user->driver;
        if (! $driver) {
            return response()->json([
                'success' => false,
                'message' => 'Driver profile not found.',
            ], 403);
        }

        $trip = Trip::with([
            'passengers.commuter:id,first_name,last_name',
            'passengers.passengerStart.waypoint',
            'passengers.passengerStop.waypoint',
            'waypoints.waypoint',
        ])->find($id);

        if (! $trip) {
            return response()->json([
                'success' => false,
                'message' => 'Trip not found.',
            ], 404);
        }

        if ($trip->driver_id !== $driver->id) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized. This trip does not belong to you.',
            ], 403);
        }

        return response()->json([
            'success' => true,
            'data'    => [
                'id'             => $trip->id,
                'driver_id'      => $trip->driver_id,
                'departure_time' => optional($trip->departure_time)->toIso8601String(),
                'return_time'    => optional($trip->return_time)->toIso8601String(),
                'status'         => is_null($trip->return_time) ? 'active' : 'completed',
                'created_at'     => optional($trip->created_at)->toIso8601String(),
                'passengers'     => $trip->passengers->map(fn (TripPassenger $p) => $this->formatPassenger($p))->values(),
                'waypoints'      => $trip->waypoints->map(fn (TripWaypoint $w) => $this->formatWaypoint($w))->values(),
            ],
        ]);
    }

    private function formatPassenger(TripPassenger $passenger): array
    {
        return [
            'id'          => $passenger->id,
            'commuter_id' => $passenger->commuter_id,
            'commuter'    => $passenger->commuter ? [
                'id'         => $passenger->commuter->id,
                'first_name' => $passenger->commuter->first_name,
                'last_name'  => $passenger->commuter->last_name,
            ] : null,
            'fare'        => (float) $passenger->fare,
            'boarded_at'  => optional($passenger->created_at)->toIso8601String(),
            'start'       => $passenger->passengerStart?->waypoint ? [
                'id'        => $passenger->passengerStart->id,
                'latitude'  => (float) $passenger->passengerStart->waypoint->latitude,
                'longitude' => (float) $passenger->passengerStart->waypoint->longitude,
            ] : null,
            'stop'        => $passenger->passengerStop?->waypoint ? [
                'id'        => $passenger->passengerStop->id,
                'latitude'  => (float) $passenger->passengerStop->waypoint->latitude,
                'longitude' => (float) $passenger->passengerStop->waypoint->longitude,
            ] : null,
        ];
    }

    private function computeFare(
        $driver,
        float $startLat,
        float $startLng,
        float $stopLat,
        float $stopLng,
        string $commuterId
    ): ?float {
        if (! $driver->organization_id) {
            return null;
        }

        $fareRateLink = OrganizationFareRate::with('fareRate')
            ->where('organization_id', $driver->organization_id)
            ->latest('created_at')
            ->first();

        if (! $fareRateLink || ! $fareRateLink->fareRate) {
            return null;
        }

        $fareRate        = $fareRateLink->fareRate;
        $baseFare        = (float) $fareRate->base_fare_4KM;
        $perKmRate       = (float) $fareRate->per_km_rate;
        $routeStandardFare = (float) $fareRate->route_standard_fare;

        $terminalThresholdKm = 0.3;
        $terminals = Organization::find($driver->organization_id)
            ?->terminals()
            ->get(['latitude', 'longitude']);

        $isTerminalToTerminal = false;
        if ($terminals && $terminals->isNotEmpty()) {
            $startNear = $this->isNearAnyTerminal($startLat, $startLng, $terminals, $terminalThresholdKm);
            $stopNear  = $this->isNearAnyTerminal($stopLat, $stopLng, $terminals, $terminalThresholdKm);
            $isTerminalToTerminal = $startNear && $stopNear;
        }

        if ($isTerminalToTerminal) {
            $fare = $routeStandardFare;
        } else {
            $distance      = $this->haversineKm($startLat, $startLng, $stopLat, $stopLng);
            $excessDistance = max($distance - 4, 0);
            $fare = $baseFare + ($perKmRate * $excessDistance);
        }

        $commuter = Commuter::with('discount')->find($commuterId);
        if (
            $commuter &&
            $commuter->discount &&
            $commuter->discount->verification_status === \App\Models\Discount::VERIFICATION_VERIFIED
        ) {
            $fare *= 0.8;
        }

        return round($fare, 2);
    }

    private function isNearAnyTerminal(float $lat, float $lng, $terminals, float $thresholdKm): bool
    {
        foreach ($terminals as $terminal) {
            if ($terminal->latitude === null || $terminal->longitude === null) {
                continue;
            }
            $distance = $this->haversineKm($lat, $lng, (float) $terminal->latitude, (float) $terminal->longitude);
            if ($distance <= $thresholdKm) {
                return true;
            }
        }
        return false;
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

    private function formatWaypoint(TripWaypoint $tripWaypoint): array
    {
        return [
            'id'        => $tripWaypoint->id,
            'sequence'  => $tripWaypoint->sequence,
            'latitude'  => $tripWaypoint->waypoint ? (float) $tripWaypoint->waypoint->latitude : null,
            'longitude' => $tripWaypoint->waypoint ? (float) $tripWaypoint->waypoint->longitude : null,
        ];
    }

    private function seedOrganizationWaypoints(Trip $trip, $driver): void
    {
        $terminals = Organization::find($driver->organization_id)
            ?->terminals()
            ->orderBy('terminal_name')
            ->get(['id', 'latitude', 'longitude', 'terminal_name']);

        if (! $terminals || $terminals->isEmpty()) {
            return;
        }

        $seq            = 0;
        $acceptedStops  = [];
        foreach ($terminals as $terminal) {
            if ($terminal->latitude === null || $terminal->longitude === null) {
                continue;
            }

            $lat = (float) $terminal->latitude;
            $lng = (float) $terminal->longitude;
            if ($this->coordinatesNearAny($lat, $lng, $acceptedStops)) {
                continue;
            }
            $acceptedStops[] = ['latitude' => $lat, 'longitude' => $lng];

            $waypoint = Waypoint::create([
                'latitude'  => (string) $lat,
                'longitude' => (string) $lng,
            ]);

            TripWaypoint::create([
                'trip_id'     => $trip->id,
                'waypoint_id' => $waypoint->id,
                'sequence'    => $seq,
            ]);

            $seq++;
        }
    }

    private function tripHasWaypointNear(Trip $trip, float $lat, float $lng): bool
    {
        $trip->loadMissing('waypoints.waypoint');

        $points = [];
        foreach ($trip->waypoints as $tripWaypoint) {
            if ($tripWaypoint->waypoint === null) {
                continue;
            }
            $points[] = [
                'latitude'  => (float) $tripWaypoint->waypoint->latitude,
                'longitude' => (float) $tripWaypoint->waypoint->longitude,
            ];
        }

        return $this->coordinatesNearAny($lat, $lng, $points);
    }

    /**
     * @param  array<int, array{latitude: float, longitude: float}>  $points
     */
    private function coordinatesNearAny(float $lat, float $lng, array $points): bool
    {
        foreach ($points as $point) {
            if ($this->distanceMeters($lat, $lng, $point['latitude'], $point['longitude'])
                < self::DUPLICATE_WAYPOINT_METERS) {
                return true;
            }
        }

        return false;
    }

    private function distanceMeters(float $lat1, float $lon1, float $lat2, float $lon2): float
    {
        return $this->haversineKm($lat1, $lon1, $lat2, $lon2) * 1000;
    }
}
