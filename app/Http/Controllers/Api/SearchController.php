<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Commuter;
use App\Models\Driver;
use App\Models\LicenseId;
use App\Support\InputValidation;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class SearchController extends Controller
{
    /*
        Endpoint: /api/search/drivers
        Query Params: search, verification_status, sort_by, sort_order
    */
    public function searchDrivers(Request $request): JsonResponse
    {
        $user = auth()->user();

        if (! $user) {
            return response()->json(['error' => 'Unauthenticated'], 401);
        }

        $roleName = $user->role?->name;

        // Only admin and commuter can search drivers
        if (! in_array($roleName, ['admin', 'commuter'])) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized. Drivers cannot search other drivers.',
            ], 403);
        }

        $validated = $request->validate([
            'search' => InputValidation::safeSearchRules(120),
            'verification_status' => ['nullable', Rule::in([
                LicenseId::VERIFICATION_STATUS_UNVERIFIED,
                LicenseId::VERIFICATION_STATUS_VERIFIED,
                LicenseId::VERIFICATION_STATUS_REJECTED,
            ])],
            'sort_by' => ['nullable', Rule::in(['license_number', 'franchise_number', 'verification_status', 'created_at'])],
            'sort_order' => ['nullable', Rule::in(['asc', 'desc'])],
        ]);

        $query = Driver::with('user', 'licenseId');

        // Commuters can only see verified drivers
        if ($roleName === 'commuter') {
            $query->whereHas('licenseId', function ($builder) {
                $builder->where('verification_status', LicenseId::VERIFICATION_STATUS_VERIFIED);
            });
        }

        // Search by license number, franchise number, or driver name/email
        if (! empty($validated['search'])) {
            $search = $validated['search'];
            $query->where(function ($q) use ($search) {
                $q->where('license_number', 'LIKE', "%{$search}%")
                    ->orWhere('franchise_number', 'LIKE', "%{$search}%")
                    ->orWhereHas('user', function ($q) use ($search) {
                        $q->where('first_name', 'LIKE', "%{$search}%")
                            ->orWhere('last_name', 'LIKE', "%{$search}%")
                            ->orWhere('email', 'LIKE', "%{$search}%");
                    });
            });
        }

        // Filter by verification status (admin only)
        $verificationStatus = $validated['verification_status'] ?? null;
        if ($roleName === 'admin' && $verificationStatus !== null) {
            $query->whereHas('licenseId', function ($builder) use ($verificationStatus) {
                $builder->where('verification_status', $verificationStatus);
            });
        }

        // Sort
        $sortBy = $validated['sort_by'] ?? 'created_at';
        $sortOrder = $validated['sort_order'] ?? 'desc';
        $allowedSorts = ['license_number', 'franchise_number', 'verification_status', 'created_at'];

        if (in_array($sortBy, $allowedSorts)) {
            $direction = $sortOrder === 'asc' ? 'asc' : 'desc';

            if ($sortBy === 'verification_status') {
                $query->orderBy(
                    LicenseId::select('verification_status')
                        ->whereColumn('license_id.id', 'driver.driver_license_id'),
                    $direction
                );
            } else {
                $query->orderBy($sortBy, $direction);
            }
        }

        $drivers = $query->get();

        return response()->json([
            'success' => true,
            'data' => $drivers->map(fn ($driver) => $this->formatDriver($driver, $roleName)),
            'total' => $drivers->count(),
        ]);
    }

    /*
        Endpoint: /api/search/commuters
        Query Params: search, classification, sort_by, sort_order
    */
    public function searchCommuters(Request $request): JsonResponse
    {
        $user = auth()->user();

        if (! $user) {
            return response()->json(['error' => 'Unauthenticated'], 401);
        }

        $roleName = $user->role?->name;

        // Only admin and driver can search commuters
        if (! in_array($roleName, ['admin', 'driver'])) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized. Only admins and drivers can search commuters.',
            ], 403);
        }

        $validated = $request->validate([
            'search' => InputValidation::safeSearchRules(120),
            'classification' => ['nullable', Rule::in(['Regular', 'Student', 'Senior', 'PWD'])],
            'sort_by' => ['nullable', Rule::in(['created_at'])],
            'sort_order' => ['nullable', Rule::in(['asc', 'desc'])],
        ]);

        $query = Commuter::with('user', 'discount.classificationType');

        // Search by user name or email
        if (! empty($validated['search'])) {
            $search = $validated['search'];
            $query->whereHas('user', function ($q) use ($search) {
                $q->where('first_name', 'LIKE', "%{$search}%")
                    ->orWhere('last_name', 'LIKE', "%{$search}%")
                    ->orWhere('email', 'LIKE', "%{$search}%");
            });
        }

        // Filter by classification (through discount -> classificationType relationship)
        if (! empty($validated['classification'])) {
            $classification = $validated['classification'];
            if (in_array($classification, ['Regular', 'Student', 'Senior', 'PWD'])) {
                if ($classification === 'Regular') {
                    // Regular commuters have no discount record
                    $query->whereNull('discount_id');
                } else {
                    // Non-regular commuters: filter via the discount's classification type
                    $query->whereHas('discount.classificationType', function ($q) use ($classification) {
                        $q->where('classification_name', $classification);
                    });
                }
            }
        }

        // Sort
        $sortBy = $validated['sort_by'] ?? 'created_at';
        $sortOrder = $validated['sort_order'] ?? 'desc';
        $allowedSorts = ['created_at'];

        if (in_array($sortBy, $allowedSorts)) {
            $query->orderBy($sortBy, $sortOrder === 'asc' ? 'asc' : 'desc');
        }

        $commuters = $query->get();

        return response()->json([
            'success' => true,
            'data' => $commuters->map(fn ($commuter) => $this->formatCommuter($commuter, $roleName)),
            'total' => $commuters->count(),
        ]);
    }

    // Helper Method to format driver data based on viewer's role
    private function formatDriver(Driver $driver, string $viewerRole): array
    {
        $data = [
            'id' => $driver->id,
            'franchise_number' => $driver->franchise_number,
            'driver_name' => $driver->user
                ? $driver->user->first_name.' '.$driver->user->last_name
                : null,
        ];

        // Admin sees full details
        if ($viewerRole === 'admin') {
            $data['user_id'] = $driver->user_id;
            $data['license_number'] = $driver->license_number;
            $data['verification_status'] = $driver->verification_status;
            $data['email'] = $driver->user?->email;
            $data['created_at'] = $driver->created_at;
            $data['updated_at'] = $driver->updated_at;
        }

        return $data;
    }

    private function formatCommuter(Commuter $commuter, string $viewerRole): array
    {
        $classificationName = $commuter->discount?->classificationType?->classification_name ?? 'Regular';

        $data = [
            'id' => $commuter->id,
            'classification_name' => $classificationName,
            'commuter_name' => $commuter->user
                ? $commuter->user->first_name.' '.$commuter->user->last_name
                : null,
        ];

        // Admin sees full details
        if ($viewerRole === 'admin') {
            $data['user_id'] = $commuter->user_id;
            $data['email'] = $commuter->user?->email;
            $data['discount'] = $commuter->discount ? [
                'id' => $commuter->discount->id,
                'ID_number' => $commuter->discount->ID_number,
                'ID_image_path' => $commuter->discount->ID_image_path,
                'classification' => $commuter->discount->classificationType?->classification_name ?? 'Regular',
            ] : null;
            $data['created_at'] = $commuter->created_at;
            $data['updated_at'] = $commuter->updated_at;
        }

        return $data;
    }
}