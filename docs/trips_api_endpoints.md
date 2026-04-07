# Trips API Endpoints

## Base Flow

1. Driver creates trip.
2. Commuter joins trip.
3. Driver starts trip (records trip start waypoint).
4. Driver optionally records pickup waypoint per passenger.
5. Driver drops off passenger (records waypoint and finalizes fare).
6. Driver ends trip when no onboard passengers remain.

## Endpoints

### `POST /api/trips`
- Driver-only.
- Creates a `scheduled` trip.
- Requires:
  - `departure_time`
  - `start_terminal_id`
  - `capacity`

### `POST /api/trips/{trip}/join`
- Commuter-only.
- Joins trip if capacity is available.
- Destination can be:
  - `destination_terminal_id`, or
  - `destination_latitude` + `destination_longitude`.

### `POST /api/trips/{trip}/start`
- Driver-only (trip owner).
- Stores trip start waypoint.
- Requires:
  - `latitude`
  - `longitude`

### `POST /api/trips/{trip}/passengers/{tripPassenger}/pickup`
- Driver-only (trip owner).
- Stores passenger pickup waypoint.
- Requires:
  - `latitude`
  - `longitude`

### `POST /api/trips/{trip}/passengers/{tripPassenger}/dropoff`
- Driver-only (trip owner).
- Stores passenger drop-off waypoint and ends passenger segment.
- Requires:
  - `latitude`
  - `longitude`
  - `distance_km`

Fare rule:
- Terminal-to-terminal trip: fixed route fare.
- Non-terminal trip: `base_fare_4KM + max(distance_km - 4, 0) * per_km_rate`.

### `POST /api/trips/{trip}/end`
- Driver-only (trip owner).
- Fails if passengers are still `onboard`.
- Requires:
  - `latitude`
  - `longitude`
