<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Driverprofile;
use App\Models\Feedback;
use App\Models\Role;
use App\Models\UserProfile;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
   
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

        // Total Users
        $totalVerifiedUsers       = User::whereNotNull('email_verified_at')->count();
        $totalAdmins      = User::whereHas('role', fn($q) => $q->where('name', 'admin'))->count();
        $totalDrivers     = User::whereHas('role', fn($q) => $q->where('name', 'driver'))->count();
        $totalCommuters   = User::whereHas('role', fn($q) => $q->where('name', 'commuter'))->count();

        // Total Verified 
        $totalDriverProfiles = Driverprofile::count();
        $getAllDriverProfiles = Driverprofile::all();

        // get own credentials
        $getCredentials = User::where('id', $user->id)->first();
    

        // teerminals
        // $totalTerminals = Terminals::count();
        // $getAllTerminals = Terminals::all();


        

        return response()->json([
            'success' => true,
            'data'    => [
                'users' => [
                    'total_verified_users' => $totalVerifiedUsers,
                    'total_admins' => $totalAdmins,
                    'total_drivers' => $totalDrivers,
                    'total_commuters' => $totalCommuters,
                ],
                'admin_credentials' => $getCredentials,
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

        $getUser = User::where('id', $user->id)->first();
        $getUserProfile = UserProfile::where('user_id', $user->id)->first();
        $getDriversProfile = Driverprofile::where('user_id', $user->id)->first();
        //$getFeedbacks = Feedback::where('driver_id', $driverProfile->id)->get();
        //$getTerminals = Terminals::where('driver_id', $driverProfile->id)->get();
        //$getDriverRoutes = DriverRoutes::where('driver_id', $driverProfile->id)->get();
        //$getDriverVehiclesType = DriverVehicles::where('driver_id', $driverProfile->id)->get();
        //$getDriverRouteTerminals = DriverRouteTerminals::where('driver_id', $driverProfile->id)->get();
        //$getRouteStops = RouteStops::where('driver_id', $driverProfile->id)->get();

        return response()->json([
            'success' => true,
            'data'    => [
                'user' => $getUser,
                'profile' => $getUserProfile,
                'driver_profile' => $getDriversProfile,
            ],
        ], 200);
    }

   
    // USER (COMMUTER) DASHBOARD
 
    public function commuterDashboard(): JsonResponse
    {
        $user = auth()->user();

        if (! $user) {
            return response()->json(['error' => 'Unauthenticated'], 401);
        }

        // Only commuter can access
        if ($user->role->name !== 'commuter') {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        $userProfile = UserProfile::where('user_id', $user->id)->first();
        if (! $userProfile) {
            return response()->json(['error' => 'User profile not found'], 404);
        }
        
        $getUser = User::where('id', $user->id)->first();
        $getUserProfile = UserProfile::where('user_id', $user->id)->first();
        //$getAllTerminals = Terminals::all();
        $getAllDriverProfiles = Driverprofile::all();
        //$getAllFeedbacks = Feedback::all();

        return response()->json([
            'success' => true,
            'data'    => [
                'profile' => $getUser,
                'user_profile' => $getUserProfile,
                //'terminals' => $getAllTerminals,
                'drivers'   => $getAllDriverProfiles,
                //'feedbacks' => $getAllFeedbacks,
            ],
        ], 200);
    }
}