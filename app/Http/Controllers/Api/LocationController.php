<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Barangay;
use App\Models\Terminal;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class LocationController extends Controller
{
    /**
     * GET /api/locations/terminals
     * Return all terminals with coordinates
     */
    public function getTerminals(Request $request): JsonResponse
    {
        $query = Terminal::query();

        // Optional barangay_id filter
        if ($request->has('barangay_id')) {
            $query->where('barangay', $request->query('barangay_id'));
        }

        // Apply limit (default 100, cap at 1000)
        $limit = min($request->query('limit', 100), 1000);
        $terminals = $query->select(['id', 'terminal_name as name', 'latitude', 'longitude'])
            ->limit($limit)
            ->get();

        return response()->json($terminals);
    }

    /**
     * GET /api/locations/routes
     * Return all routes with waypoints
     * Note: Route model does not yet exist; return empty array structure
     */
    public function getRoutes(Request $request): JsonResponse
    {
        $limit = min($request->query('limit', 100), 1000);

        // Placeholder: routes would be retrieved like:
        // $routes = Route::with('waypoints')->limit($limit)->get();
        // For now, return empty array with correct structure
        
        $routes = collect(); // Empty collection until Route model exists

        return response()->json($routes->map(function ($route) {
            return [
                'id' => $route->id ?? null,
                'name' => $route->name ?? null,
                'waypoints' => $route->waypoints->map(function ($waypoint) {
                    return [
                        'lat' => $waypoint->latitude,
                        'lng' => $waypoint->longitude,
                    ];
                }) ?? [],
            ];
        })->values());
    }

    /**
     * GET /api/locations/barangays
     * Return all barangays with boundary info
     */
    public function getBarangays(): JsonResponse
    {
        $barangays = Barangay::select([
            'id',
            'name',
            'code',
            'center_latitude',
            'center_longitude',
            'north_latitude',
            'south_latitude',
            'east_longitude',
            'west_longitude',
        ])->get();

        return response()->json($barangays);
    }
}
