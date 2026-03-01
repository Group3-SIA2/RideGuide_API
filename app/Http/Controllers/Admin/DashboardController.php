<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Commuter;
use App\Models\Driver;
use App\Models\User;
use Illuminate\Http\Request;
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
    public function index(): View
    {
        $totalVerifiedUsers = User::whereNotNull('email_verified_at')->count();
        $totalAdmins        = User::whereHas('role', fn ($q) => $q->where('name', 'admin'))->count();
        $totalDrivers       = User::whereHas('role', fn ($q) => $q->where('name', 'driver'))->count();
        $totalCommuters     = User::whereHas('role', fn ($q) => $q->where('name', 'commuter'))->count();
        $totalDriverProfiles = Driver::count();

        $recentUsers = User::with('role')
            ->whereNotNull('email_verified_at')
            ->latest()
            ->take(5)
            ->get();

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
