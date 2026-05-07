<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Barangay;
use App\Models\Province;
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
        $terminals = $query->select([
            'id',
            'terminal_name',
            'terminal_name as name',
            'barangay',
            'city',
            'latitude',
            'longitude',
            'created_at',
            'updated_at',
        ])
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

    /**
     * GET /api/locations/provinces
     * Return all provinces for dropdown selection.
     */
    public function getProvinces(): JsonResponse
    {
        $provinces = Province::query()
            ->select(['id', 'name', 'code', 'region'])
            ->whereNull('deleted_at')
            ->orderBy('name')
            ->get();

        return response()->json([
            'success' => true,
            'data' => $provinces,
        ]);
    }

    /**
     * Get available map filters based on user's role and permissions
     * 
     * Response includes only filters the user has permission to see.
     * SuperAdmin sees all filters regardless of permission assignments.
     * 
     * @return JsonResponse
     */
    public function getAvailableFilters(Request $request): JsonResponse
    {
        $user = $request->user();
        
        if (!$user) {
            return response()->json(['success' => false, 'message' => 'Unauthorized'], 403);
        }

        // SuperAdmin sees all filters
        if ($user->isSuperAdmin()) {
            return response()->json([
                'success' => true,
                'user_role' => 'super_admin',
                'available_filters' => $this->getAllFilters()
            ]);
        }

        // Get user's role
        $userRole = $user->roles()->first();
        if (!$userRole) {
            return response()->json([
                'success' => false,
                'message' => 'User has no role assigned'
            ], 403);
        }

        // Build available filters based on permissions
        $availableFilters = [];
        
        $filterPermissions = [
            'location' => [
                'permission' => 'view_map_locations',
                'name' => 'Location',
                'description' => 'Terminal and barangay boundaries'
            ],
            'routes' => [
                'permission' => 'view_map_routes',
                'name' => 'Routes & Stops',
                'description' => 'Route start and end points'
            ],
            'drivers' => [
                'permission' => 'view_map_drivers',
                'name' => 'Drivers',
                'description' => 'Driver current locations (realtime)'
            ],
            'available_commuters' => [
                'permission' => 'view_map_available_commuters',
                'name' => 'Available Commuters',
                'description' => 'Active commuter ride requests (10-min expiry)'
            ],
            'route_planning' => [
                'permission' => 'view_map_route_planning',
                'name' => 'Route Planning',
                'description' => 'Planned route geometry'
            ],
        ];

        foreach ($filterPermissions as $filterId => $filterData) {
            if ($userRole->hasPermission($filterData['permission'])) {
                $availableFilters[] = [
                    'id' => $filterId,
                    'name' => $filterData['name'],
                    'enabled' => false,  // Default: unchecked
                    'description' => $filterData['description']
                ];
            }
        }

        return response()->json([
            'success' => true,
            'user_role' => $userRole->name,
            'available_filters' => $availableFilters
        ]);
    }

    /**
     * Get all available filters (for SuperAdmin)
     */
    private function getAllFilters(): array
    {
        return [
            [
                'id' => 'location',
                'name' => 'Location',
                'enabled' => false,
                'description' => 'Terminal and barangay boundaries'
            ],
            [
                'id' => 'routes',
                'name' => 'Routes & Stops',
                'enabled' => false,
                'description' => 'Route start and end points'
            ],
            [
                'id' => 'drivers',
                'name' => 'Drivers',
                'enabled' => false,
                'description' => 'Driver current locations (realtime)'
            ],
            [
                'id' => 'available_commuters',
                'name' => 'Available Commuters',
                'enabled' => false,
                'description' => 'Active commuter ride requests (10-min expiry)'
            ],
            [
                'id' => 'route_planning',
                'name' => 'Route Planning',
                'enabled' => false,
                'description' => 'Planned route geometry'
            ],
        ];
    }
}
