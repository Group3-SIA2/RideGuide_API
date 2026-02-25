<?php

namespace App\Http\Controllers\Api;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
//use App\Models\Fare;
use App\Models\Driverprofile;
use App\Models\UserProfile;
use App\Models\User;

class FareController extends Controller
{
    public function calculateFare(Request $request)
    {
        $basefare = 15;
        $perKmRate = 1;
        $discountedFare = 0.2 * $basefare;

        $vehicleID = $

        // Validate the incoming request data
        $validatedData = $request->validate([
            'distance' => 'required|numeric|min:0',
        ]);

        // Extract validated data
        $distance = $validatedData['distance'];

        // Calculate the fare
        $fare = $basefare + ($perKmRate * $distance) - $discountedFare;

        //insert into fares table
        Fare::create([
            'distance' => $distance,
            'fare' => $fare,
        ]);

        // Return the calculated fare as a JSON response
        return response()->json([
            'success' => true,
            'fare' => round($fare, 2), // Round to 2 decimal places
        ]);
    }
}
