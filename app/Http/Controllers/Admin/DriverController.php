<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Driver;
use App\Models\Role;
use App\Support\MediaStorage;
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

        if ($driverId = $request->input('driver_id')) {
            $driver = Driver::with(['user', 'organization', 'licenseId.image', 'vehicles.vehicleType.vehicleImage', 'vehicles.plateNumber'])
                ->find($driverId);

            if (! $driver) {
                return response()->json([
                    'driver' => null,
                    'vehicles' => [],
                ], 404);
            }

            if ($currentUser->hasRole(Role::ORGANIZATION)
                && ! $currentUser->hasRole(Role::ADMIN)
                && ! $currentUser->hasRole(Role::SUPER_ADMIN)
                && $driver->organization
                && $driver->organization->owner_user_id !== $currentUser->id
            ) {
                return response()->json(['error' => 'Unauthorized'], 403);
            }

            $license = $driver->licenseId;
            $licenseImage = $license ? $license->image : null;

            $vehicles = $driver->vehicles->map(function ($vehicle) {
                $vehicleImage = $vehicle->vehicleType?->vehicleImage;

                $images = [
                    'Front' => $vehicleImage?->image_front,
                    'Back' => $vehicleImage?->image_back,
                    'Left' => $vehicleImage?->image_left,
                    'Right' => $vehicleImage?->image_right,
                ];

                $photoUrls = [];
                foreach ($images as $label => $path) {
                    $url = $path ? (\App\Support\MediaStorage::url($path)) : null;
                    if ($url) {
                        $photoUrls[] = [
                            'label' => $label,
                            'url' => $url,
                        ];
                    }
                }

                return [
                    'id' => $vehicle->id,
                    'plate_number' => optional($vehicle->plateNumber)->plate_number ?? '—',
                    'vehicle_type' => optional($vehicle->vehicleType)->vehicle_type ?? '—',
                    'status' => $vehicle->status,
                    'verification_status' => $vehicle->verification_status,
                    'photos' => $photoUrls,
                ];
            })->values();

            return response()->json([
                'driver' => [
                    'id' => $driver->id,
                    'name' => trim(($driver->user?->first_name ?? '') . ' ' . ($driver->user?->last_name ?? '')),
                    'license_id' => $license?->license_id,
                    'license_front' => $licenseImage && $licenseImage->image_front
                        ? ($licenseImage->image_front_url ?? MediaStorage::url($licenseImage->image_front))
                        : null,
                    'license_back' => $licenseImage && $licenseImage->image_back
                        ? ($licenseImage->image_back_url ?? MediaStorage::url($licenseImage->image_back))
                        : null,
                ],
                'vehicles' => $vehicles,
            ]);
        }

        $query = Driver::with(['user', 'organization', 'licenseId.image', 'vehicles.vehicleType.vehicleImage', 'vehicles.plateNumber']);

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
                $q->whereHas('licenseId', function ($lq) use ($search) {
                    $lq->where('license_id', 'like', "%{$search}%");
                })
                  ->orWhereHas('user', function ($uq) use ($search) {
                      $uq->where('first_name', 'like', "%{$search}%")
                        ->orWhere('last_name', 'like', "%{$search}%")
                        ->orWhere('email', 'like', "%{$search}%");
                  });
            });
        }

        if ($status = $request->input('status')) {
            if ($status === 'pending') {
                $status = 'unverified';
            }

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