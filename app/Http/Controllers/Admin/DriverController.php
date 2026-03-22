<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Driver;
use App\Models\Role;
use Illuminate\Http\Request;

class DriverController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth');
    }

    public function index(Request $request)
    {
        $this->authorizePermissions($request, 'view_drivers', 'manage_drivers');
        $currentUser = $request->user();

        $query = Driver::with(['user', 'organization', 'licenseId.image']);

        // Organization managers can only view drivers assigned to organizations they own.
        if ($currentUser->hasRole(Role::ORGANIZATION)
            && !$currentUser->hasRole(Role::ADMIN)
            && !$currentUser->hasRole(Role::SUPER_ADMIN)
        ) {
            $query->whereHas('organization', function ($organizationQuery) use ($currentUser) {
                $organizationQuery->where('owner_user_id', $currentUser->id);
            });
        }

        if ($search = $request->input('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('license_number', 'like', "%{$search}%")
                  ->orWhereHas('user', function ($uq) use ($search) {
                      $uq->where('first_name', 'like', "%{$search}%")
                        ->orWhere('last_name', 'like', "%{$search}%")
                        ->orWhere('email', 'like', "%{$search}%");
                  });
            });
        }

        if ($status = $request->input('status')) {
            $query->whereHas('licenseId', function ($q) use ($status) {
                $q->where('verification_status', $status);
            });
        }

        $drivers = $query->latest()->paginate(15)->withQueryString();

        if ($request->ajax()) {
            return response()->json([
                'rows'       => view('admin.drivers._rows', compact('drivers'))->render(),
                'pagination' => $drivers->hasPages() ? (string) $drivers->links() : '',
                'total'      => $drivers->total(),
            ]);
        }

        return view('admin.drivers.index', compact('drivers'));
    }
}
