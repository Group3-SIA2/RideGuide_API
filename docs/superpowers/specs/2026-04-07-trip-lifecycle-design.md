# Trip Lifecycle Design (Driver + Multi-Passenger)

## Problem

Implement `TripController` so transportation trips support real-world operations:
- Driver creates and starts a trip from an assigned terminal.
- Multiple commuters can join the same active trip.
- Driver records trip start waypoint and each passenger pickup/drop-off waypoint.
- Passenger journey ends at drop-off, while the trip may continue for other passengers.
- Fare finalization rules:
  - **Terminal-to-terminal**: fixed fare.
  - **Otherwise**: base fare + per-km excess formula.

## Scope and Approach

This design uses a **lifecycle/event-driven model**:
- `trips` tracks trip-level lifecycle.
- `trip_passengers` tracks each commuter lifecycle independently.
- Waypoints are immutable records linked to trip or passenger lifecycle events.
- Driver is authoritative for pickup/drop-off confirmation.
- Seat capacity is enforced strictly with transactional locking.

The design aligns with existing entities already present in the codebase (`trips`, `trip_passengers`, `waypoint`, `passenger_start`, `passenger_stops`, `drv_assign_term`, `fare` logic).

## Domain Model

### Trip (trip-level)
- Purpose: one driver run from a terminal with shared context for many passengers.
- Core fields:
  - `id`
  - `driver_id`
  - `start_terminal_id` (add)
  - `departure_time`
  - `capacity` (add)
  - `status` (add: `scheduled`, `in_progress`, `completed`, `cancelled`)
  - `trip_start_waypoint_id` (add, nullable until start)
  - `trip_end_waypoint_id` (add, nullable until end)

### TripPassenger (passenger-level)
- Purpose: one commuter journey within a trip.
- Core fields:
  - `id`
  - `trip_id`
  - `commuter_id`
  - `passenger_start_id` (nullable until pickup)
  - `passenger_stop_id` (nullable until drop-off)
  - `destination_terminal_id` (nullable)
  - `destination_latitude`, `destination_longitude` (nullable)
  - `status` (add: `joined`, `onboard`, `dropped_off`, `cancelled`, `no_show`)
  - `joined_at`, `picked_up_at`, `dropped_off_at` (add)
  - `fare` (nullable until finalized at drop-off)

### Waypoint
- Immutable location capture (`latitude`, `longitude`, timestamps).
- Reused for:
  - trip start
  - trip end
  - passenger pickup
  - passenger drop-off

## API and State Flow

1. `POST /api/trips`
   - Driver-only.
   - Validates driver profile and assigned terminal (`drv_assign_term`).
   - Creates trip in `scheduled` state with `capacity`.

2. `POST /api/trips/{trip}/join`
   - Commuter-only.
   - Inside DB transaction with `lockForUpdate()` on trip row.
   - Validates trip state (`scheduled` or `in_progress`) and seats available.
   - Creates `trip_passengers` with status `joined`.
   - Stores destination as terminal and/or exact GPS.

3. `POST /api/trips/{trip}/start`
   - Driver-only (trip owner).
   - Captures trip start waypoint.
   - Sets trip status to `in_progress`.

4. `POST /api/trips/{trip}/passengers/{tripPassenger}/pickup`
   - Driver-only (trip owner).
   - Captures pickup waypoint and creates `passenger_start`.
   - Sets passenger status to `onboard`, sets `picked_up_at`.

5. `POST /api/trips/{trip}/passengers/{tripPassenger}/dropoff`
   - Driver-only (trip owner).
   - Captures drop-off waypoint and creates `passenger_stops`.
   - Sets passenger status to `dropped_off`, sets `dropped_off_at`.
   - Finalizes fare with the agreed rule:
     - terminal-to-terminal: fixed route fare
     - otherwise: `base_fare + max(distance_km - included_km, 0) * per_km_rate`
     - initial `included_km` is 4 km, matching existing fare behavior

6. `POST /api/trips/{trip}/end`
   - Driver-only (trip owner).
   - Captures trip end waypoint.
   - Completes trip only when no passengers are still `onboard`.

## Real-World Multi-Passenger Scenario

Example jeepney run:
- Driver starts Trip T1 at Terminal A (capacity 14).
- 8 commuters join over time with mixed destinations:
  - 3 terminal-bound (A -> B): fixed fare.
  - 5 midpoint drops via GPS pin: base + per-km.
- As vehicle moves:
  - each pickup records passenger start waypoint;
  - each drop-off records passenger stop waypoint and closes only that passenger segment.
- Trip remains active until driver ends it, regardless of completed passenger segments.

This mirrors actual public transport operations where passengers board/exit at different points in one continuous run.

## Validation and Error Handling

- `403` when non-owner driver attempts trip actions.
- `409` when join request exceeds capacity or trip is not joinable.
- `422` for invalid lifecycle transitions (e.g., drop-off before pickup).
- `404` for missing trip/passenger records.
- Transaction boundaries on join and passenger state mutation to prevent race conditions.

## Testing Strategy

1. Capacity race: two commuters attempt to take the last seat concurrently.
2. Lifecycle order: cannot drop off before pickup.
3. Fare rule split:
   - terminal-to-terminal -> fixed fare
   - non-terminal route -> base + per-km formula.
4. Multi-passenger lifecycle: independent passenger completion in one trip.
5. Driver authorization: only trip owner can start/end/pickup/drop-off.

## Implementation Notes for TripController

- Use Laravel policies or explicit ownership checks for driver actions.
- Keep waypoint creation centralized (helper/service) to avoid duplicated logic.
- Load relations for API responses (`trip_passengers`, `waypoints`, `commuter`, `driver`).
- Keep state transitions explicit and guarded to avoid invalid updates.

