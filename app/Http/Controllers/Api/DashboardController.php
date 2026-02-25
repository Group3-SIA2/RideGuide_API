<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Driverprofile;
use App\Models\Otp;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
   
    // ADMIN DASHBOARD
   
    public function adminDashboard(): JsonResponse
    {
        $user = auth()->user();

        if (! $user) {
            return response()->json(['error' => 'Unauthenticated'], 401);
        }

        // Only admin can access
        if ($user->role->name !== 'admin') {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        //  User Stats 
        $totalUsers        = User::count();
        $totalAdmins       = User::whereHas('role', fn($q) => $q->where('name', 'admin'))->count();
        $totalDrivers      = User::whereHas('role', fn($q) => $q->where('name', 'driver'))->count();
        $totalCommuters    = User::whereHas('role', fn($q) => $q->where('name', 'commuter'))->count();
        $verifiedUsers     = User::whereNotNull('email_verified_at')->count();
        $unverifiedUsers   = User::whereNull('email_verified_at')->count();
        $newUsersToday     = User::whereDate('created_at', today())->count();
        $newUsersThisWeek  = User::whereBetween('created_at', [now()->startOfWeek(), now()->endOfWeek()])->count();
        $newUsersThisMonth = User::whereMonth('created_at', now()->month)
                                 ->whereYear('created_at', now()->year)
                                 ->count();
        $deletedUsers      = User::onlyTrashed()->count();

        //  Driver Profile Stats
        $totalDriverProfiles   = Driverprofile::count();
        $verifiedDrivers       = Driverprofile::where('verification_status', 'verified')->count();
        $unverifiedDrivers     = Driverprofile::where('verification_status', 'unverified')->count();
        $rejectedDrivers       = Driverprofile::where('verification_status', 'rejected')->count();
        $deletedDriverProfiles = Driverprofile::onlyTrashed()->count();

        // OTP Stats
        $totalOtpsToday = Otp::whereDate('created_at', today())->count();
        $expiredOtps    = Otp::where('expires_at', '<', now())->whereNull('used_at')->count();
        $usedOtps       = Otp::whereNotNull('used_at')->count();

        // Recent Registrations (last 5 users)
        $recentUsers = User::with('role')
            ->latest()
            ->take(5)
            ->get()
            ->map(fn($u) => [
                'id'             => $u->id,
                'name'           => trim("{$u->first_name} {$u->middle_name} {$u->last_name}"),
                'email'          => $u->email,
                'role'           => $u->role->name ?? 'N/A',
                'email_verified' => ! is_null($u->email_verified_at),
                'registered_at'  => $u->created_at->toDateTimeString(),
            ]);

        //Recent Driver Applications (last 5)
        $recentDriverApplications = Driverprofile::with('user')
            ->latest()
            ->take(5)
            ->get()
            ->map(fn($d) => [
                'id'                  => $d->id,
                'driver_name'         => $d->user
                                            ? trim("{$d->user->first_name} {$d->user->last_name}")
                                            : 'N/A',
                'license_number'      => $d->license_number,
                'franchise_number'    => $d->franchise_number,
                'verification_status' => $d->verification_status,
                'applied_at'          => $d->created_at->toDateTimeString(),
            ]);

        return response()->json([
            'success' => true,
            'data'    => [
                'users' => [
                    'total'          => $totalUsers,
                    'admins'         => $totalAdmins,
                    'drivers'        => $totalDrivers,
                    'commuters'      => $totalCommuters,
                    'verified'       => $verifiedUsers,
                    'unverified'     => $unverifiedUsers,
                    'new_today'      => $newUsersToday,
                    'new_this_week'  => $newUsersThisWeek,
                    'new_this_month' => $newUsersThisMonth,
                    'deleted'        => $deletedUsers,
                ],
                'driver_profiles' => [
                    'total'      => $totalDriverProfiles,
                    'verified'   => $verifiedDrivers,
                    'unverified' => $unverifiedDrivers,
                    'rejected'   => $rejectedDrivers,
                    'deleted'    => $deletedDriverProfiles,
                ],
                'otps' => [
                    'sent_today' => $totalOtpsToday,
                    'expired'    => $expiredOtps,
                    'used'       => $usedOtps,
                ],
                'recent_registrations'       => $recentUsers,
                'recent_driver_applications' => $recentDriverApplications,
            ],
        ], 200);
    }

 
    // DRIVER DASHBOARD

    public function driverDashboard(): JsonResponse
    {
        $user = auth()->user();

        if (! $user) {
            return response()->json(['error' => 'Unauthenticated'], 401);
        }

        // Only driver can access
        if ($user->role->name !== 'driver') {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        //  Driver Profile 
        $driverProfile = Driverprofile::where('user_id', $user->id)->first();

        if (! $driverProfile) {
            return response()->json(['error' => 'Driver profile not found'], 404);
        }

        //  OTP Stats (for this driver) 
        $totalOtpsToday = Otp::where('user_id', $user->id)
                              ->whereDate('created_at', today())
                              ->count();
        $expiredOtps    = Otp::where('user_id', $user->id)
                              ->where('expires_at', '<', now())
                              ->whereNull('used_at')
                              ->count();
        $usedOtps       = Otp::where('user_id', $user->id)
                              ->whereNotNull('used_at')
                              ->count();

        return response()->json([
            'success' => true,
            'data'    => [
                'profile' => [
                    'id'                  => $driverProfile->id,
                    'name'                => trim("{$user->first_name} {$user->middle_name} {$user->last_name}"),
                    'email'               => $user->email,
                    'email_verified'      => ! is_null($user->email_verified_at),
                    'license_number'      => $driverProfile->license_number,
                    'franchise_number'    => $driverProfile->franchise_number,
                    'verification_status' => $driverProfile->verification_status,
                    'member_since'        => $user->created_at->toDateTimeString(),
                ],
                'otps' => [
                    'sent_today' => $totalOtpsToday,
                    'expired'    => $expiredOtps,
                    'used'       => $usedOtps,
                ],
            ],
        ], 200);
    }

   
    // USER (COMMUTER) DASHBOARD
 
    public function userDashboard(): JsonResponse
    {
        $user = auth()->user();

        if (! $user) {
            return response()->json(['error' => 'Unauthenticated'], 401);
        }

        // Only commuter can access
        if ($user->role->name !== 'commuter') {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        //  OTP Stats (for this user) 
        $totalOtpsToday = Otp::where('user_id', $user->id)
                              ->whereDate('created_at', today())
                              ->count();
        $expiredOtps    = Otp::where('user_id', $user->id)
                              ->where('expires_at', '<', now())
                              ->whereNull('used_at')
                              ->count();
        $usedOtps       = Otp::where('user_id', $user->id)
                              ->whereNotNull('used_at')
                              ->count();

        return response()->json([
            'success' => true,
            'data'    => [
                'profile' => [
                    'id'             => $user->id,
                    'name'           => trim("{$user->first_name} {$user->middle_name} {$user->last_name}"),
                    'email'          => $user->email,
                    'email_verified' => ! is_null($user->email_verified_at),
                    'role'           => $user->role->name ?? 'N/A',
                    'member_since'   => $user->created_at->toDateTimeString(),
                ],
                'otps' => [
                    'sent_today' => $totalOtpsToday,
                    'expired'    => $expiredOtps,
                    'used'       => $usedOtps,
                ],
            ],
        ], 200);
    }
}