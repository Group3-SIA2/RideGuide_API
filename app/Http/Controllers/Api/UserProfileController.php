<?php

namespace App\Http\Controllers\Api;

use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use App\Http\Controllers\Controller;
use App\Models\UserProfile;
use App\Models\User;
use Carbon\Carbon;

class UserProfileController extends Controller
{
    /*
    | Access-control matrix
    |
    | Driver / Commuter : Create, Read, Update  → OWN profile only
    | Admin             : Create                → OWN profile only
    |                   : Read, Update, Delete  → Driver/Commuter profiles only (NOT other admins)
    |                   : Restore               → Driver/Commuter profiles deleted within 30 days
    */

    /** Retention window (days) – soft-deleted profiles older than this should not be restorable. */
    private const RESTORE_WINDOW_DAYS = 30;

    // Create

    public function addUserProfileCredentials(Request $request): JsonResponse
    {
        $user = $request->user();

        // Every role can only create their OWN profile — no exceptions
        if (UserProfile::where('user_id', $user->id)->exists()) {
            return response()->json([
                'success' => false,
                'message' => 'You already have a profile. You can only have one profile.',
            ], 400);
        }

        $request->validate([
            'birthdate'     => 'required|date',
            'gender'        => 'required|string|in:male,female,other',
            'profile_image' => 'nullable|image|max:2048',
        ]);

        $userProfile = UserProfile::create([
            'user_id'       => $user->id,
            'birthdate'     => $request->input('birthdate'),
            'gender'        => $request->input('gender'),
            'profile_image' => $request->file('profile_image')
                                ? $request->file('profile_image')->store('profile_images', 'public')
                                : null,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'User profile created successfully.',
            'data'    => $userProfile,
        ], 201);
    }

    // Read

    public function getUserProfileCredentials(string $id): JsonResponse
    {
        $user = auth()->user();

        $userProfile = UserProfile::with('user')->find($id);

        if (!$userProfile) {
            return response()->json([
                'success' => false,
                'message' => 'User profile not found.',
            ], 404);
        }

        $profileOwner = $userProfile->user;

        // Driver / Commuter → can only view their own profile
        if (!$user->hasRole('admin')) {
            if ($userProfile->user_id !== $user->id) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized. You can only view your own profile.',
                ], 403);
            }
        } else {
            // Admin → can view driver/commuter profiles, NOT other admin profiles
            if ($profileOwner && $profileOwner->hasRole('admin') && $profileOwner->id !== $user->id) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized. You cannot view another admin\'s profile.',
                ], 403);
            }
        }

        return response()->json([
            'success' => true,
            'data'    => $userProfile,
        ]);
    }

    // Update

    public function updateUserProfileCredentials(Request $request, string $id): JsonResponse
    {
        $user = auth()->user();

        $userProfile = UserProfile::with('user')->find($id);

        if (!$userProfile) {
            return response()->json([
                'success' => false,
                'message' => 'User profile not found.',
            ], 404);
        }

        $profileOwner = $userProfile->user;

        // Driver / Commuter → can only update their own profile
        if (!$user->hasRole('admin')) {
            if ($userProfile->user_id !== $user->id) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized. You can only update your own profile.',
                ], 403);
            }
        } else {
            // Admin → can update driver/commuter profiles, NOT other admin profiles
            if ($profileOwner && $profileOwner->hasRole('admin') && $profileOwner->id !== $user->id) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized. You cannot update another admin\'s profile.',
                ], 403);
            }
        }

        $request->validate([
            'birthdate'     => 'sometimes|required|date',
            'gender'        => 'sometimes|required|string|in:male,female,other',
            'profile_image' => 'nullable|image|max:2048',
        ]);

        $data = $request->only(['birthdate', 'gender']);

        if ($request->hasFile('profile_image')) {
            $data['profile_image'] = $request->file('profile_image')->store('profile_images', 'public');
        }

        $userProfile->update($data);

        return response()->json([
            'success' => true,
            'message' => 'User profile updated successfully.',
            'data'    => $userProfile->fresh(),
        ]);
    }

    // Delete (Admin only — driver/commuter profiles)

    public function deleteUserProfileCredentials(string $id): JsonResponse
    {
        $user = auth()->user();

        if (!$user->hasRole('admin')) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized. Only admins can delete user profiles.',
            ], 403);
        }

        $userProfile = UserProfile::with('user')->find($id);

        if (!$userProfile) {
            return response()->json([
                'success' => false,
                'message' => 'User profile not found.',
            ], 404);
        }

        // Admin cannot delete another admin's profile
        $profileOwner = $userProfile->user;
        if ($profileOwner && $profileOwner->hasRole('admin')) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized. You cannot delete an admin\'s profile.',
            ], 403);
        }

        $userProfile->delete();

        return response()->json([
            'success' => true,
            'message' => 'User profile deleted successfully.',
        ]);
    }

    // Restore (Admin only — within retention window) 

    public function restoreUserProfileCredentials(string $id): JsonResponse
    {
        $user = auth()->user();

        if (!$user->hasRole('admin')) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized. Only admins can restore user profiles.',
            ], 403);
        }

        $userProfile = UserProfile::onlyTrashed()->with('user')->find($id);

        if (!$userProfile) {
            return response()->json([
                'success' => false,
                'message' => 'User profile not found or not deleted.',
            ], 404);
        }

        // Admin cannot restore another admin's profile
        $profileOwner = $userProfile->user;
        if ($profileOwner && $profileOwner->hasRole('admin')) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized. You cannot restore an admin\'s profile.',
            ], 403);
        }

        // Data-privacy guard: only allow restore within the retention window
        $deletedAt = Carbon::parse($userProfile->deleted_at);
        if ($deletedAt->diffInDays(now()) > self::RESTORE_WINDOW_DAYS) {
            return response()->json([
                'success' => false,
                'message' => 'This profile was deleted more than ' . self::RESTORE_WINDOW_DAYS
                             . ' days ago and can no longer be restored for data-privacy compliance.',
            ], 403);
        }

        $userProfile->restore();

        return response()->json([
            'success' => true,
            'message' => 'User profile restored successfully.',
            'data'    => $userProfile->fresh(),
        ]);
    }
}
