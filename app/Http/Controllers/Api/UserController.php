<?php

namespace App\Http\Controllers\Api;

use Illuminate\Http\JsonResponse;
use App\Http\Controllers\Controller;
use App\Models\User;

class UserController extends Controller
{
    /*
    | Access-control
    |
    | Admin           : Can list and view all driver/commuter users (NOT other admins)
    | Driver/Commuter : Can only view their own user record
    */

    // Get All Users

    public function index(): JsonResponse
    {
        $user = auth()->user();

        if ($user->hasRole('admin')) {
            // Admin sees all non-admin users (drivers & commuters)
            $users = User::with('role')
                ->whereHas('role', fn ($q) => $q->whereIn('name', ['driver', 'commuter']))
                ->get()
                ->map(fn ($u) => $this->formatUser($u));

            return response()->json([
                'success' => true,
                'data'    => $users,
            ]);
        }

        // Driver/Commuter only sees themselves
        return response()->json([
            'success' => true,
            'data'    => [$this->formatUser($user->load('role'))],
        ]);
    }

    // Get Specific User

    public function show(string $id): JsonResponse
    {
        $user = auth()->user();

        $targetUser = User::with('role')->find($id);

        if (!$targetUser) {
            return response()->json([
                'success' => false,
                'message' => 'User not found.',
            ], 404);
        }

        // Driver/Commuter can only view themselves
        if (!$user->hasRole('admin')) {
            if ($targetUser->id !== $user->id) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized. You can only view your own account.',
                ], 403);
            }
        } else {
            // Admin cannot view other admin accounts
            if ($targetUser->hasRole('admin') && $targetUser->id !== $user->id) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized. You cannot view another admin\'s account.',
                ], 403);
            }
        }

        return response()->json([
            'success' => true,
            'data'    => $this->formatUser($targetUser),
        ]);
    }

    // Format user data for consistent response

    private function formatUser(User $user): array
    {
        return [
            'id'                => $user->id,
            'first_name'        => $user->first_name,
            'last_name'         => $user->last_name,
            'middle_name'       => $user->middle_name,
            'email'             => $user->email,
            'role'              => $user->role->name,
            'email_verified_at' => $user->email_verified_at,
            'created_at'        => $user->created_at,
            'updated_at'        => $user->updated_at,
        ];
    }
}
