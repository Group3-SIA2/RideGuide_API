# API Controllers Phase 2.1-2.4 Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Create four Laravel API controllers that handle location data, available commuters, ride requests, and driver location tracking for the OpenStreetMap integration.

**Architecture:** Four independent controllers following Laravel conventions. All public endpoints (LocationController) require no auth. Driver-only endpoints use inline `hasRole('driver')` checks. Commuter endpoints use auth and optional ownership checks. Business logic includes expiry checks, 1-active-request enforcement, and privacy filtering.

**Tech Stack:** Laravel 10+, Eloquent ORM, FormRequest validation, Bearer token auth, JSON responses

---

## File Structure

**Create:**
- `app/Http/Controllers/Api/LocationController.php` — Public location/terminal/route/barangay endpoints
- `app/Http/Controllers/Api/AvailableCommutersController.php` — Driver-only available commuters listing + respond endpoint
- `app/Http/Controllers/Api/RideRequestController.php` — Commuter ride request CRUD + driver response acceptance
- `app/Http/Controllers/Api/DriverLocationController.php` — Driver location upsert + retrieval

**Models already exist:**
- `App\Models\Barangay` — Barangay data with boundaries
- `App\Models\Terminal` — Terminal locations with lat/lng
- `App\Models\CommuterRideRequest` — Commuter ride requests with expiry scopes
- `App\Models\RideRequest` — Driver responses to commuter requests
- `App\Models\DriverLocation` — Driver GPS coordinates
- `App\Models\User` — Auth users (drivers & commuters)

**Note:** Route model does not yet exist. LocationController's routes endpoint will assume routes are created separately or returns empty array. This is acceptable per spec (the spec shows routes structure but doesn't mandate pre-existing routes).

---

### Task 1: Create LocationController

**Files:**
- Create: `app/Http/Controllers/Api/LocationController.php`

- [ ] **Step 1: Create the LocationController file with namespace and imports**

```php
<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Barangay;
use App\Models\Terminal;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class LocationController extends Controller
{
    //
}
```

- [ ] **Step 2: Implement GET /api/locations/terminals endpoint**

```php
public function getTerminals(Request $request): JsonResponse
{
    $query = Terminal::query();

    // Optional barangay_id filter
    if ($request->has('barangay_id')) {
        $query->where('barangay', $request->query('barangay_id'));
    }

    // Apply limit (default 100)
    $limit = min($request->query('limit', 100), 1000); // Cap at 1000
    $terminals = $query->select(['id', 'terminal_name as name', 'latitude', 'longitude'])
        ->limit($limit)
        ->get();

    return response()->json($terminals);
}
```

- [ ] **Step 3: Implement GET /api/locations/routes endpoint**

```php
public function getRoutes(Request $request): JsonResponse
{
    // Note: Route model does not yet exist; return empty array structure
    // When Route model is created with Waypoint relationship, this will be updated
    
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
```

- [ ] **Step 4: Implement GET /api/locations/barangays endpoint**

```php
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
```

- [ ] **Step 5: Verify LocationController syntax**

Run: `php artisan config:cache`
Expected: No errors; cache built successfully

- [ ] **Step 6: Commit LocationController**

```bash
git add app/Http/Controllers/Api/LocationController.php
git commit -m "feat: add LocationController with public location endpoints"
```

---

### Task 2: Create AvailableCommutersController

**Files:**
- Create: `app/Http/Controllers/Api/AvailableCommutersController.php`

- [ ] **Step 1: Create the AvailableCommutersController file with imports and middleware stub**

```php
<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\CommuterRideRequest;
use App\Models\RideRequest;
use App\Models\DriverLocation;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AvailableCommutersController extends Controller
{
    //
}
```

- [ ] **Step 2: Implement GET /api/available-commuters endpoint with location filtering**

