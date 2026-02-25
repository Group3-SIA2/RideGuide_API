<?php

namespace App\Http\Controllers\Api;

use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use App\Http\Controllers\Controller;
use App\Models\DriverProfile as Driver;
use Illuminate\Validation\Rule;
use App\Models\User;

class DriverController extends Controller
{
    // Drivers Profile CRUD

    public function createProfile(Request $request): JsonResponse
    {
        $user = auth()->user();
        if (! $user) {
            return response()->json(['error' => 'Unauthenticated'], 401);
        }
        
        if ($driverProfile->user_id !== $user->id && $user->role->name !== 'admin') {
            return response()->json(['error' => 'Unauthorized'], 403);
        }
  
        $validatedData = $request->validate([
            'license_number' => ['required','string','max:255', Rule::unique('driver_profile','license_number')],
            'franchise_number' => ['required','string','max:255', Rule::unique('driver_profile','franchise_number')],
            'verification_status' => ['required','in:verified,unverified,rejected'],
        ]);

        $driverProfile = Driver::create([
            'user_id' => $request->user()->id,
            'license_number' => $validatedData['license_number'],
            'franchise_number' => $validatedData['franchise_number'],
            'verification_status' => $validatedData['verification_status'],
        ]);

        return response()->json(['message' => 'Driver profile created successfully', 
                                 'driver_profile' => $driverProfile], 201);
    }

    public function readProfile($id): JsonResponse
    {
        $driverProfile = Driver::find($id);

        // Debugging: Check if the driver profile is being retrieved correctly
        //$driverProfile = Driver::where('id', $id)->first();
        // $driverProfile = Driver::where('id', $id)->first();

        // dd($driverProfile);

        if (! $driverProfile) {
            return response()->json(['error' => 'Driver profile not found'], 404);
        }

        $user = auth()->user();
        if (! $user) {
            return response()->json(['error' => 'Unauthenticated'], 401);
        }

        if ($driverProfile->user_id !== $user->id && $user->role->name !== 'admin') {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        return response()->json(['driver_profile' => $driverProfile], 200);
    }

    public function updateProfile(Request $request, $id): JsonResponse
    {
        $driverProfile = Driver::find($id);

        if (!$driverProfile) {
            return response()->json(['error' => 'Driver profile not found'], 404);
        }

        $user = auth()->user();
        if (! $user) {
            return response()->json(['error' => 'Unauthenticated'], 401);
        }

        // Admin or owner lungs
        if ($driverProfile->user_id !== auth()->id() && auth()->user()->role->name !== 'admin') {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        $validatedData = $request->validate([
            'license_number' => ['sometimes','string','max:255', Rule::unique('driver_profile','license_number')->ignore($driverProfile->id)],
            'franchise_number' => ['sometimes','string','max:255', Rule::unique('driver_profile','franchise_number')->ignore($driverProfile->id)],
            'verification_status' => ['sometimes','in:verified,unverified,rejected'],
        ]);

        $driverProfile->update($validatedData);

        return response()->json(['message' => 'Driver profile updated successfully', 
                                 'driver_profile' => $driverProfile], 200);
    }

    public function deleteProfile($id): JsonResponse
    {
        $driverProfile = Driver::find($id);

        if (!$driverProfile) {
            return response()->json(['error' => 'Driver profile not found'], 404);
        }

        // admin lungs
        if (auth()->user()->role->name !== 'admin') {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        $driverProfile->delete();

        return response()->json(['message' => 'Driver profile deleted successfully'], 200);
    }

    public function restoreProfile($id): JsonResponse
    {
        $driverProfile = Driver::withTrashed()->find($id);

        if (! $driverProfile) {
            return response()->json(['error' => 'Driver profile not found'], 404);
        }

        $user = auth()->user();
        if (! $user) {
            return response()->json(['error' => 'Unauthenticated'], 401);
        }

        // admin lungs
        if ($user->role->name !== 'admin') {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        $driverProfile->restore();

        return response()->json([
            'message' => 'Driver profile restored successfully',
            'driver_profile' => $driverProfile
        ], 200);
    }
}
