<?php

namespace App\Http\Controllers\Api;

use Illuminate\Http\JsonResponse;
use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class UserController extends Controller
{
    /*
    | Access-control
    |
    | Admin                     : Can list and view all driver/commuter/organization users (NOT other admins)
    | Driver/Commuter/Organization : Can only view their own user record
    */

    // Get All Users

    public function index(Request $request): JsonResponse
    {
        $user = auth()->user();

        if ($user->hasRole('admin') || $user->hasRole('super_admin')) {
            $validated = $request->validate([
                'status' => ['nullable', Rule::in([User::STATUS_ACTIVE, User::STATUS_INACTIVE, User::STATUS_SUSPENDED])],
            ]);

            // Admin sees all non-admin users (drivers, commuters, and organizations)
            $usersQuery = User::with('roles')
                ->whereHas('roles', fn ($q) => $q->whereIn('name', ['driver', 'commuter', 'organization']))
                ->orderBy('first_name');

            if (!empty($validated['status'])) {
                $usersQuery->where('status', $validated['status']);
            }

            $users = $usersQuery
                ->get()
                ->map(fn ($u) => $this->formatUser($u));

            return response()->json([
                'success' => true,
                'data'    => $users,
            ]);
        }

        // Driver/Commuter/Organization only sees themselves
        return response()->json([
            'success' => true,
            'data'    => [$this->formatUser($user->load('roles'))],
        ]);
    }

    // Get Specific User

    public function show(string $id): JsonResponse
    {
        $user = auth()->user();

        $targetUser = User::with('roles')->find($id);

        if (!$targetUser) {
            return response()->json([
                'success' => false,
                'message' => 'User not found.',
            ], 404);
        }

        // Driver/Commuter/Organization can only view themselves
        if (!$user->hasRole('admin') && !$user->hasRole('super_admin')) {
            if ($targetUser->id !== $user->id) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized. You can only view your own account.',
                ], 403);
            }
        } else {
            // Admin cannot view other admin accounts
            if (($targetUser->hasRole('admin') || $targetUser->hasRole('super_admin')) && $targetUser->id !== $user->id) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized. You cannot view another admin account.',
                ], 403);
            }
        }

        return response()->json([
            'success' => true,
            'data'    => $this->formatUser($targetUser),
        ]);
    }

    // Format user data for consistent response

    private function formatUser($user): array
    {
        return [
            'id'                => $user->id,
            'first_name'        => $user->first_name,
            'last_name'         => $user->last_name,
            'middle_name'       => $user->middle_name,
            'email'             => $user->email,
            'role'              => $user->roles->pluck('name'),
            'status'            => $user->status,
            'status_reason'     => $user->status_reason,
            'status_changed_at' => $user->status_changed_at,
            'email_verified_at' => $user->email_verified_at,
            'created_at'        => $user->created_at,
            'updated_at'        => $user->updated_at,
        ];
    }
}
