<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Commuter;
use App\Models\Driver;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\View\View;

class DashboardController extends Controller
{
    /**
     * Create a new controller instance.
     */
    public function __construct()
    {
        $this->middleware('auth');
    }

    /**
     * Display the admin dashboard.
     */
    public function index(Request $request): View|JsonResponse
    {
        $this->authorizePermissions($request, 'view_admin_dashboard');

        $totalVerifiedUsers = User::whereNotNull('email_verified_at')->count();
        $totalActiveUsers   = User::where('status', User::STATUS_ACTIVE)->count();
        $totalInactiveUsers = User::where('status', User::STATUS_INACTIVE)->count();
        $totalSuspendedUsers = User::where('status', User::STATUS_SUSPENDED)->count();
        $totalAdmins        = User::whereHas('roles', fn ($q) => $q->where('name', 'admin'))->count();
        $totalSuperAdmins   = User::whereHas('roles', fn ($q) => $q->where('name', 'super_admin'))->count();
        $totalDrivers       = User::whereHas('roles', fn ($q) => $q->where('name', 'driver'))->count();
        $totalCommuters     = User::whereHas('roles', fn ($q) => $q->where('name', 'commuter'))->count();
        $totalDriverProfiles = Driver::count();

        $recentQuery = User::with('roles')
            ->whereNotNull('email_verified_at');

        if ($search = $request->input('search')) {
            $recentQuery->where(function ($q) use ($search) {
                $q->where('first_name', 'like', "%{$search}%")
                  ->orWhere('last_name', 'like', "%{$search}%")
                  ->orWhere('email', 'like', "%{$search}%");
            });
        }

        if ($role = $request->input('role')) {
            $recentQuery->whereHas('roles', fn ($q) => $q->where('name', $role));
        }

        $recentUsers = $recentQuery->latest()->take(10)->get();

        if ($request->ajax()) {
            return response()->json([
                'rows'  => view('admin.dashboard._recent_rows', compact('recentUsers'))->render(),
                'total' => $recentUsers->count(),
            ]);
        }

        return view('admin.dashboard', compact(
            'totalVerifiedUsers',
            'totalActiveUsers',
            'totalInactiveUsers',
            'totalSuspendedUsers',
            'totalAdmins',
            'totalDrivers',
            'totalCommuters',
            'totalSuperAdmins',
            'totalDriverProfiles',
            'recentUsers',
        ));
    }
}
