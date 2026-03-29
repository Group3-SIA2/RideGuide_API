<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Discount;
use App\Models\Driver;
use App\Models\LicenseId;
use App\Models\LicenseImage;
use App\Models\Role;
use App\Models\User;
use App\Models\vehicle as Vehicle;
use App\Support\MediaStorage;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class UserManagementController extends Controller
{
    private const RESTORABLE_ENTITIES = [
        'user' => User::class,
        'driver' => Driver::class,
        'vehicle' => Vehicle::class,
        'discount' => Discount::class,
    ];

    public function __construct()
    {
        $this->middleware('auth');
    }

    public function index(Request $request)
    {
        $this->authorizePermissions($request, 'manage_users');

        $stats = [
            'users' => [
                'active' => User::whereNotNull('email_verified_at')
                    ->where('status', User::STATUS_ACTIVE)
                    ->count(),
                'inactive' => User::whereNotNull('email_verified_at')
                    ->where('status', User::STATUS_INACTIVE)
                    ->count(),
                'suspended' => User::whereNotNull('email_verified_at')
                    ->where('status', User::STATUS_SUSPENDED)
                    ->count(),
            ],
            'drivers' => [
                'unverified' => Driver::whereHas('licenseId', function ($query) {
                    $query->where('verification_status', LicenseId::VERIFICATION_STATUS_UNVERIFIED);
                })->count(),
                'verified' => Driver::whereHas('licenseId', function ($query) {
                    $query->where('verification_status', LicenseId::VERIFICATION_STATUS_VERIFIED);
                })->count(),
                'rejected' => Driver::whereHas('licenseId', function ($query) {
                    $query->where('verification_status', LicenseId::VERIFICATION_STATUS_REJECTED);
                })->count(),
            ],
            'vehicles' => [
                'pending' => Vehicle::where('verification_status', Vehicle::VERIFICATION_PENDING)->count(),
                'verified' => Vehicle::where('verification_status', Vehicle::VERIFICATION_VERIFIED)->count(),
                'rejected' => Vehicle::where('verification_status', Vehicle::VERIFICATION_REJECTED)->count(),
            ],
            'discounts' => [
                'pending' => Discount::where('verification_status', Discount::VERIFICATION_PENDING)->count(),
                'verified' => Discount::where('verification_status', Discount::VERIFICATION_VERIFIED)->count(),
                'rejected' => Discount::where('verification_status', Discount::VERIFICATION_REJECTED)->count(),
            ],
        ];

        $userQuery = User::with('roles')->whereNotNull('email_verified_at');
        $search = $request->input('search');

        if ($search) {
            $userQuery->where(function ($query) use ($search) {
                $query->where('first_name', 'like', "%{$search}%")
                    ->orWhere('last_name', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%");
            });
        }

        $users = $userQuery
            ->latest()
            ->paginate(10, ['*'], 'users_page')
            ->withQueryString();

        $drivers = Driver::with(['user', 'licenseId.image'])
            ->latest()
            ->paginate(10, ['*'], 'drivers_page')
            ->withQueryString();

        $drivers->getCollection()->transform(function (Driver $driver) {
            $image = $driver->licenseId?->image;
            if ($image) {
                $image->setAttribute('image_front_url', $image->image_front ? MediaStorage::url($image->image_front) : null);
                $image->setAttribute('image_back_url', $image->image_back ? MediaStorage::url($image->image_back) : null);
            }
            return $driver;
        });

        $vehicles = Vehicle::with(['driver.user', 'vehicleType.vehicleImage', 'plateNumber'])
            ->latest()
            ->paginate(10, ['*'], 'vehicles_page')
            ->withQueryString();

        $vehicles->getCollection()->transform(function (Vehicle $vehicle) {
            $image = $vehicle->vehicleType?->vehicleImage;

            if ($image) {
                foreach (['front', 'back', 'left', 'right'] as $side) {
                    $path = $image->{"image_{$side}"};
                    $attribute = "image_{$side}_url";

                    $image->setAttribute($attribute, $path ? MediaStorage::url($path) : null);
                }
            }

            return $vehicle;
        });

        $discounts = Discount::with(['commuter.user', 'classificationType', 'idImage'])
            ->latest()
            ->paginate(10, ['*'], 'discounts_page')
            ->withQueryString();

        $discounts->getCollection()->transform(function (Discount $discount) {
            $image = $discount->idImage;
            if ($image) {
                $image->setAttribute('image_front_url', $image->image_front ? MediaStorage::url($image->image_front) : null);
                $image->setAttribute('image_back_url', $image->image_back ? MediaStorage::url($image->image_back) : null);
            }
            return $discount;
        });

        return view('admin.users.status-dashboard', [
            'users' => $users,
            'drivers' => $drivers,
            'vehicles' => $vehicles,
            'discounts' => $discounts,
            'stats' => $stats,
            'filters' => ['search' => $search],
            'userStatusOptions' => [
                User::STATUS_ACTIVE => 'Active',
                User::STATUS_INACTIVE => 'Inactive',
                User::STATUS_SUSPENDED => 'Suspended',
            ],
            'driverVerificationOptions' => [
                LicenseId::VERIFICATION_STATUS_UNVERIFIED => 'Unverified',
                LicenseId::VERIFICATION_STATUS_VERIFIED => 'Verified',
                LicenseId::VERIFICATION_STATUS_REJECTED => 'Rejected',
            ],
            'vehicleStatusOptions' => [
                Vehicle::STATUS_ACTIVE => 'Active',
                Vehicle::STATUS_INACTIVE => 'Inactive',
            ],
            'vehicleVerificationOptions' => [
                Vehicle::VERIFICATION_PENDING => 'Pending',
                Vehicle::VERIFICATION_VERIFIED => 'Verified',
                Vehicle::VERIFICATION_REJECTED => 'Rejected',
            ],
            'discountVerificationOptions' => [
                Discount::VERIFICATION_PENDING => 'Pending',
                Discount::VERIFICATION_VERIFIED => 'Verified',
                Discount::VERIFICATION_REJECTED => 'Rejected',
                Discount::VERIFICATION_EXPIRED => 'Expired',
            ],
        ]);
    }

    public function updateUserStatus(Request $request, User $user)
    {
        $this->authorizePermissions($request, 'manage_users');

        if ($user->hasRole(Role::SUPER_ADMIN)) {
            return redirect()->route($this->panelRouteName($request, 'user-status.index'))
                ->with('error', 'Cannot change status of Super Admin.');
        }

        $validated = $request->validate([
            'status' => ['required', Rule::in([User::STATUS_ACTIVE, User::STATUS_INACTIVE, User::STATUS_SUSPENDED])],
            'status_reason' => ['nullable', 'string', 'max:255'],
        ]);

        $statusChanged = $user->status !== $validated['status'];

        $user->update([
            'status' => $validated['status'],
            'status_reason' => $validated['status_reason'] ?? null,
            'status_changed_at' => $statusChanged ? now() : $user->status_changed_at,
        ]);

        return redirect()->route($this->panelRouteName($request, 'user-status.index'))
            ->with('success', 'User status updated successfully.');
    }

    public function updateDiscountStatus(Request $request, Discount $discount)
    {
        $this->authorizePermissions($request, 'manage_users');

        $validated = $request->validate([
            'verification_status' => ['required', Rule::in([
                Discount::VERIFICATION_PENDING,
                Discount::VERIFICATION_VERIFIED,
                Discount::VERIFICATION_EXPIRED,
                Discount::VERIFICATION_REJECTED,
            ])],
            'rejection_reason' => ['nullable', 'string', 'max:255'],
        ]);

        $discount->update([
            'verification_status' => $validated['verification_status'],
            'rejection_reason' => $validated['rejection_reason'] ?? null,
        ]);

        return redirect()->route($this->panelRouteName($request, 'user-status.index'))
            ->with('success', 'Discount verification updated successfully.');
    }

    public function updateVehicleStatus(Request $request, Vehicle $vehicle)
    {
        $this->authorizePermissions($request, 'manage_users');

        $validated = $request->validate([
            'status' => ['required', Rule::in([Vehicle::STATUS_INACTIVE, Vehicle::STATUS_ACTIVE])],
            'verification_status' => ['required', Rule::in([
                Vehicle::VERIFICATION_PENDING,
                Vehicle::VERIFICATION_VERIFIED,
                Vehicle::VERIFICATION_REJECTED,
            ])],
            'rejection_reason' => ['nullable', 'string', 'max:255'],
        ]);

        $vehicle->update([
            'status' => $validated['status'],
            'verification_status' => $validated['verification_status'],
            'rejection_reason' => $validated['rejection_reason'] ?? null,
        ]);

        return redirect()->route($this->panelRouteName($request, 'user-status.index'))
            ->with('success', 'Vehicle status updated successfully.');
    }

    public function updateDriverStatus(Request $request, Driver $driver)
    {
        $this->authorizePermissions($request, 'manage_users');

        $validated = $request->validate([
            'verification_status' => ['required', Rule::in([
                LicenseId::VERIFICATION_STATUS_UNVERIFIED,
                LicenseId::VERIFICATION_STATUS_VERIFIED,
                LicenseId::VERIFICATION_STATUS_REJECTED,
            ])],
            'rejection_reason' => ['nullable', 'string', 'max:255'],
        ]);

        $license = $driver->loadMissing('licenseId')->licenseId;

        if (! $license) {
            return redirect()->route($this->panelRouteName($request, 'user-status.index'))
                ->with('error', 'Driver license record not found, unable to update status.');
        }

        $license->update([
            'verification_status' => $validated['verification_status'],
            'rejection_reason' => $validated['verification_status'] === LicenseId::VERIFICATION_STATUS_REJECTED
                ? ($validated['rejection_reason'] ?? null)
                : null,
        ]);

        return redirect()->route($this->panelRouteName($request, 'user-status.index'))
            ->with('success', 'Driver verification updated successfully.');
    }

    public function restoreUsers(Request $request)
    {
        $this->authorizePermissions($request, 'manage_users');

        User::onlyTrashed()->restore();

        return redirect()->route($this->panelRouteName($request, 'user-status.index'))
            ->with('success', 'All deleted users have been restored.');
    }

     public function restoreDrivers(Request $request)
    {
        $this->authorizePermissions($request, 'manage_users');

        Driver::onlyTrashed()->get()->each(function (Driver $driver) {
            $this->restoreDriverLicenseData($driver);
            $driver->restore();
        });

        return redirect()->route($this->panelRouteName($request, 'user-status.index'))
            ->with('success', 'All deleted drivers have been restored.');
    }

     public function restoreVehicles(Request $request)
    {
        $this->authorizePermissions($request, 'manage_users');

        Vehicle::onlyTrashed()->restore();

        return redirect()->route($this->panelRouteName($request, 'user-status.index'))
            ->with('success', 'All deleted vehicles have been restored.');
     }

     public function restoreDiscounts(Request $request)
     {
         $this->authorizePermissions($request, 'manage_users');

         Discount::onlyTrashed()->restore();

         return redirect()->route($this->panelRouteName($request, 'user-status.index'))
             ->with('success', 'All deleted discounts have been restored.');
      }

    public function searchDeletedRecords(Request $request)
    {
        $this->authorizePermissions($request, 'manage_users');

        $validated = $request->validate([
            'entity' => ['required', Rule::in(array_keys(self::RESTORABLE_ENTITIES))],
            'query' => ['nullable', 'string', 'max:255'],
        ]);

        $entity = $validated['entity'];
        $query = $validated['query'] ?? '';

        $results = match ($entity) {
            'user' => $this->searchDeletedUsers($query),
            'driver' => $this->searchDeletedDrivers($query),
            'vehicle' => $this->searchDeletedVehicles($query),
            'discount' => $this->searchDeletedDiscounts($query),
            default => [],
        };

        return response()->json([
            'entity' => $entity,
            'results' => $results,
        ]);
    }

    public function restoreRecord(Request $request)
    {
        $this->authorizePermissions($request, 'manage_users');

        $validated = $request->validate([
            'entity' => ['required', Rule::in(array_keys(self::RESTORABLE_ENTITIES))],
            'id' => ['required', 'string', 'max:255'],
        ]);

        $modelClass = self::RESTORABLE_ENTITIES[$validated['entity']] ?? null;

        if (!$modelClass) {
            return response()->json(['message' => 'Invalid entity.'], 422);
        }

        $record = $modelClass::onlyTrashed()->find($validated['id']);

        if (!$record) {
            return response()->json(['message' => 'Record not found or already restored.'], 404);
        }

        if ($record instanceof Driver) {
            $this->restoreDriverLicenseData($record);
        }

        $record->restore();

        return response()->json([
            'message' => ucfirst($validated['entity']) . ' restored successfully.',
        ]);
    }

    private function restoreDriverLicenseData(Driver $driver): void
    {
        if (! $driver->driver_license_id) {
            return;
        }

        $license = LicenseId::withTrashed()->find($driver->driver_license_id);

        if (!$license) {
            return;
        }

        if ($license->trashed()) {
            $license->restore();
        }

        if ($license->image_id) {
            $image = LicenseImage::withTrashed()->find($license->image_id);
            if ($image && $image->trashed()) {
                $image->restore();
            }
        }
    }

    private function searchDeletedUsers(?string $query): array
    {
        return User::onlyTrashed()
            ->when($query, function ($builder) use ($query) {
                $builder->where(function ($q) use ($query) {
                    $q->where('first_name', 'like', "%{$query}%")
                        ->orWhere('last_name', 'like', "%{$query}%")
                        ->orWhere('email', 'like', "%{$query}%");
                });
            })
            ->orderByDesc('deleted_at')
            ->limit(20)
            ->get()
            ->map(function (User $user) {
                return [
                    'id' => $user->id,
                    'title' => trim($user->first_name . ' ' . $user->last_name) ?: 'Unnamed User',
                    'subtitle' => $user->email,
                    'deleted_at' => optional($user->deleted_at)->format('M d, Y h:i A'),
                ];
            })
            ->toArray();
    }

    private function searchDeletedDrivers(?string $query): array
    {
        return Driver::onlyTrashed()
            ->with('user')
            ->when($query, function ($builder) use ($query) {
                $builder->where(function ($q) use ($query) {
                    $q->where('license_number', 'like', "%{$query}%")
                        ->orWhereHas('user', function ($userQuery) use ($query) {
                            $userQuery->where('first_name', 'like', "%{$query}%")
                                ->orWhere('last_name', 'like', "%{$query}%")
                                ->orWhere('email', 'like', "%{$query}%");
                        });
                });
            })
            ->orderByDesc('deleted_at')
            ->limit(20)
            ->get()
            ->map(function (Driver $driver) {
                $name = trim(($driver->user?->first_name ?? '') . ' ' . ($driver->user?->last_name ?? ''));

                return [
                    'id' => $driver->id,
                    'title' => $name ?: 'Driver #' . $driver->id,
                    'subtitle' => 'License: ' . ($driver->license_number ?? 'N/A'),
                    'deleted_at' => optional($driver->deleted_at)->format('M d, Y h:i A'),
                ];
            })
            ->toArray();
    }

    private function searchDeletedVehicles(?string $query): array
    {
        return Vehicle::onlyTrashed()
            ->with(['driver.user', 'plateNumber'])
            ->when($query, function ($builder) use ($query) {
                $builder->where(function ($q) use ($query) {
                    $q->whereHas('plateNumber', function ($plateQuery) use ($query) {
                        $plateQuery->where('plate_number', 'like', "%{$query}%");
                    })
                        ->orWhereHas('driver.user', function ($userQuery) use ($query) {
                            $userQuery->where('first_name', 'like', "%{$query}%")
                                ->orWhere('last_name', 'like', "%{$query}%");
                        });
                });
            })
            ->orderByDesc('deleted_at')
            ->limit(20)
            ->get()
            ->map(function (Vehicle $vehicle) {
                $driverUser = $vehicle->driver?->user;

                return [
                    'id' => $vehicle->id,
                    'title' => 'Plate: ' . (optional($vehicle->plateNumber)->plate_number ?? 'N/A'),
                    'subtitle' => 'Driver: ' . trim(($driverUser->first_name ?? '') . ' ' . ($driverUser->last_name ?? '')),
                    'deleted_at' => optional($vehicle->deleted_at)->format('M d, Y h:i A'),
                ];
            })
            ->toArray();
    }

    private function searchDeletedDiscounts(?string $query): array
    {
        return Discount::onlyTrashed()
            ->with(['commuter.user', 'classificationType'])
            ->when($query, function ($builder) use ($query) {
                $builder->where(function ($q) use ($query) {
                    $q->where('ID_number', 'like', "%{$query}%")
                        ->orWhereHas('commuter.user', function ($userQuery) use ($query) {
                            $userQuery->where('first_name', 'like', "%{$query}%")
                                ->orWhere('last_name', 'like', "%{$query}%")
                                ->orWhere('email', 'like', "%{$query}%");
                        })
                        ->orWhereHas('classificationType', function ($classificationQuery) use ($query) {
                            $classificationQuery->where('name', 'like', "%{$query}%");
                        });
                });
            })
            ->orderByDesc('deleted_at')
            ->limit(20)
            ->get()
            ->map(function (Discount $discount) {
                $commuterUser = $discount->commuter?->user;

                return [
                    'id' => $discount->id,
                    'title' => trim(($commuterUser->first_name ?? '') . ' ' . ($commuterUser->last_name ?? '')) ?: 'Discount #' . $discount->id,
                    'subtitle' => 'ID #: ' . ($discount->ID_number ?? 'N/A'),
                    'meta' => optional($discount->classificationType)->name,
                    'deleted_at' => optional($discount->deleted_at)->format('M d, Y h:i A'),
                ];
            })
            ->toArray();
    }

    private function panelRouteName(Request $request, string $suffix): string
    {
        $routeName = (string) optional($request->route())->getName();

        if (str_starts_with($routeName, 'super-admin.')) {
            return 'super-admin.' . $suffix;
        }

        return 'admin.' . $suffix;
    }
}