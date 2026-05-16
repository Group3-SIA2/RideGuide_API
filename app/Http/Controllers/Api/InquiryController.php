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
        $debugLogPath = 'C:/Users/marjo/AndroidStudioProjects/ride_guide/debug-91f1b8.log';
        $agentLog = static function (string $hypothesisId, string $location, string $message, array $data = []) use ($debugLogPath): void {
            // #region agent log
            file_put_contents(
                $debugLogPath,
                json_encode([
                    'sessionId' => '91f1b8',
                    'hypothesisId' => $hypothesisId,
                    'location' => $location,
                    'message' => $message,
                    'data' => $data,
                    'timestamp' => (int) round(microtime(true) * 1000),
                ], JSON_THROW_ON_ERROR)."\n",
                FILE_APPEND | LOCK_EX
            );
            // #endregion
        };

        $agentLog('A', 'InquiryController::driverRespond', 'entry', [
            'request_id' => $id,
            'status' => $validated['status'],
            'driver_user_id' => $user?->id,
        ]);

        $commuterRequest = CommuterRideRequest::query()->with('terminal')->find($id);
        if (! $commuterRequest) {
            return response()->json(['success' => false, 'message' => 'Ride request not found.'], 404);
        }

        $driver = $user?->driver;
        $activeTrip = $driver
            ? Trip::query()->active()->where('driver_id', $driver->id)->first()
            : null;

        $existingRideRequest = RideRequest::query()
            ->where('driver_id', $user?->id)
            ->where('commuter_ride_request_id', $commuterRequest->id)
            ->first();

        $existingPassenger = ($activeTrip && $commuterRequest->commuter_id)
            ? TripPassenger::query()
                ->where('trip_id', $activeTrip->id)
                ->where('commuter_id', $commuterRequest->commuter_id)
                ->whereNull('deleted_at')
                ->first()
            : null;

        if ($validated['status'] === 'accepted' && $existingPassenger !== null) {
            $agentLog('B', 'InquiryController::driverRespond', 'idempotent_accept', [
                'ride_request_status' => $existingRideRequest?->status,
                'trip_passenger_id' => $existingPassenger?->id,
                'commuter_request_status' => $commuterRequest->status,
            ]);

            return response()->json([
                'success' => true,
                'message' => $existingPassenger
                    ? 'Passenger is already on board.'
                    : 'You already accepted this request.',
                'data'    => [
                    'id'             => $existingRideRequest?->id,
                    'status'         => $existingRideRequest?->status ?? 'accepted',
                    'responded_at'   => $existingRideRequest?->responded_at,
                    'trip_passenger' => $this->formatTripPassengerData($existingPassenger),
                ],
            ], 200);
        }

        if ($commuterRequest->status !== 'active' || $commuterRequest->expires_at <= now()) {
            $agentLog('D', 'InquiryController::driverRespond', 'inactive_request', [
                'found' => true,
                'status' => $commuterRequest->status,
                'expires_at' => $commuterRequest->expires_at?->toIso8601String(),
            ]);

            return response()->json([
                'success' => false,
                'message' => "Request is no longer active (status: {$commuterRequest->status}).",
            ], 400);
        }

        $agentLog('A', 'InquiryController::driverRespond', 'commuter_request_ok', [
            'commuter_id' => $commuterRequest->commuter_id,
        ]);

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
            if ($driver) {
                $agentLog('C', 'InquiryController::driverRespond', 'accept_branch', [
                    'has_driver_profile' => true,
                    'active_trip_id' => $activeTrip?->id,
                ]);

                if ($activeTrip) {
                    if ($existingPassenger === null) {
                        $existingPassenger = TripPassenger::query()
                            ->where('trip_id', $activeTrip->id)
                            ->where('commuter_id', $commuterRequest->commuter_id)
                            ->whereNull('deleted_at')
                            ->first();
                    }

                    if ($existingPassenger !== null) {
                        $tripPassengerData = $this->formatTripPassengerData($existingPassenger);
                        $agentLog('C', 'InquiryController::driverRespond', 'already_onboard', [
                            'trip_passenger_id' => $existingPassenger->id,
                        ]);
                    } else {

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

                        try {
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
                        } catch (\Throwable $e) {
                            $agentLog('A', 'InquiryController::driverRespond', 'trip_passenger_create_failed', [
                                'error' => $e->getMessage(),
                                'commuter_id' => $commuterRequest->commuter_id,
                                'trip_id' => $activeTrip->id,
                            ]);
                            throw $e;
                        }

                        $agentLog('A', 'InquiryController::driverRespond', 'trip_passenger_created', [
                            'trip_passenger_id' => $tripPassenger->id,
                        ]);

                        $tripPassengerData = $this->formatTripPassengerData($tripPassenger);
                    }
                }
            } else {
                $agentLog('C', 'InquiryController::driverRespond', 'accept_branch', [
                    'has_driver_profile' => false,
                    'active_trip_id' => null,
                ]);
            }

            $commuterRequest->update(['status' => 'accepted']);
        }

        $agentLog('B', 'InquiryController::driverRespond', 'success_response', [
            'ride_request_status' => $rideRequest->status,
            'trip_passenger_created' => $tripPassengerData !== null,
        ]);

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

    private function formatTripPassengerData(?TripPassenger $tripPassenger): ?array
    {
        if (! $tripPassenger) {
            return null;
        }

        return [
            'id'          => $tripPassenger->id,
            'commuter_id' => $tripPassenger->commuter_id,
            'trip_id'     => $tripPassenger->trip_id,
            'fare'        => (float) $tripPassenger->fare,
        ];
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