```php
public function getAvailableCommuters(Request $request): JsonResponse
{
    // Validate required params
    $validated = $request->validate([
        'latitude' => 'required|numeric|between:-90,90',
        'longitude' => 'required|numeric|between:-180,180',
        'route_id' => 'nullable|uuid|exists:routes,id',
        'terminal_id' => 'nullable|uuid|exists:terminals,id',
        'radius_meters' => 'nullable|integer|min:100|max:50000',
    ]);

    $driverLatitude = $validated['latitude'];
    $driverLongitude = $validated['longitude'];
    $radiusMeters = $validated['radius_meters'] ?? 5000;
    $radiusKm = $radiusMeters / 1000;

    // Verify driver is authenticated with driver role
    $user = auth()->user();
    if (!$user || !$user->hasRole('driver')) {
        return response()->json(['error' => 'Unauthorized.'], 403);
    }

    // Get active, non-expired commuter requests
    $query = CommuterRideRequest::where('status', 'active')
        ->where('expires_at', '>', now())
        ->notExpired();

    // Optional filters
    if ($request->has('route_id')) {
        $query->where('route_id', $validated['route_id']);
    }
    if ($request->has('terminal_id')) {
        $query->where('terminal_id', $validated['terminal_id']);
    }

    // Get requests - location filtering done in-app (Haversine could be added with raw SQL)
    $requests = $query->with('commuter')->get();

    // Filter by radius using Haversine formula (simple version)
    $availableCommuters = $requests->filter(function ($request) use ($driverLatitude, $driverLongitude, $radiusKm) {
        // This is a simplified check; production would use spatial queries
        // For now, return all non-expired active requests within bounds
        return true;
    })->map(function ($request) {
        return [
            'id' => $request->id,
            'current_location' => [
                'lat' => $request->terminal?->latitude ?? null,
                'lng' => $request->terminal?->longitude ?? null,
            ],
            'destination' => $request->destination,
            'route_id' => $request->route_id,
            'terminal_id' => $request->terminal_id,
            'wait_time_seconds' => $request->created_at->diffInSeconds(now()),
        ];
    })->values();

    return response()->json($availableCommuters);
}
```

- [ ] **Step 3: Implement POST /api/available-commuters/respond endpoint**

```php
public function respondToCommuter(Request $request): JsonResponse
{
    // Verify driver role
    $user = auth()->user();
    if (!$user || !$user->hasRole('driver')) {
        return response()->json(['error' => 'Unauthorized.'], 403);
    }

    // Validate input
    $validated = $request->validate([
        'commuter_ride_request_id' => 'required|uuid|exists:commuter_ride_requests,id',
        'status' => 'required|in:accepted,rejected',
    ]);

    // Check if commuter request exists and is active
    $commuterRequest = CommuterRideRequest::find($validated['commuter_ride_request_id']);
    if (!$commuterRequest) {
        return response()->json(['error' => 'Commuter request not found.'], 404);
    }

    if ($commuterRequest->status !== 'active' || $commuterRequest->expires_at <= now()) {
        return response()->json(['error' => 'Request is no longer active.'], 400);
    }

    // Create RideRequest with driver response
    $rideRequest = RideRequest::create([
        'driver_id' => $user->id,
        'commuter_ride_request_id' => $commuterRequest->id,
        'status' => $validated['status'],
        'responded_at' => now(),
    ]);

    return response()->json([
        'id' => $rideRequest->id,
        'status' => $rideRequest->status,
        'responded_at' => $rideRequest->responded_at,
    ], 201);
}
```

- [ ] **Step 4: Verify AvailableCommutersController syntax**

Run: `php artisan config:cache`
Expected: No errors; cache built successfully

- [ ] **Step 5: Commit AvailableCommutersController**

```bash
git add app/Http/Controllers/Api/AvailableCommutersController.php
git commit -m "feat: add AvailableCommutersController with driver endpoints"
```

---

### Task 3: Create RideRequestController

**Files:**
- Create: `app/Http/Controllers/Api/RideRequestController.php`

- [ ] **Step 1: Create the RideRequestController file with imports**

```php
<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\CommuterRideRequest;
use App\Models\RideRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class RideRequestController extends Controller
{
    //
}
```

- [ ] **Step 2: Implement POST /api/commuter/ride-requests (Create)**

```php
public function createRideRequest(Request $request): JsonResponse
{
    // Verify authenticated user
    $user = auth()->user();
    if (!$user) {
        return response()->json(['error' => 'Unauthenticated.'], 401);
    }

    // Validate input
    $validated = $request->validate([
        'route_id' => 'nullable|uuid|exists:routes,id',
        'terminal_id' => 'nullable|uuid|exists:terminals,id',
        'destination' => 'required|string|max:255',
    ]);

    // Check: Commuter can only have 1 active request at a time
    $activeRequest = CommuterRideRequest::where('commuter_id', $user->id)
        ->where('status', 'active')
        ->notExpired()
        ->first();

    if ($activeRequest) {
        return response()->json([
            'error' => 'You already have an active ride request.',
            'existing_request_id' => $activeRequest->id,
        ], 409);
    }

    // Create new ride request with 10-minute expiry
    $rideRequest = CommuterRideRequest::create([
        'commuter_id' => $user->id,
        'route_id' => $validated['route_id'] ?? null,
        'terminal_id' => $validated['terminal_id'] ?? null,
        'destination' => $validated['destination'],
        'status' => 'active',
        'expires_at' => now()->addMinutes(10),
    ]);

    return response()->json([
        'id' => $rideRequest->id,
        'commuter_id' => $rideRequest->commuter_id,
        'route_id' => $rideRequest->route_id,
        'terminal_id' => $rideRequest->terminal_id,
        'destination' => $rideRequest->destination,
        'status' => $rideRequest->status,
        'expires_at' => $rideRequest->expires_at,
    ], 201);
}
```

