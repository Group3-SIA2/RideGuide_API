<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\CommuterRideRequest;
use App\Models\RideRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class InquiryController extends Controller
{
    public function driverList(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'latitude' => 'required|numeric|between:5.5,6.5',
            'longitude' => 'required|numeric|between:124.7,125.7',
            'route_id' => 'nullable|uuid|exists:routes,id',
            'terminal_id' => 'nullable|uuid|exists:terminals,id',
            'radius_meters' => 'nullable|integer|min:100|max:50000',
        ]);
        $latitude = (float) $validated['latitude'];
        $longitude = (float) $validated['longitude'];
        $radiusKm = ((int) ($validated['radius_meters'] ?? 5000)) / 1000;

        $query = CommuterRideRequest::query()
            ->where('status', 'active')
            ->notExpired()
            ->with('terminal');

        if (isset($validated['route_id'])) {
            $query->where('route_id', $validated['route_id']);
        }
        if (isset($validated['terminal_id'])) {
            $query->where('terminal_id', $validated['terminal_id']);
        }
        if (DB::connection()->getDriverName() === 'sqlite') {
            $query->whereHas('terminal', function ($terminalQuery): void {
                $terminalQuery->whereNotNull('latitude')->whereNotNull('longitude');
            });
        } else {
            $query->whereHas('terminal', function ($terminalQuery) use ($latitude, $longitude, $radiusKm): void {
                $terminalQuery->whereNotNull('latitude')
                    ->whereNotNull('longitude')
                    ->whereRaw(
                        '(6371 * acos(cos(radians(?)) * cos(radians(latitude)) * cos(radians(longitude) - radians(?)) + sin(radians(?)) * sin(radians(latitude)))) <= ?',
                        [$latitude, $longitude, $latitude, $radiusKm]
                    );
            });
        }

        $items = $query->latest('created_at')->limit(200)->get()->map(function (CommuterRideRequest $request) {
            return [
                'id' => $request->id,
                'destination' => $request->destination,
                'route_id' => $request->route_id,
                'terminal_id' => $request->terminal_id,
                'wait_time_seconds' => $request->created_at?->diffInSeconds(now()) ?? 0,
                'current_location' => [
                    'lat' => $request->terminal?->latitude,
                    'lng' => $request->terminal?->longitude,
                ],
            ];
        })->values();

        return response()->json(['success' => true, 'data' => $items], 200);
    }

    public function driverRespond(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'commuter_ride_request_id' => 'required|uuid|exists:commuter_ride_requests,id',
            'status' => 'required|in:accepted,rejected',
        ]);

        $commuterRequest = CommuterRideRequest::query()->find($validated['commuter_ride_request_id']);
        if (! $commuterRequest || $commuterRequest->status !== 'active' || $commuterRequest->expires_at <= now()) {
            return response()->json(['success' => false, 'message' => 'Request is no longer active.'], 400);
        }

        $item = RideRequest::query()->updateOrCreate(
            [
                'driver_id' => $request->user()?->id,
                'commuter_ride_request_id' => $commuterRequest->id,
            ],
            [
                'status' => $validated['status'],
                'responded_at' => now(),
            ]
        );

        return response()->json([
            'success' => true,
            'message' => 'Response submitted.',
            'data' => [
                'id' => $item->id,
                'status' => $item->status,
                'responded_at' => $item->responded_at,
            ],
        ], $item->wasRecentlyCreated ? 201 : 200);
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
            'status' => 'required|in:accepted,rejected',
        ]);

        $item = CommuterRideRequest::query()->find($id);
        if (! $item) {
            return response()->json(['success' => false, 'message' => 'Ride request not found.'], 404);
        }
        if ($item->commuter_id !== $request->user()?->id) {
            return response()->json(['success' => false, 'message' => 'Unauthorized.'], 403);
        }

        $item->update(['status' => $validated['status']]);

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
