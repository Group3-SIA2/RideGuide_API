<?php

namespace App\Http\Controllers\Api;

use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use App\Http\Controllers\Controller;
use App\Models\Driver;
use App\Models\Commuter;

class SearchController extends Controller
{
    /**
     * Search & filter drivers.
     *
     * GET /api/search/drivers
     * 
     * Access:
     *   - Admin     : full search with all filters
     *   - Commuter  : search drivers (verified only, limited info)
     *   - Driver    : CANNOT search other drivers
     *
     * Query params: search, verification_status (admin only), sort_by, sort_order
     */
    public function searchDrivers(Request $request): JsonResponse
    {
        $user = auth()->user();

        if (!$user) {
            return response()->json(['error' => 'Unauthenticated'], 401);
        }

        $roleName = $user->role?->name;

        // Only admin and commuter can search drivers
        if (!in_array($roleName, ['admin', 'commuter'])) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized. Drivers cannot search other drivers.',
            ], 403);
        }

        $query = Driver::with('user');

        // Commuters can only see verified drivers
        if ($roleName === 'commuter') {
            $query->where('verification_status', 'verified');
        }

        // Search by license number, franchise number, or driver name/email
        if ($request->filled('search')) {
            $search = $request->input('search');
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
        if ($roleName === 'admin' && $request->filled('verification_status')) {
            $status = $request->input('verification_status');
            if (in_array($status, ['unverified', 'verified', 'rejected'])) {
                $query->where('verification_status', $status);
            }
        }

        // Sort
        $sortBy = $request->input('sort_by', 'created_at');
        $sortOrder = $request->input('sort_order', 'desc');
        $allowedSorts = ['license_number', 'franchise_number', 'verification_status', 'created_at'];

        if (in_array($sortBy, $allowedSorts)) {
            $query->orderBy($sortBy, $sortOrder === 'asc' ? 'asc' : 'desc');
        }

        $drivers = $query->get();

        return response()->json([
            'success' => true,
            'data'    => $drivers->map(fn ($driver) => $this->formatDriver($driver, $roleName)),
            'total'   => $drivers->count(),
        ]);
    }

    /**
     * Search & filter commuters.
     *
     * GET /api/search/commuters
     * 
     * Access:
     *   - Admin     : full search with all filters
     *   - Driver    : search commuters (limited info)
     *   - Commuter  : cannot search other commuters
     *
     * Query params: search, classification, sort_by, sort_order
     */
    public function searchCommuters(Request $request): JsonResponse
    {
        $user = auth()->user();

        if (!$user) {
            return response()->json(['error' => 'Unauthenticated'], 401);
        }

        $roleName = $user->role?->name;

        // Only admin and driver can search commuters
        if (!in_array($roleName, ['admin', 'driver'])) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized. Only admins and drivers can search commuters.',
            ], 403);
        }

        $query = Commuter::with('user', 'discount.classificationType');

        // Search by user name or email
        if ($request->filled('search')) {
            $search = $request->input('search');
            $query->whereHas('user', function ($q) use ($search) {
                $q->where('first_name', 'LIKE', "%{$search}%")
                  ->orWhere('last_name', 'LIKE', "%{$search}%")
                  ->orWhere('email', 'LIKE', "%{$search}%");
            });
        }

        // Filter by classification (through discount -> classificationType relationship)
        if ($request->filled('classification')) {
            $classification = $request->input('classification');
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
        $sortBy = $request->input('sort_by', 'created_at');
        $sortOrder = $request->input('sort_order', 'desc');
        $allowedSorts = ['created_at'];

        if (in_array($sortBy, $allowedSorts)) {
            $query->orderBy($sortBy, $sortOrder === 'asc' ? 'asc' : 'desc');
        }

        $commuters = $query->get();

        return response()->json([
            'success' => true,
            'data'    => $commuters->map(fn ($commuter) => $this->formatCommuter($commuter, $roleName)),
            'total'   => $commuters->count(),
        ]);
    }

    /**
     * Format driver data based on the viewer's role.
     * Admin sees everything, commuters see limited public info.
     */
    private function formatDriver(Driver $driver, string $viewerRole): array
    {
        $data = [
            'id'               => $driver->id,
            'franchise_number' => $driver->franchise_number,
            'driver_name'      => $driver->user
                ? $driver->user->first_name . ' ' . $driver->user->last_name
                : null,
        ];

        // Admin sees full details
        if ($viewerRole === 'admin') {
            $data['user_id']             = $driver->user_id;
            $data['license_number']      = $driver->license_number;
            $data['verification_status'] = $driver->verification_status;
            $data['email']               = $driver->user?->email;
            $data['created_at']          = $driver->created_at;
            $data['updated_at']          = $driver->updated_at;
        }

        return $data;
    }

    /**
     * Format commuter data based on the viewer's role.
     * Admin sees everything, drivers see limited info.
     */
    private function formatCommuter(Commuter $commuter, string $viewerRole): array
    {
        $classificationName = $commuter->discount?->classificationType?->classification_name ?? 'Regular';

        $data = [
            'id'                  => $commuter->id,
            'classification_name' => $classificationName,
            'commuter_name'       => $commuter->user
                ? $commuter->user->first_name . ' ' . $commuter->user->last_name
                : null,
        ];

        // Admin sees full details
        if ($viewerRole === 'admin') {
            $data['user_id']    = $commuter->user_id;
            $data['email']      = $commuter->user?->email;
            $data['discount']   = $commuter->discount ? [
                'id'             => $commuter->discount->id,
                'ID_number'      => $commuter->discount->ID_number,
                'ID_image_path'  => $commuter->discount->ID_image_path,
                'classification' => $commuter->discount->classificationType?->classification_name ?? 'Regular',
            ] : null;
            $data['created_at'] = $commuter->created_at;
            $data['updated_at'] = $commuter->updated_at;
        }

        return $data;
    }
}