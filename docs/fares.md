# Fare Management

This document describes fare calculation for commuters (API) and fare rate management for admins.

## API: Fare Calculation

**Controller:** `App\Http\Controllers\Api\FareController`  
**Method:** `calculateFare(Request $request)`

### Purpose
Calculates the commuter fare for a trip based on the organization’s active fare rate, distance traveled, terminal proximity, and (optional) verified commuter discount.

### Request
Validated input fields:
- `organization_id` (required, UUID): Must exist in `organizations.id`.
- `distance` (required, numeric, min 0): Distance in kilometers.
- `passenger_start_id` (optional, UUID): Must exist in `passenger_start.id`.
- `passenger_stop_id` (optional, UUID): Must exist in `passenger_stops.id`.

### Core Logic
1. Loads the latest `OrganizationFareRate` for the organization (via `fareRate` relation).
2. Determines if the trip is **terminal-to-terminal**:
   - Loads start/stop waypoints (if provided).
   - Checks if both waypoints are within `0.3 km` of any terminal linked to the organization.
3. Calculates fare:
   - If terminal-to-terminal: use `route_standard_fare`.
   - Otherwise: `base_fare_4KM + per_km_rate × max(distance - 4, 0)`.
4. Applies discount if the authenticated commuter has a verified discount:
   - If `commuter.discount.verification_status === Discount::VERIFICATION_VERIFIED`.
   - Fare is multiplied by `0.8` (80% of original fare).

### Response
`200 OK`
```json
{
  "success": true,
  "fare": 42.50,
  "terminal_to_terminal": false,
  "discount_applied": true
}
```

`422 Unprocessable Entity` (no fare rate configured)
```json
{
  "success": false,
  "message": "No fare rate configured for this organization."
}
```

### Helper Methods
- **`isNearAnyTerminal(lat, lng, terminals, thresholdKm)`**: Returns `true` if any terminal is within the threshold distance.
- **`haversineKm(lat1, lon1, lat2, lon2)`**: Calculates distance between coordinates using the Haversine formula.

### Notes & Edge Cases
- If start/stop IDs are not provided or their waypoints are missing, terminal-to-terminal is treated as `false`.
- If terminals lack coordinates, they are skipped.
- Distance is expected in kilometers.

---

## Admin: Fare Rate Management

**Controller:** `App\Http\Controllers\Admin\FareController`

### Purpose
Allows admins/org managers to view, create, and update fare rates per organization and terminal routes.

### Views
- **`index(Request $request)`**: Shows current fare rate configuration and assigned terminals for the managed organization.
- **`overview(Request $request)`**: Displays a summarized list of fare rates grouped by organization and route.

### Create & Update Actions

#### `store(Request $request)`
Updates an existing *route fare* (fare rate values) for the organization.

Validated fields:
- `origin_terminal_id` (nullable, UUID, must exist in `terminals.id`)
- `destination_terminal_id` (nullable, UUID, must exist in `terminals.id`)
- `base_fare_4KM` (required, numeric, min 0)
- `per_km_rate` (required, numeric, min 0)
- `route_standard_fare` (required, numeric, min 0)
- `effective_date` (required, date)

Rules:
- Selected terminals must belong to the organization.
- Origin and destination must be different if both are provided.
- A matching route fare link must already exist, or it returns an error.

#### `updateRouteFare(Request $request, OrganizationFareRate $routeFare)`
Updates a specific route fare and prevents duplicates.

Rules:
- Route fare must belong to the managed organization.
- Terminals must belong to the organization.
- Prevents duplicate routes in either direction.

#### `createRouteFare(Request $request)`
Creates a new route fare entry and assigns a new `FareRate`.

Rules:
- Same validation as above.
- Prevents duplicates in either direction.

### Organization Resolution
- Admin/Super Admin can pass `organization_id` to manage any org.
- Other roles are limited to their managed organization (owned or assigned).

### Common Error Messages
- "Route fare not found. Use Add Fare Rate to create a new route."
- "This Route Fare is added already, Update Available at Fare Rate OverView"
- "Origin and destination terminals must be different."
- "No managed organization is assigned to your account."

### Related Models
- `Organization`
- `OrganizationFareRate`
- `FareRate`
- `Role`

---

## Where to Find These Controllers
- API: `app/Http/Controllers/Api/FareController.php`
- Admin: `app/Http/Controllers/Admin/FareController.php`
