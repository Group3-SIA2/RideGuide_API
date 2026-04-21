<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AdminTransactionLog;
use App\Models\Role;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class LogbookController extends Controller
{
    /**
     * Return the authenticated actor's own logbook entries.
     */
    public function myActivity(Request $request): JsonResponse
    {
        $user = $request->user();

        if (! $user) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized.',
            ], 401);
        }

        if (! $this->canViewOwnLogbook($user)) {
            return response()->json([
                'success' => false,
                'message' => 'You are not allowed to access this logbook.',
            ], 403);
        }

        $validated = $request->validate([
            'search' => ['nullable', 'string', 'max:120'],
            'module' => ['nullable', 'string', 'max:50'],
            'status' => ['nullable', 'string', 'in:success,failed'],
            'transaction_type' => ['nullable', 'string', 'max:80'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);

        $query = AdminTransactionLog::query()
            ->where('actor_user_id', (string) $user->id)
            ->latest();

        if (! empty($validated['search'])) {
            $search = $validated['search'];
            $query->where(function ($q) use ($search) {
                $q->where('transaction_type', 'like', '%' . $search . '%')
                  ->orWhere('module', 'like', '%' . $search . '%')
                  ->orWhere('reference_type', 'like', '%' . $search . '%')
                  ->orWhere('reference_id', 'like', '%' . $search . '%');
            });
        }

        if (! empty($validated['module'])) {
            $query->where('module', $validated['module']);
        }

        if (! empty($validated['status'])) {
            $query->where('status', $validated['status']);
        }

        if (! empty($validated['transaction_type'])) {
            $query->where('transaction_type', $validated['transaction_type']);
        }

        $perPage = (int) ($validated['per_page'] ?? 20);
        $logs = $query->paginate($perPage)->withQueryString();

        return response()->json([
            'success' => true,
            'message' => 'Own activity logbook fetched successfully.',
            'data' => [
                'logs' => $logs->items(),
                'pagination' => [
                    'current_page' => $logs->currentPage(),
                    'per_page' => $logs->perPage(),
                    'total' => $logs->total(),
                    'last_page' => $logs->lastPage(),
                ],
            ],
        ]);
    }

    private function canViewOwnLogbook(object $user): bool
    {
        if (! method_exists($user, 'hasRole')) {
            return false;
        }

        return $user->hasRole(Role::COMMUTER)
            || $user->hasRole(Role::DRIVER)
            || $user->hasRole(Role::ORGANIZATION)
            || (method_exists($user, 'hasAnyActiveOrganizationManagement') && $user->hasAnyActiveOrganizationManagement());
    }
}
