<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\CommuterRideRequest;
use App\Models\PassengerStart;
use App\Models\PassengerStop;
use App\Models\RideRequest;
use App\Models\Trip;
use App\Models\TripPassenger;
use App\Models\Waypoint;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class InquiryController extends Controller
{
    public function driverList(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'latitude'      => 'required|numeric|between:4,22',
            'longitude'     => 'required|numeric|between:116,130',
            'route_id'      => 'nullable|uuid|exists:routes,id',
            'terminal_id'   => 'nullable|uuid|exists:terminals,id',
            'radius_meters' => 'nullable|integer|min:100|max:50000',
        ]);
        $latitude  = (float) $validated['latitude'];
        $longitude = (float) $validated['longitude'];
        $radiusKm  = ((int) ($validated['radius_meters'] ?? 5000)) / 1000;

        $query = CommuterRideRequest::query()
            ->where('status', 'active')
            ->notExpired()
            ->with(['terminal', 'commuter:id,first_name,last_name']);

        if (isset($validated['route_id'])) {
            $query->where('route_id', $validated['route_id']);
        }
        if (isset($validated['terminal_id'])) {
            $query->where('terminal_id', $validated['terminal_id']);
        }

        // For MySQL: filter by pickup coords OR terminal coords within radius.
        // For SQLite (dev/testing): skip geo filter — return all active requests.
        if (DB::connection()->getDriverName() !== 'sqlite') {
            $query->where(function ($q) use ($latitude, $longitude, $radiusKm): void {
                // Has direct GPS pickup coords within range
                $q->where(function ($inner) use ($latitude, $longitude, $radiusKm): void {
                    $inner->whereNotNull('pickup_latitude')
                        ->whereNotNull('pickup_longitude')
                        ->whereRaw(
                            '(6371 * acos(cos(radians(?)) * cos(radians(pickup_latitude)) * cos(radians(pickup_longitude) - radians(?)) + sin(radians(?)) * sin(radians(pickup_latitude)))) <= ?',
                            [$latitude, $longitude, $latitude, $radiusKm]
                        );
                })
                // OR has a terminal whose location is within range
                ->orWhereHas('terminal', function ($tq) use ($latitude, $longitude, $radiusKm): void {
                    $tq->whereNotNull('latitude')
                        ->whereNotNull('longitude')
                        ->whereRaw(
                            '(6371 * acos(cos(radians(?)) * cos(radians(latitude)) * cos(radians(longitude) - radians(?)) + sin(radians(?)) * sin(radians(latitude)))) <= ?',
                            [$latitude, $longitude, $latitude, $radiusKm]
                        );
                });
            });
        }

        $items = $query->latest('created_at')->limit(200)->get()->map(function (CommuterRideRequest $req) {
            $pickupLat = $req->pickup_latitude !== null
                ? (float) $req->pickup_latitude
                : ($req->terminal?->latitude !== null ? (float) $req->terminal->latitude : null);
            $pickupLng = $req->pickup_longitude !== null
                ? (float) $req->pickup_longitude
                : ($req->terminal?->longitude !== null ? (float) $req->terminal->longitude : null);

            return [
                'id'               => $req->id,
                'commuter_id'      => $req->commuter_id,
                'destination'      => $req->destination,
                'route_id'         => $req->route_id,
                'terminal_id'      => $req->terminal_id,
                'wait_time_seconds'=> $req->created_at?->diffInSeconds(now()) ?? 0,
                'commuter'         => $req->commuter ? [
                    'first_name' => $req->commuter->first_name,
                    'last_name'  => $req->commuter->last_name,
                ] : null,
                'pickup_location'  => [
                    'lat' => $pickupLat,
                    'lng' => $pickupLng,
                ],
                'dropoff_location' => [
                    'lat'   => $req->dropoff_latitude !== null ? (float) $req->dropoff_latitude : null,
                    'lng'   => $req->dropoff_longitude !== null ? (float) $req->dropoff_longitude : null,
                    'label' => $req->destination,
                ],
            ];
        })->values();

        return response()->json(['success' => true, 'data' => $items], 200);
    }

    public function driverRespond(Request $request, string $id): JsonResponse
    {
        $validated = $request->validate([
            'status' => 'required|in:accepted,rejected',
        ]);

        $user = $request->user();

        $commuterRequest = CommuterRideRequest::query()->with('terminal')->find($id);
        if (! $commuterRequest || $commuterRequest->status !== 'active' || $commuterRequest->expires_at <= now()) {
            return response()->json(['success' => false, 'message' => 'Request is no longer active.'], 400);
        }

        $rideRequest = RideRequest::query()->updateOrCreate(
            [
                'driver_id'                => $user?->id,
                'commuter_ride_request_id' => $commuterRequest->id,
            ],
            [
                'status'       => $validated['status'],
                'responded_at' => now(),
            ]
        );

        $tripPassengerData = null;

        if ($validated['status'] === 'accepted') {
            $driver = $user?->driver;
            if ($driver) {
                $activeTrip = Trip::query()
                    ->active()
                    ->where('driver_id', $driver->id)
                    ->first();

                if ($activeTrip) {
                    $alreadyOnboard = TripPassenger::where('trip_id', $activeTrip->id)
                        ->where('commuter_id', $commuterRequest->commuter_id)
                        ->whereNull('deleted_at')
                        ->exists();

                    if (! $alreadyOnboard) {

                        $startLat = $commuterRequest->pickup_latitude !== null
                            ? (float) $commuterRequest->pickup_latitude
                            : (float) ($commuterRequest->terminal?->latitude ?? 0);
                        $startLng = $commuterRequest->pickup_longitude !== null
                            ? (float) $commuterRequest->pickup_longitude
                            : (float) ($commuterRequest->terminal?->longitude ?? 0);
                        $stopLat = $commuterRequest->dropoff_latitude !== null
                            ? (float) $commuterRequest->dropoff_latitude
                            : $startLat;
                        $stopLng = $commuterRequest->dropoff_longitude !== null
                            ? (float) $commuterRequest->dropoff_longitude
                            : $startLng;

                        $tripPassenger = DB::transaction(function () use ($commuterRequest, $activeTrip, $startLat, $startLng, $stopLat, $stopLng) {
                            $startWaypoint = Waypoint::create([
                                'latitude'  => (string) $startLat,
                                'longitude' => (string) $startLng,
                            ]);
                            $passengerStart = PassengerStart::create([
                                'waypoint_id' => $startWaypoint->id,
                            ]);
                            $stopWaypoint = Waypoint::create([
                                'latitude'  => (string) $stopLat,
                                'longitude' => (string) $stopLng,
                            ]);
                            $passengerStop = PassengerStop::create([
                                'waypoint_id' => $stopWaypoint->id,
                            ]);
                            return TripPassenger::create([
                                'commuter_id'        => $commuterRequest->commuter_id,
                                'trip_id'            => $activeTrip->id,
                                'passenger_start_id' => $passengerStart->id,
                                'passenger_stop_id'  => $passengerStop->id,
                                'fare'               => 0,
                            ]);
                        });

                        $tripPassengerData = [
                            'id'          => $tripPassenger->id,
                            'commuter_id' => $tripPassenger->commuter_id,
                            'trip_id'     => $tripPassenger->trip_id,
                            'fare'        => (float) $tripPassenger->fare,
                        ];
                    }
                }
            }
        }

        return response()->json([
            'success'       => true,
            'message'       => 'Response submitted.',
            'data' => [
                'id'           => $rideRequest->id,
                'status'       => $rideRequest->status,
                'responded_at' => $rideRequest->responded_at,
                'trip_passenger' => $tripPassengerData,
            ],
        ], $rideRequest->wasRecentlyCreated ? 201 : 200);
    }

    public function commuterList(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'status' => 'nullable|in:active,accepted,completed,cancelled,rejected',
        ]);

        $query = CommuterRideRequest::query()
            ->where('commuter_id', $request->user()?->id)
            ->notExpired()
            ->with('rideRequests');

        if (isset($validated['status'])) {
            $query->where('status', $validated['status']);
        }

        $items = $query->latest('created_at')->limit(200)->get()->map(function (CommuterRideRequest $item) {
            return [
                'id' => $item->id,
                'destination' => $item->destination,
                'route_id' => $item->route_id,
                'terminal_id' => $item->terminal_id,
                'status' => $item->status,
                'expires_at' => $item->expires_at,
                'driver_responses' => $item->rideRequests->map(fn (RideRequest $response) => [
                    'driver_id' => $response->driver_id,
                    'status' => $response->status,
                    'responded_at' => $response->responded_at,
                ])->values(),
            ];
        })->values();

        return response()->json(['success' => true, 'data' => $items], 200);
    }

    public function commuterRespond(Request $request, string $id): JsonResponse
    {
        $validated = $request->validate([
            'status' => 'required|in:accepted,rejected,cancelled',
        ]);

        $item = CommuterRideRequest::query()->find($id);
        if (! $item) {
            return response()->json(['success' => false, 'message' => 'Ride request not found.'], 404);
        }
        if ($item->commuter_id !== $request->user()?->id) {
            return response()->json(['success' => false, 'message' => 'Unauthorized.'], 403);
        }

        $updates = ['status' => $validated['status']];
        if ($validated['status'] === 'cancelled') {
            $updates['expires_at'] = now();
        }
        $item->update($updates);

        return response()->json([
            'success' => true,
            'message' => 'Request updated.',
            'data' => [
                'id' => $item->id,
                'status' => $item->status,
                'responded_at' => $item->updated_at,
            ],
        ], 200);
    }
}
