<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Driver;
use App\Models\LicenseId;
use App\Models\LicenseImage;
use App\Support\DashboardCache;
use App\Support\InputValidation;
use App\Support\MediaStorage;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class DriverController extends Controller
{
    // Drivers Profile CRUD

    public function createProfile(Request $request): JsonResponse
    {
        $user = auth()->user();
        if (! $user) {
            return response()->json(['error' => 'Unauthenticated'], 401);
        }

        if (! $user->hasRole('driver')) {
            return response()->json(['error' => 'Unauthorized.'], 403);
        }

        // Check if the user already has a driver profile
        if (Driver::where('user_id', $user->id)->exists()) {
            return response()->json(['error' => 'You already have a driver profile.'], 400);
        }

        $validatedData = $request->validate([
            'organization_id' => ['nullable', 'string', 'exists:organizations,id'],
            'license_id_number' => [...InputValidation::safeStringRules(required: true, max: 255), Rule::unique('license_id', 'license_id'), 'regex:/^[A-Za-z0-9\s-]+$/'],
            'license_image_front' => ['required', ...MediaStorage::imageValidationRules()],
            'license_image_back' => ['nullable', ...MediaStorage::imageValidationRules()],
        ]);

        $licenseImage = LicenseImage::create([
            'image_front' => MediaStorage::putFile('driver_license_ids', $request->file('license_image_front')),
            'image_back' => $request->hasFile('license_image_back')
                ? MediaStorage::putFile('driver_license_ids', $request->file('license_image_back'))
                : null,
        ]);

        $licenseId = LicenseId::create([
            'license_id' => $validatedData['license_id_number'],
            'image_id' => $licenseImage->id,
            'verification_status' => LicenseId::VERIFICATION_STATUS_UNVERIFIED,
        ]);

        $driver = Driver::create([
            'user_id' => $request->user()->id,
            'organization_id' => $validatedData['organization_id'] ?? null,
            'driver_license_id' => $licenseId->id,
        ]);

        DashboardCache::forgetUserDashboards($driver->user_id);

        return response()->json([
            'message' => 'Driver profile created successfully',
            'driver_profile' => $this->formatDriver(
                $driver->load(['user', 'organization', 'licenseId.image', 'usersEmergencyContact.emergencyContact'])
            ),
        ], 201);
    }

    public function readProfile($id): JsonResponse
    {
        $driver = Driver::with(['user', 'organization', 'licenseId.image', 'usersEmergencyContact.emergencyContact'])
            ->find($id);

        // Debugging: Check if the driver profile is being retrieved correctly
        // $driverProfile = Driver::where('id', $id)->first();
        // $driverProfile = Driver::where('id', $id)->first();

        // dd($driver);

        if (! $driver) {
            return response()->json(['error' => 'Driver profile not found'], 404);
        }

        $user = auth()->user();
        if (! $user) {
            return response()->json(['error' => 'Unauthenticated'], 401);
        }

        if ($driver->user_id !== $user->id && ! $user->hasRole('admin')) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        return response()->json([
            'driver_profile' => $this->formatDriver($driver),
        ], 200);
    }

    public function updateProfile(Request $request, $id): JsonResponse
    {
        $driver = Driver::with(['user', 'organization', 'licenseId.image', 'usersEmergencyContact.emergencyContact'])->find($id);

        if (! $driver) {
            return response()->json(['error' => 'Driver profile not found'], 404);
        }

        $user = auth()->user();
        if (! $user) {
            return response()->json(['error' => 'Unauthenticated'], 401);
        }

        if ($driver->user_id !== $user->id && ! $user->hasRole('admin')) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        $license = $driver->licenseId;

        if ($user->hasRole('admin')) {
            $validatedData = $request->validate([
                'organization_id' => ['nullable', 'string', 'exists:organizations,id'],
                'license_id_number' => [
                    'sometimes',
                    ...InputValidation::safeStringRules(required: true, max: 255),
                    'regex:/^[A-Za-z0-9\s-]+$/',
                    Rule::unique('license_id', 'license_id')->ignore(optional($license)->id),
                ],
                'license_image_front' => ['nullable', ...MediaStorage::imageValidationRules()],
                'license_image_back' => ['nullable', ...MediaStorage::imageValidationRules()],
                'license_verification_status' => ['sometimes', Rule::in([
                    LicenseId::VERIFICATION_STATUS_UNVERIFIED,
                    LicenseId::VERIFICATION_STATUS_VERIFIED,
                    LicenseId::VERIFICATION_STATUS_REJECTED,
                ])],
                'license_rejection_reason' => ['nullable', 'string', 'max:255'],
            ]);
        } else {
            $disallowedFields = array_intersect(
                array_keys($request->all()),
                ['license_number', 'verification_status', 'organization_id']
            );

            if (! empty($disallowedFields)) {
                return response()->json([
                    'error' => 'You can only update your license ID images.',
                    'disallowed_fields' => array_values($disallowedFields),
                ], 403);
            }

            $validatedData = $request->validate([
                'license_id_number' => [
                    'sometimes',
                    ...InputValidation::safeStringRules(required: true, max: 255),
                    'regex:/^[A-Za-z0-9\s-]+$/',
                    Rule::unique('license_id', 'license_id')->ignore(optional($license)->id),
                ],
                'license_image_front' => ['nullable', ...MediaStorage::imageValidationRules()],
                'license_image_back' => ['nullable', ...MediaStorage::imageValidationRules()],
            ]);
        }

        $driverFields = ['organization_id'];
        $driverUpdates = [];
        foreach ($driverFields as $field) {
            if (array_key_exists($field, $validatedData)) {
                $driverUpdates[$field] = $validatedData[$field];
            }
        }

        if (! empty($driverUpdates)) {
            $driver->update($driverUpdates);
        }

        if ($license) {
            $licenseUpdates = [];
            if (array_key_exists('license_id_number', $validatedData)) {
                $licenseUpdates['license_id'] = $validatedData['license_id_number'];
            }

            if ($user->hasRole('admin')) {
                if (array_key_exists('license_verification_status', $validatedData)) {
                    $licenseUpdates['verification_status'] = $validatedData['license_verification_status'];

                    if ($validatedData['license_verification_status'] !== LicenseId::VERIFICATION_STATUS_REJECTED) {
                        $licenseUpdates['rejection_reason'] = null;
                    }
                }

                if (array_key_exists('license_rejection_reason', $validatedData)) {
                    $licenseUpdates['rejection_reason'] = $validatedData['license_rejection_reason'];
                }
            }

            if (! empty($licenseUpdates)) {
                $license->update($licenseUpdates);
            }

            if ($request->hasFile('license_image_front') || $request->hasFile('license_image_back')) {
                $image = $license->image;

                if (! $image) {
                    if (! $request->hasFile('license_image_front')) {
                        return response()->json([
                            'error' => 'Front image is required when uploading license images for the first time.',
                        ], 422);
                    }

                    $image = LicenseImage::create([
                        'image_front' => MediaStorage::putFile('driver_license_ids', $request->file('license_image_front')),
                        'image_back' => $request->hasFile('license_image_back')
                            ? MediaStorage::putFile('driver_license_ids', $request->file('license_image_back'))
                            : null,
                    ]);

                    $license->update(['image_id' => $image->id]);
                } else {
                    $imageUpdates = [];
                    if ($request->hasFile('license_image_front')) {
                        $imageUpdates['image_front'] = MediaStorage::putFile('driver_license_ids', $request->file('license_image_front'));
                    }
                    if ($request->hasFile('license_image_back')) {
                        $imageUpdates['image_back'] = MediaStorage::putFile('driver_license_ids', $request->file('license_image_back'));
                    }

                    if (! empty($imageUpdates)) {
                        $image->update($imageUpdates);
                    }
                }
            }
        }

        DashboardCache::forgetUserDashboards($driver->user_id);

        return response()->json([
            'message' => 'Driver profile updated successfully',
            'driver_profile' => $this->formatDriver(
                $driver->fresh()->load(['user', 'organization', 'licenseId.image', 'usersEmergencyContact.emergencyContact'])
            ),
        ], 200);
    }

    public function deleteProfile($id): JsonResponse
    {
        $driver = Driver::with(['user', 'organization', 'licenseId.image', 'usersEmergencyContact.emergencyContact'])->find($id);

        if (! $driver) {
            return response()->json(['error' => 'Driver profile not found'], 404);
        }

        // admin only
        if (! auth()->user()->hasRole('admin')) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        $driverDetails = $this->formatDriver($driver);

        if ($driver->licenseId) {
            if ($driver->licenseId->image) {
                $driver->licenseId->image->delete();
            }
            $driver->licenseId->delete();
        }

        $driver->delete();
        DashboardCache::forgetUserDashboards($driver->user_id);

        return response()->json([
            'message' => 'Driver profile deleted successfully',
            'driver_profile' => $driverDetails,
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

        // admin only
        if (! $user->hasRole('admin')) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        $license = $driver->driver_license_id
            ? LicenseId::withTrashed()->find($driver->driver_license_id)
            : null;

        if ($license && $license->trashed()) {
            $license->restore();

            $image = $license->image_id
                ? LicenseImage::withTrashed()->find($license->image_id)
                : null;

            if ($image && $image->trashed()) {
                $image->restore();
            }
        }

        $driver->restore();
        DashboardCache::forgetUserDashboards($driver->user_id);

        return response()->json([
            'message' => 'Driver profile restored successfully',
            'driver_profile' => $this->formatDriver(
                $driver->fresh()->load(['user', 'organization', 'licenseId.image', 'usersEmergencyContact.emergencyContact'])
            ),
        ], 200);
    }

    private function formatDriver(Driver $driver): array
    {
        $emergency = $driver->usersEmergencyContact?->emergencyContact;
        $license = $driver->licenseId;
        $image = $license?->image;
        $frontPath = $image?->image_front;
        $backPath = $image?->image_back;

        return [
            'id' => $driver->id,
            'user_id' => $driver->user_id,
            'user' => $driver->user ? [
                'id' => $driver->user->id,
                'first_name' => $driver->user->first_name,
                'last_name' => $driver->user->last_name,
                'middle_name' => $driver->user->middle_name,
                'email' => $driver->user->email,
            ] : null,
            'organization' => $driver->organization ? [
                'id' => $driver->organization->id,
                'name' => $driver->organization->name,
                'organization_type' => $driver->organization->organization_type,
            ] : null,
            'verification_status' => $license?->verification_status,
            'rejection_reason' => $license?->rejection_reason,
            'driver_license' => $license ? [
                'id' => $license->id,
                'number' => $license->license_id,
                'verification_status' => $license->verification_status,
                'rejection_reason' => $license->rejection_reason,
                'images' => [
                    'front_path' => $frontPath,
                    'front_url' => $frontPath ? MediaStorage::url($frontPath) : null,
                    'back_path' => $backPath,
                    'back_url' => $backPath ? MediaStorage::url($backPath) : null,
                ],
            ] : null,
            'emergency_contact' => $emergency ? [
                'id' => $emergency->id,
                'contact_name' => $emergency->contact_name,
                'contact_phone_number' => $emergency->contact_phone_number,
                'contact_relationship' => $emergency->contact_relationship,
            ] : null,
            'created_at' => $driver->created_at,
            'updated_at' => $driver->updated_at,
            'deleted_at' => $driver->deleted_at,
        ];
    }
}
