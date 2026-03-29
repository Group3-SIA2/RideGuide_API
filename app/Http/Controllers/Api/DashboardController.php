<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Driver;
use App\Models\Feedback;
use App\Models\Role;
use App\Models\Commuter;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use App\Support\DashboardCache;
use App\Support\MediaStorage;

class DashboardController extends Controller
{

    private function formatUser(User $user): array
    {
        return [
            'id' => $user->id,
            'first_name' => $user->first_name,
            'last_name' => $user->last_name,
            'middle_name' => $user->middle_name,
            'birthdate' => $user->birthdate,
            'profile_picture' => $user->profile_picture,
            'email' => $user->email,
            'google_id' => $user->google_id,
            'facebook_id' => $user->facebook_id,
            'phone_number' => $user->phone_number,
            'email_verified_at' => $user->email_verified_at,
            'phone_verified_at' => $user->phone_verified_at,
            'created_at' => $user->created_at,
            'updated_at' => $user->updated_at,
            'deleted_at' => $user->deleted_at,
        ];
    }

    private function formatDriverProfile(?Driver $driver): ?array
    {
        if (! $driver) {
            return null;
        }

        $emergency = $driver->usersEmergencyContact?->emergencyContact;
        $license = $driver->licenseId;
        $image = $license?->image;
        $frontPath = $image?->image_front;
        $backPath = $image?->image_back;
        $joinedLicenseIdNumber = $driver->getAttribute('license_id_number');
        $joinedLicenseStatus = $driver->getAttribute('license_verification_status');
        $joinedLicenseRejectionReason = $driver->getAttribute('license_rejection_reason');
        $licenseIdNumber = $joinedLicenseIdNumber ?: $license?->license_id;
        $licenseVerificationStatus = $joinedLicenseStatus ?: $license?->verification_status;
        $licenseRejectionReason = $joinedLicenseRejectionReason ?: $license?->rejection_reason;

        return [
            'id' => $driver->id,
            'user_id' => $driver->user_id,
            'driver_license_id' => $driver->driver_license_id,
            'organization_id' => $driver->organization_id,
            'organization' => $driver->organization ? [
                'id' => $driver->organization->id,
                'name' => $driver->organization->name,
                'organization_type' => $driver->organization->organization_type,
            ] : null,
            'license_id_number' => $licenseIdNumber,
            // Keep this legacy key for older clients while exposing nested driver_license.
            'license_number' => $licenseIdNumber,
            'verification_status' => $licenseVerificationStatus,
            'rejection_reason' => $licenseRejectionReason,
            'license_verification_status' => $licenseVerificationStatus,
            'license_rejection_reason' => $licenseRejectionReason,
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

    private function loadDriverProfileForDashboard(string $userId): ?Driver
    {
        return Driver::query()
            ->leftJoin('license_id', 'driver.driver_license_id', '=', 'license_id.id')
            ->where('driver.user_id', $userId)
            ->select([
                'driver.*',
                'license_id.license_id as license_id_number',
                'license_id.verification_status as license_verification_status',
                'license_id.rejection_reason as license_rejection_reason',
            ])
            ->with(['organization.organizationType', 'licenseId.image', 'usersEmergencyContact.emergencyContact'])
            ->first();
    }

    private function formatCommuterProfile(?Commuter $commuter): ?array
    {
        if (! $commuter) {
            return null;
        }

        return [
            'id' => $commuter->id,
            'user_id' => $commuter->user_id,
            'discount_id' => $commuter->discount_id,
            'classification_name' => $commuter->discount?->classificationType?->classification_name ?? 'Regular',
            'discount' => $commuter->discount ? [
                'id' => $commuter->discount->id,
                'ID_number' => $commuter->discount->ID_number,
                'ID_image_id' => $commuter->discount->ID_image_id,
                'classification_type_id' => $commuter->discount->classification_type_id,
                'created_at' => $commuter->discount->created_at,
                'updated_at' => $commuter->discount->updated_at,
                'deleted_at' => $commuter->discount->deleted_at,
                'classification_type' => $commuter->discount->classificationType ? [
                    'id' => $commuter->discount->classificationType->id,
                    'classification_name' => $commuter->discount->classificationType->classification_name,
                    'created_at' => $commuter->discount->classificationType->created_at,
                    'updated_at' => $commuter->discount->classificationType->updated_at,
                    'deleted_at' => $commuter->discount->classificationType->deleted_at,
                ] : null,
            ] : null,
            'created_at' => $commuter->created_at,
            'updated_at' => $commuter->updated_at,
            'deleted_at' => $commuter->deleted_at,
        ];
    }

    private function resolveRoleNames(User $user): array
    {
        return $user->roles()->pluck('name')->map(fn (string $name) => strtolower($name))->values()->all();
    }

    private function unauthorizedForRole(array $roleNames, string $requiredRole): bool
    {
        return !in_array(strtolower($requiredRole), $roleNames, true);
    }

    private function baseResponsePayload(User $user, string $activeRole, array $roleNames): array
    {
        return [
            'user_id' => $user->id,
            'active_role' => strtolower($activeRole),
            'roles' => $roleNames,
        ];
    }

    private function buildUserDashboardData(User $user, string $activeRole, array $roleNames, ?Driver $driverProfile = null): array
    {
        $freshUser = User::findOrFail($user->id);
        $driverProfile = $driverProfile ?? $this->loadDriverProfileForDashboard($user->id);
        $commuterProfile = Commuter::with('discount.classificationType')
            ->where('user_id', $user->id)
            ->first();

        return array_merge(
            $this->baseResponsePayload($freshUser, $activeRole, $roleNames),
            [
                'user' => $this->formatUser($freshUser),
                'driver_profile' => $this->formatDriverProfile($driverProfile),
                'commuter_profile' => $this->formatCommuterProfile($commuterProfile),
            ]
        );
    }

    public function adminDashboard(): JsonResponse
    {
        $user = auth()->user();

        if (! $user) {
            return response()->json(['error' => 'Unauthenticated'], 401);
        }

        $roleNames = $this->resolveRoleNames($user);

        if ($this->unauthorizedForRole($roleNames, 'admin')) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        $cacheKey = DashboardCache::key($user->id, 'admin');

        $data = Cache::remember($cacheKey, DashboardCache::ttlSeconds(), function () use ($user, $roleNames) {
            $totalVerifiedUsers = User::whereNotNull('email_verified_at')->count();
            $totalAdmins = User::whereHas('roles', fn ($query) => $query->where('name', 'admin'))->count();
            $totalDrivers = User::whereHas('roles', fn ($query) => $query->where('name', 'driver'))->count();
            $totalCommuters = User::whereHas('roles', fn ($query) => $query->where('name', 'commuter'))->count();
            $getCredentials = User::where('id', $user->id)->first();

            return array_merge(
                $this->baseResponsePayload($user, 'admin', $roleNames),
                [
                    'users' => [
                        'total_verified_users' => $totalVerifiedUsers,
                        'total_admins' => $totalAdmins,
                        'total_drivers' => $totalDrivers,
                        'total_commuters' => $totalCommuters,
                    ],
                    'admin_credentials' => $getCredentials,
                ]
            );
        });

        return response()->json([
            'success' => true,
            'data' => $data,
        ], 200);
    }

 
    // DRIVER DASHBOARD

    public function driverDashboard(): JsonResponse
    {
        $user = auth()->user();

        if (! $user) {
            return response()->json(['error' => 'Unauthenticated'], 401);
        }

        $roleNames = $this->resolveRoleNames($user);

        if ($this->unauthorizedForRole($roleNames, 'driver')) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        $cacheKey = DashboardCache::key($user->id, 'driver');

        $data = Cache::remember($cacheKey, DashboardCache::ttlSeconds(), function () use ($user, $roleNames) {
            $driverProfile = $this->loadDriverProfileForDashboard($user->id);

            if (! $driverProfile) {
                return null;
            }

            return $this->buildUserDashboardData($user, 'driver', $roleNames, $driverProfile);
        });

        if ($data === null) {
            return response()->json(['error' => 'Driver profile not found'], 404);
        }

        return response()->json([
            'success' => true,
            'data' => $data,
        ], 200);
    }

   
    // USER (COMMUTER) DASHBOARD
 
    public function commuterDashboard(): JsonResponse
    {
        $user = auth()->user();

        if (! $user) {
            return response()->json(['error' => 'Unauthenticated'], 401);
        }

        $roleNames = $this->resolveRoleNames($user);

        if ($this->unauthorizedForRole($roleNames, 'commuter')) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        $cacheKey = DashboardCache::key($user->id, 'commuter');

        $data = Cache::remember($cacheKey, DashboardCache::ttlSeconds(), function () use ($user, $roleNames) {
            $commuterProfile = Commuter::with('user', 'discount.classificationType')
                ->where('user_id', $user->id)
                ->first();

            if (! $commuterProfile) {
                return null;
            }

            return $this->buildUserDashboardData($user, 'commuter', $roleNames);
        });

        if ($data === null) {
            return response()->json(['error' => 'User profile not found'], 404);
        }

        return response()->json([
            'success' => true,
            'data' => $data,
        ], 200);
    }
}