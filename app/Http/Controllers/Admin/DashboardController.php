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
        $totalVerifiedUsers = User::whereNotNull('email_verified_at')->count();
        $totalAdmins        = User::whereHas('role', fn ($q) => $q->where('name', 'admin'))->count();
        $totalDrivers       = User::whereHas('role', fn ($q) => $q->where('name', 'driver'))->count();
        $totalCommuters     = User::whereHas('role', fn ($q) => $q->where('name', 'commuter'))->count();
        $totalDriverProfiles = Driver::count();

        $recentQuery = User::with('role')
            ->whereNotNull('email_verified_at');

        if ($search = $request->input('search')) {
            $recentQuery->where(function ($q) use ($search) {
                $q->where('first_name', 'like', "%{$search}%")
                  ->orWhere('last_name', 'like', "%{$search}%")
                  ->orWhere('email', 'like', "%{$search}%");
            });
        }

        if ($role = $request->input('role')) {
            $recentQuery->whereHas('role', fn ($q) => $q->where('name', $role));
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
            'totalAdmins',
            'totalDrivers',
            'totalCommuters',
            'totalDriverProfiles',
            'recentUsers',
        ));
    }
}