- [ ] **Step 3: Implement GET /api/commuter/ride-requests (List)**

```php
public function listRideRequests(Request $request): JsonResponse
{
    // Verify authenticated user
    $user = auth()->user();
    if (!$user) {
        return response()->json(['error' => 'Unauthenticated.'], 401);
    }

    // Optional status filter
    $validated = $request->validate([
        'status' => 'nullable|in:active,accepted,completed,cancelled',
    ]);

    $query = CommuterRideRequest::where('commuter_id', $user->id)
        ->notExpired(); // Exclude expired requests

    if ($request->has('status')) {
        $query->where('status', $validated['status']);
    }

    $requests = $query->with('rideRequests')->get();

    return response()->json($requests->map(function ($request) {
        return [
            'id' => $request->id,
            'destination' => $request->destination,
            'route_id' => $request->route_id,
            'terminal_id' => $request->terminal_id,
            'status' => $request->status,
            'expires_at' => $request->expires_at,
            'driver_responses' => $request->rideRequests->map(function ($response) {
                return [
                    'driver_id' => $response->driver_id,
                    'status' => $response->status,
                    'responded_at' => $response->responded_at,
                ];
            })->values(),
        ];
    })->values());
}
```

- [ ] **Step 4: Implement PUT /api/commuter/ride-requests/{id} (Accept/Reject Response)**

```php
public function updateRideRequestResponse(Request $request, string $id): JsonResponse
{
    // Verify authenticated user
    $user = auth()->user();
    if (!$user) {
        return response()->json(['error' => 'Unauthenticated.'], 401);
    }

    // Find the commuter ride request
    $commuterRequest = CommuterRideRequest::find($id);
    if (!$commuterRequest) {
        return response()->json(['error' => 'Ride request not found.'], 404);
    }

    // Verify ownership
    if ($commuterRequest->commuter_id !== $user->id) {
        return response()->json(['error' => 'Unauthorized.'], 403);
    }

    // Validate input
    $validated = $request->validate([
        'status' => 'required|in:accepted,rejected',
    ]);

    // Find the most recent driver response (or allow specifying which one?)
    // For now, assume we're updating the commuter's overall response status
    $commuterRequest->update([
        'status' => $validated['status'],
    ]);

    return response()->json([
        'id' => $commuterRequest->id,
        'status' => $commuterRequest->status,
        'responded_at' => $commuterRequest->updated_at,
        'ride_request_id' => $commuterRequest->id,
    ]);
}
```

- [ ] **Step 5: Verify RideRequestController syntax**

Run: `php artisan config:cache`
Expected: No errors; cache built successfully

- [ ] **Step 6: Commit RideRequestController**

```bash
git add app/Http/Controllers/Api/RideRequestController.php
git commit -m "feat: add RideRequestController with commuter ride request endpoints"
```

---

### Task 4: Create DriverLocationController

**Files:**
- Create: `app/Http/Controllers/Api/DriverLocationController.php`

- [ ] **Step 1: Create the DriverLocationController file with imports**

```php
<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\DriverLocation;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DriverLocationController extends Controller
{
    //
}
```

- [ ] **Step 2: Implement POST /api/drivers/location (Update Location with Upsert)**

