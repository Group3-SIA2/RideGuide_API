<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\FareRate;
use App\Models\Terminal;
use App\Models\vehicleType;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

class RouteController extends Controller
{
    public function terminals(): JsonResponse
    {
        $terminals = Terminal::query()
            ->whereNull('deleted_at')
            ->orderBy('terminal_name')
            ->get()
            ->map(function (Terminal $terminal): array {
                return [
                    'id' => (string) $terminal->id,
                    'terminal_name' => $terminal->terminal_name,
                    'barangay' => $terminal->barangay,
                    'city' => $terminal->city,
                    // Flutter terminal model currently requires non-null doubles.
                    'latitude' => $terminal->latitude !== null ? (float) $terminal->latitude : 0.0,
                    'longitude' => $terminal->longitude !== null ? (float) $terminal->longitude : 0.0,
                    'created_at' => $terminal->created_at?->toISOString(),
                    'updated_at' => $terminal->updated_at?->toISOString(),
                    'deleted_at' => $terminal->deleted_at?->toISOString(),
                ];
            })
            ->values();

        return response()->json([
            'success' => true,
            'data' => $terminals,
        ], 200);
    }

    public function routes(): JsonResponse
    {
        $routes = DB::table('routes')
            ->whereNull('deleted_at')
            ->orderBy('created_at')
            ->get()
            ->map(function (object $route): array {
                return [
                    'id' => (string) $route->id,
                    'route_name' => $route->route_name,
                    'route_code' => $route->route_code,
                    'origin_terminal_id' => (string) $route->origin_terminal_id,
                    'destination_terminal_id' => (string) $route->destination_terminal_id,
                    // Compatibility field required by current Flutter route model.
                    'vehicle_id' => '',
                    'status' => $route->status,
                    'created_at' => $route->created_at,
                    'updated_at' => $route->updated_at,
                    'deleted_at' => $route->deleted_at,
                ];
            })
            ->values();

        return response()->json([
            'success' => true,
            'data' => $routes,
        ], 200);
    }

    public function routeStops(): JsonResponse
    {
        $rawStops = DB::table('route_stops')
            ->whereNull('deleted_at')
            ->orderBy('created_at')
            ->orderBy('id')
            ->get()
            ->values();

        $routeStops = $rawStops->map(function (object $stop, int $index): array {
            $routeId = property_exists($stop, 'route_id') && $stop->route_id
                ? (string) $stop->route_id
                : (string) $stop->id;

            $stopOrder = property_exists($stop, 'stop_order') && $stop->stop_order !== null
                ? (int) $stop->stop_order
                : $index + 1;

            return [
                'id' => (string) $stop->id,
                // Compatibility fields required by current Flutter local schema.
                'route_id' => $routeId,
                'terminal_id' => $stop->terminal_id !== null ? (string) $stop->terminal_id : null,
                'stop_name' => $stop->stop_name,
                'latitude' => (float) $stop->latitude,
                'longitude' => (float) $stop->longitude,
                'stop_order' => $stopOrder,
                'created_at' => $stop->created_at,
                'updated_at' => $stop->updated_at,
                'deleted_at' => $stop->deleted_at,
            ];
        })->values();

        return response()->json([
            'success' => true,
            'data' => $routeStops,
        ], 200);
    }

    public function fares(): JsonResponse
    {
        $fares = FareRate::query()
            ->whereNull('deleted_at')
            ->orderByDesc('effective_date')
            ->get()
            ->map(function (FareRate $fareRate): array {
                return [
                    'id' => (string) $fareRate->id,
                    // Compatibility fields required by current Flutter local fares table.
                    'vehicle_id' => '',
                    'base_fare_4KM' => (float) $fareRate->base_fare_4KM,
                    'per_km_rate' => (float) $fareRate->per_km_rate,
                    'discounts' => 0,
                    // Required by mysql_fare_rate mirror mapping in Flutter.
                    'route_standard_fare' => (float) $fareRate->route_standard_fare,
                    'effective_date' => $fareRate->effective_date?->toDateString(),
                    'created_at' => $fareRate->created_at?->toISOString(),
                    'updated_at' => $fareRate->updated_at?->toISOString(),
                    'deleted_at' => $fareRate->deleted_at?->toISOString(),
                ];
            })
            ->values();

        return response()->json([
            'success' => true,
            'data' => $fares,
        ], 200);
    }

    public function vehicleTypes(): JsonResponse
    {
        $vehicleTypes = vehicleType::query()
            ->whereNull('deleted_at')
            ->orderBy('vehicle_type')
            ->get()
            ->map(function (vehicleType $vehicleType): array {
                return [
                    'id' => (string) $vehicleType->id,
                    'vehicle_type' => $vehicleType->vehicle_type,
                    'description' => $vehicleType->description,
                    'image_id' => $vehicleType->image_id !== null ? (string) $vehicleType->image_id : null,
                    'created_at' => $vehicleType->created_at?->toISOString(),
                    'updated_at' => $vehicleType->updated_at?->toISOString(),
                    'deleted_at' => $vehicleType->deleted_at?->toISOString(),
                ];
            })
            ->values();

        return response()->json([
            'success' => true,
            'data' => $vehicleTypes,
        ], 200);
    }
}
