<?php

namespace App\Http\Controllers\Api;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\Models\Organization;
use App\Models\OrganizationFareRate;
use App\Models\PassengerStart;
use App\Models\PassengerStop;
use App\Models\Discount;

class FareController extends Controller
{
    // Endpoint: /api/calculate-fare
    public function calculateFare(Request $request)
    {
        $validatedData = $request->validate([
            'organization_id' => 'required|uuid|exists:organizations,id',
            'distance' => 'required|numeric|min:0',
            'passenger_start_id' => 'nullable|uuid|exists:passenger_start,id',
            'passenger_stop_id' => 'nullable|uuid|exists:passenger_stops,id',
        ]);

        $distance = (float) $validatedData['distance'];
        $organization = Organization::query()->findOrFail($validatedData['organization_id']);

        $fareRateLink = OrganizationFareRate::with('fareRate')
            ->where('organization_id', $organization->id)
            ->latest('created_at')
            ->first();

        if (! $fareRateLink || ! $fareRateLink->fareRate) {
            return response()->json([
                'success' => false,
                'message' => 'No fare rate configured for this organization.',
            ], 422);
        }

        $fareRate = $fareRateLink->fareRate;
        $baseFare = (float) $fareRate->base_fare_4KM;
        $perKmRate = (float) $fareRate->per_km_rate;
        $routeStandardFare = (float) $fareRate->route_standard_fare;

        $isTerminalToTerminal = false;
        $terminalThresholdKm = 0.3; // 300m counts as terminal proximity.

        $startWaypoint = null;
        $stopWaypoint = null;

        if (! empty($validatedData['passenger_start_id'])) {
            $startWaypoint = PassengerStart::with('waypoint')->find($validatedData['passenger_start_id'])?->waypoint;
        }

        if (! empty($validatedData['passenger_stop_id'])) {
            $stopWaypoint = PassengerStop::with('waypoint')->find($validatedData['passenger_stop_id'])?->waypoint;
        }

        if ($startWaypoint && $stopWaypoint) {
            $terminals = $organization->terminals()->get(['latitude', 'longitude']);

            $startNear = $this->isNearAnyTerminal($startWaypoint->latitude, $startWaypoint->longitude, $terminals, $terminalThresholdKm);
            $stopNear = $this->isNearAnyTerminal($stopWaypoint->latitude, $stopWaypoint->longitude, $terminals, $terminalThresholdKm);

            $isTerminalToTerminal = $startNear && $stopNear;
        }

        if ($isTerminalToTerminal) {
            $fare = $routeStandardFare;
        } else {
            $excessDistance = max($distance - 4, 0);
            $fare = $baseFare + ($perKmRate * $excessDistance);
        }

        $discountApplied = false;
        $commuter = $request->user()?->commuter;

        if ($commuter && $commuter->discount && $commuter->discount->verification_status === Discount::VERIFICATION_VERIFIED) {
            $fare *= 0.8; // 80% of the original fare
            $discountApplied = true;
        }

        return response()->json([
            'success' => true,
            'fare' => round($fare, 2),
            'terminal_to_terminal' => $isTerminalToTerminal,
            'discount_applied' => $discountApplied,
        ]);
    }

    // Helper function to check if near any terminal
    private function isNearAnyTerminal(float $lat, float $lng, $terminals, float $thresholdKm): bool
    {
        foreach ($terminals as $terminal) {
            if ($terminal->latitude === null || $terminal->longitude === null) {
                continue;
            }

            $distance = $this->haversineKm($lat, $lng, (float) $terminal->latitude, (float) $terminal->longitude);
            if ($distance <= $thresholdKm) {
                return true;
            }
        }

        return false;
    }
    // Haversine formula lat lng calculation
    private function haversineKm(float $lat1, float $lon1, float $lat2, float $lon2): float
    {
        $earthRadius = 6371;

        $dLat = deg2rad($lat2 - $lat1);
        $dLon = deg2rad($lon2 - $lon1);

        $a = sin($dLat / 2) ** 2
            + cos(deg2rad($lat1)) * cos(deg2rad($lat2))
            * sin($dLon / 2) ** 2;

        $c = 2 * atan2(sqrt($a), sqrt(1 - $a));

        return $earthRadius * $c;
    }
}