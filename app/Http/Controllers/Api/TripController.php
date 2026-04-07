<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Commuter;
use App\Models\Driver;
use App\Models\DriverAssignTerminal;
use App\Models\OrganizationFareRate;
use App\Models\PassengerStart;
use App\Models\PassengerStop;
use App\Models\Trip;
use App\Models\TripPassenger;
use App\Models\Waypoint;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class TripController extends Controller
{
    public function tripCreate(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'departure_time' => ['required', 'date'],
            'start_terminal_id' => ['required', 'uuid', 'exists:terminals,id'],
            'capacity' => ['required', 'integer', 'min:1'],
        ]);

        $driver = Driver::where('user_id', $request->user()->id)->first();
        if (! $driver) {
            return response()->json(['message' => 'Driver profile not found.'], 404);
        }

        $assigned = DriverAssignTerminal::where('driver_id', $driver->id)
            ->where('terminal_id', $validated['start_terminal_id'])
            ->exists();

        if (! $assigned) {
            return response()->json(['message' => 'Driver is not assigned to this terminal.'], 403);
        }

        $trip = Trip::create([
            'driver_id' => $driver->id,
            'departure_time' => $validated['departure_time'],
            'start_terminal_id' => $validated['start_terminal_id'],
            'capacity' => $validated['capacity'],
            'status' => 'scheduled',
        ]);

        return response()->json(['data' => $trip], 201);
    }

    public function commuterJoin(Request $request, Trip $trip): JsonResponse
    {
        $validated = $request->validate([
            'destination_terminal_id' => ['nullable', 'uuid', 'exists:terminals,id'],
            'destination_latitude' => ['nullable', 'numeric'],
            'destination_longitude' => ['nullable', 'numeric'],
        ]);

        if (! in_array($trip->status, ['scheduled', 'in_progress'], true)) {
            return response()->json(['message' => 'Trip is not joinable.'], 409);
        }

        return DB::transaction(function () use ($request, $trip, $validated) {
            $lockedTrip = Trip::query()->whereKey($trip->id)->lockForUpdate()->firstOrFail();
            $activePassengers = TripPassenger::query()
                ->where('trip_id', $lockedTrip->id)
                ->whereIn('status', ['joined', 'onboard'])
                ->count();

            if ($activePassengers >= $lockedTrip->capacity) {
                return response()->json(['message' => 'Trip is already full.'], 409);
            }

            $commuter = Commuter::where('user_id', $request->user()->id)->first();
            if (! $commuter) {
                return response()->json(['message' => 'Commuter profile not found.'], 404);
            }

            $tripPassenger = TripPassenger::create([
                'commuter_id' => $commuter->id,
                'trip_id' => $lockedTrip->id,
                'destination_terminal_id' => $validated['destination_terminal_id'] ?? null,
                'destination_latitude' => $validated['destination_latitude'] ?? null,
                'destination_longitude' => $validated['destination_longitude'] ?? null,
                'status' => 'joined',
                'joined_at' => now(),
            ]);

            return response()->json(['data' => $tripPassenger], 201);
        });
    }

    public function tripStart(Request $request, Trip $trip): JsonResponse
    {
        $request->validate([
            'latitude' => ['required', 'numeric'],
            'longitude' => ['required', 'numeric'],
        ]);

        $this->assertTripOwnedByAuthenticatedDriver($request, $trip);

        $waypoint = Waypoint::create([
            'latitude' => (string) $request->input('latitude'),
            'longitude' => (string) $request->input('longitude'),
        ]);

        $trip->update([
            'trip_start_waypoint_id' => $waypoint->id,
            'status' => 'in_progress',
        ]);

        return response()->json(['data' => $trip->fresh()], 200);
    }

    public function passengerPickup(Request $request, Trip $trip, TripPassenger $tripPassenger): JsonResponse
    {
        $request->validate([
            'latitude' => ['required', 'numeric'],
            'longitude' => ['required', 'numeric'],
        ]);

        $this->assertTripOwnedByAuthenticatedDriver($request, $trip);

        if ($tripPassenger->trip_id !== $trip->id) {
            return response()->json(['message' => 'Passenger does not belong to this trip.'], 422);
        }

        if ($tripPassenger->status !== 'joined') {
            return response()->json(['message' => 'Passenger is not in joined state.'], 422);
        }

        $waypoint = Waypoint::create([
            'latitude' => (string) $request->input('latitude'),
            'longitude' => (string) $request->input('longitude'),
        ]);

        $passengerStart = PassengerStart::create(['waypoint_id' => $waypoint->id]);

        $tripPassenger->update([
            'passenger_start_id' => $passengerStart->id,
            'status' => 'onboard',
            'picked_up_at' => now(),
        ]);

        return response()->json(['data' => $tripPassenger->fresh()], 200);
    }

    public function passengerStop(Request $request, Trip $trip, TripPassenger $tripPassenger): JsonResponse
    {
        $validated = $request->validate([
            'latitude' => ['required', 'numeric'],
            'longitude' => ['required', 'numeric'],
            'distance_km' => ['required', 'numeric', 'min:0'],
        ]);

        $this->assertTripOwnedByAuthenticatedDriver($request, $trip);

        if ($tripPassenger->trip_id !== $trip->id) {
            return response()->json(['message' => 'Passenger does not belong to this trip.'], 422);
        }

        if ($tripPassenger->status !== 'onboard') {
            return response()->json(['message' => 'Passenger must be onboard before drop-off.'], 422);
        }

        $waypoint = Waypoint::create([
            'latitude' => (string) $validated['latitude'],
            'longitude' => (string) $validated['longitude'],
        ]);

        $passengerStop = PassengerStop::create(['waypoint_id' => $waypoint->id]);
        [$fare, $fareType] = $this->finalizeFare($trip, $tripPassenger, (float) $validated['distance_km']);

        $tripPassenger->update([
            'passenger_stop_id' => $passengerStop->id,
            'status' => 'dropped_off',
            'dropped_off_at' => now(),
            'fare' => $fare,
        ]);

        return response()->json([
            'data' => [
                'id' => $tripPassenger->id,
                'status' => 'dropped_off',
                'fare' => $fare,
                'fare_type' => $fareType,
            ],
        ], 200);
    }

    public function tripEnd(Request $request, Trip $trip): JsonResponse
    {
        $validated = $request->validate([
            'latitude' => ['required', 'numeric'],
            'longitude' => ['required', 'numeric'],
        ]);

        $this->assertTripOwnedByAuthenticatedDriver($request, $trip);

        $activeCount = TripPassenger::query()
            ->where('trip_id', $trip->id)
            ->where('status', 'onboard')
            ->count();

        if ($activeCount > 0) {
            return response()->json([
                'message' => 'Cannot end trip while passengers are still onboard.',
            ], 422);
        }

        $waypoint = Waypoint::create([
            'latitude' => (string) $validated['latitude'],
            'longitude' => (string) $validated['longitude'],
        ]);

        $trip->update([
            'trip_end_waypoint_id' => $waypoint->id,
            'status' => 'completed',
            'return_time' => now(),
        ]);

        return response()->json(['data' => $trip->fresh()], 200);
    }

    private function assertTripOwnedByAuthenticatedDriver(Request $request, Trip $trip): Driver
    {
        $driver = Driver::where('user_id', $request->user()->id)->first();

        if (! $driver) {
            abort(404, 'Driver profile not found.');
        }

        if ($trip->driver_id !== $driver->id) {
            abort(403, 'You do not own this trip.');
        }

        return $driver;
    }

    private function finalizeFare(Trip $trip, TripPassenger $tripPassenger, float $distanceKm): array
    {
        $driver = Driver::query()->findOrFail($trip->driver_id);
        $fareRateLink = OrganizationFareRate::with('fareRate')
            ->where('organization_id', $driver->organization_id)
            ->latest('created_at')
            ->firstOrFail();

        $fareRate = $fareRateLink->fareRate;
        $isTerminalToTerminal = $tripPassenger->destination_terminal_id !== null
            && $trip->start_terminal_id !== null;

        if ($isTerminalToTerminal) {
            return [(float) $fareRate->route_standard_fare, 'terminal_to_terminal'];
        }

        $base = (float) $fareRate->base_fare_4KM;
        $perKm = (float) $fareRate->per_km_rate;
        $includedKm = 4.0;
        $fare = round($base + (max($distanceKm - $includedKm, 0) * $perKm), 2);

        return [$fare, 'distance_based'];
    }
}