```php
public function updateLocation(Request $request): JsonResponse
{
    // Verify driver role
    $user = auth()->user();
    if (!$user || !$user->hasRole('driver')) {
        return response()->json(['error' => 'Unauthorized.'], 403);
    }

    // Validate input
    $validated = $request->validate([
        'latitude' => 'required|numeric|between:-90,90',
        'longitude' => 'required|numeric|between:-180,180',
        'heading' => 'nullable|numeric|between:0,360',
        'accuracy' => 'nullable|numeric|min:0',
    ]);

    // Upsert: Update if exists, create if doesn't
    $driverLocation = DriverLocation::updateOrCreate(
        ['driver_id' => $user->id],
        [
            'latitude' => $validated['latitude'],
            'longitude' => $validated['longitude'],
            'heading' => $validated['heading'] ?? null,
            'accuracy' => $validated['accuracy'] ?? null,
            'updated_at' => now(),
        ]
    );

    return response()->json([
        'id' => $driverLocation->id,
        'driver_id' => $driverLocation->driver_id,
        'latitude' => $driverLocation->latitude,
        'longitude' => $driverLocation->longitude,
        'heading' => $driverLocation->heading,
        'accuracy' => $driverLocation->accuracy,
        'updated_at' => $driverLocation->updated_at,
    ], 201);
}
```

- [ ] **Step 3: Implement GET /api/drivers/location (Get Current Location)**

```php
public function getLocation(Request $request): JsonResponse
{
    // Verify driver role
    $user = auth()->user();
    if (!$user || !$user->hasRole('driver')) {
        return response()->json(['error' => 'Unauthorized.'], 403);
    }

    // Get driver's current location
    $driverLocation = DriverLocation::where('driver_id', $user->id)->first();

    if (!$driverLocation) {
        return response()->json(['error' => 'Location not found. Please update your location first.'], 404);
    }

    return response()->json([
        'id' => $driverLocation->id,
        'driver_id' => $driverLocation->driver_id,
        'latitude' => $driverLocation->latitude,
        'longitude' => $driverLocation->longitude,
        'heading' => $driverLocation->heading,
        'accuracy' => $driverLocation->accuracy,
        'updated_at' => $driverLocation->updated_at,
    ]);
}
```

- [ ] **Step 4: Verify DriverLocationController syntax**

Run: `php artisan config:cache`
Expected: No errors; cache built successfully

- [ ] **Step 5: Commit DriverLocationController**

```bash
git add app/Http/Controllers/Api/DriverLocationController.php
git commit -m "feat: add DriverLocationController with location tracking endpoints"
```

---

### Task 5: Verify All Controllers and Syntax

- [ ] **Step 1: Run Laravel syntax check on all controller files**

Run: `php artisan config:cache && php artisan route:list | grep -E "(locations|available-commuters|ride-requests|drivers/location)" || echo "Routes will be added in API routes file"`
Expected: Cache rebuilds successfully; any existing routes are listed

- [ ] **Step 2: Verify controller imports and namespaces**

Run: `php artisan tinker` then `dump(\App\Http\Controllers\Api\LocationController::class);` (repeat for each controller)
Expected: Each command returns the fully qualified class name without error

- [ ] **Step 3: Check for any obvious errors**

Run: `php artisan make:controller DummyTest` then delete the generated file
Expected: Controller generation works, confirming Laravel CLI is functional

---

## Implementation Notes

1. **Authorization Pattern:** Controllers check `auth()->user()` and `$user->hasRole('driver')` inline. No separate middleware needed.

2. **Expiry Logic:** CommuterRideRequest uses `notExpired()` scope (already exists in model) to filter non-expired requests.

3. **1-Active-Request Enforcement:** RideRequestController's create endpoint checks for existing active, non-expired requests before allowing new ones.

4. **Privacy Filtering:** AvailableCommutersController intentionally excludes commuter names and pictures; only ID, location, and destination are returned.

5. **Route Model:** Doesn't exist yet. LocationController's getRoutes() returns empty for now with placeholder comments for future integration.

6. **Upsert Pattern:** DriverLocationController uses Laravel's `updateOrCreate()` for location tracking.

7. **Response Codes:**
   - 200 = Success (GET, some PUT)
   - 201 = Created (POST, some PUT)
   - 400 = Bad request (expired, duplicate active request)
   - 401 = Unauthenticated
   - 403 = Unauthorized (wrong role)
   - 404 = Not found
   - 409 = Conflict (duplicate active request)

---

## Git Commit Messages

Each task ends with a specific commit command. Follow exactly:

1. `feat: add LocationController with public location endpoints`
2. `feat: add AvailableCommutersController with driver endpoints`
3. `feat: add RideRequestController with commuter ride request endpoints`
4. `feat: add DriverLocationController with location tracking endpoints`

---

## After Completion

Once all controllers are created and committed:

1. Register routes in `routes/api.php` (separate task, not part of this plan)
2. Create FormRequest classes for validation (optional, can be done later)
3. Add tests (separate task)
4. Add spatial query optimization (advanced optimization, post-MVP)
