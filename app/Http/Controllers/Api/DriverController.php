<?php

namespace App\Http\Controllers\Api;

use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use App\Http\Controllers\Controller;
use App\Models\Driver;
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
        
        if ($user->role->name !== 'driver' || $user->role->name === 'admin') {
            return response()->json(['error' => 'Unauthorized.'], 403);
        }
        
        // Check if the user already has a driver profile
        if (Driver::where('user_id', $user->id)->exists()) {
            return response()->json(['error' => 'You already have a driver profile.'], 400);
        }
        
        $validatedData = $request->validate([
            'license_number' => ['required','string','max:255', Rule::unique('driver','license_number'), 'regex:/^[A-Za-z0-9\s]+$/'],
            'franchise_number' => ['required','string','max:255', Rule::unique('driver','franchise_number'), 'regex:/^[A-Za-z0-9\s]+$/']
        ]);

        $driver = Driver::create([
            'user_id' => $request->user()->id,
            'license_number' => $validatedData['license_number'],
            'franchise_number' => $validatedData['franchise_number'],
            'verification_status' => 'unverified', // default lng only admin can edit or set this
        ]);

        return response()->json([
            'message' => 'Driver profile created successfully',
            'driver_profile' => $this->formatDriver($driver->loadMissing('user')),
        ], 201);
    }

    public function readProfile($id): JsonResponse
    {
        $driver = Driver::find($id);

        // Debugging: Check if the driver profile is being retrieved correctly
        //$driverProfile = Driver::where('id', $id)->first();
        // $driverProfile = Driver::where('id', $id)->first();

        // dd($driver);

        if (! $driver) {
            return response()->json(['error' => 'Driver profile not found'], 404);
        }

        $user = auth()->user();
        if (! $user) {
            return response()->json(['error' => 'Unauthenticated'], 401);
        }

        if ($driver->user_id !== $user->id && $user->role->name !== 'admin') {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        return response()->json([
            'driver_profile' => $this->formatDriver($driver->loadMissing('user')),
        ], 200);
    }

    public function updateProfile(Request $request, $id): JsonResponse
    {
        $driver = Driver::find($id);

        if (!$driver) {
            return response()->json(['error' => 'Driver profile not found'], 404);
        }

        $user = auth()->user();
        if (! $user) {
            return response()->json(['error' => 'Unauthenticated'], 401);
        }

        if ($driver->user_id !== auth()->id() && auth()->user()->role->name !== 'admin') {
            return response()->json(['error' => 'Unauthorized'], 403);
        }
        // Admin can update all data including verification status, driver can only update franchise number
        if($user->role->name === 'admin') {
            $validatedData = $request->validate([
                'license_number' => ['sometimes','string','max:255', Rule::unique('driver','license_number')->ignore($driver->id), 'regex:/^[A-Za-z0-9\s]+$/'],
                'franchise_number' => ['sometimes','string','max:255', Rule::unique('driver','franchise_number')->ignore($driver->id), 'regex:/^[A-Za-z0-9\s]+$/'],
                'verification_status' => ['sometimes', Rule::in(['unverified', 'verified', 'rejected'])],
            ]);
        } else {
            $disallowedFields = array_intersect(
                array_keys($request->all()),
                ['license_number', 'verification_status']
            );

            if (!empty($disallowedFields)) {
                return response()->json([
                    'error' => 'You can only update your franchise_number.',
                    'disallowed_fields' => array_values($disallowedFields),
                ], 403);
            }

            $validatedData = $request->validate([
                'franchise_number' => ['sometimes','string','max:255', Rule::unique('driver','franchise_number')->ignore($driver->id)],
            ]);
        }

        $driver->update($validatedData);

        return response()->json([
            'message' => 'Driver profile updated successfully',
            'driver_profile' => $this->formatDriver($driver->fresh()->loadMissing('user')),
        ], 200);
    }

    public function deleteProfile($id): JsonResponse
    {
        $driver = Driver::find($id);

        if (!$driver) {
            return response()->json(['error' => 'Driver profile not found'], 404);
        }

        // admin lungs
        if (auth()->user()->role->name !== 'admin') {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        $driver->delete();

        return response()->json([
            'message' => 'Driver profile deleted successfully',
            'driver_profile' => $this->formatDriver($driver->fresh()->loadMissing('user')),
        ], 200);
    }

    public function restoreProfile($id): JsonResponse
    {
        $driver = Driver::withTrashed()->find($id);

        if (! $driver) {
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

        $driver->restore();

        return response()->json([
            'message' => 'Driver profile restored successfully',
            'driver_profile' => $this->formatDriver($driver->fresh()->loadMissing('user')),
        ], 200);
    }

    private function formatDriver(Driver $driver): array
    {
        return [
            'id'                  => $driver->id,
            'user_id'             => $driver->user_id,
            'user'                => $driver->user ? [
                'id'          => $driver->user->id,
                'first_name'  => $driver->user->first_name,
                'last_name'   => $driver->user->last_name,
                'middle_name' => $driver->user->middle_name,
                'email'       => $driver->user->email,
            ] : null,
            'license_number'      => $driver->license_number,
            'franchise_number'    => $driver->franchise_number,
            'verification_status' => $driver->verification_status,
            'created_at'          => $driver->created_at,
            'updated_at'          => $driver->updated_at,
            'deleted_at'          => $driver->deleted_at,
        ];
    }
}
