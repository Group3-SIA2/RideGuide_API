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
      * 
      * Note: Route model implementation pending - Phase 4 task
      * 
      * Expected response structure (when implemented):
      * [
      *   {
      *     "id": "uuid",
      *     "name": "Route Name",
      *     "waypoints": [
      *       {"latitude": 6.12, "longitude": 125.19},
      *       {"latitude": 6.13, "longitude": 125.20}
      *     ]
      *   }
      * ]
      */
     public function getRoutes(Request $request): JsonResponse
     {
         $limit = min($request->query('limit', 100), 1000);

         // TODO: Implement when Route model is created
         // $routes = Route::select(['id', 'name'])
         //     ->with('waypoints:route_id,latitude,longitude')
         //     ->limit($limit)
         //     ->get();
         
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
