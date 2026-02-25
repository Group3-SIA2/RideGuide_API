<?php

namespace App\Http\Controllers\Api;

use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use App\Http\Controllers\Controller;
use App\Models\UserProfile;

class UserController extends Controller
{
    public function addUserProfileCredentials(Request $request): JsonResponse
    {
        // Only allow users to add their own profile or admins to add any profile
        $user = auth()->user();
        if (!$user || ($user->id !== $request->user()->id && !$user->hasRole('admin'))) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized. You can only add your own profile.',
            ], 403);
        }

        //block users from creating multiple profiles
        if (UserProfile::where('user_id', $request->user()->id)->exists()) {
            return response()->json([
                'success' => false,
                'message' => 'You already have a profile. You can only have one profile.',
            ], 400);
        }

        $request->validate([
            'birthdate' => 'required|date',
            'gender' => 'required|string|in:male,female,other',
            'profile_image' => 'nullable|image|max:2048',
        ]);


        $userProfile = UserProfile::create([
            'user_id' => $request->user()->id,
            'birthdate' => $request->input('birthdate'),
            'gender' => $request->input('gender'),
            'profile_image' => $request->input('profile_image'),
        ]);

        return response()->json([
            'success' => true,
            'message' => 'User profile created successfully.',
            'data' => $userProfile,
        ], 201);
    }

    public function updateUserProfileCredentials(Request $request, $id): JsonResponse
    {
        // Only allow users to update their own profile or admins to update any user profile
        $user = auth()->user();
        if (!$user || ($user->id !== $id && !$user->hasRole('admin'))) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized. You can only update your own profile.',
            ], 403);
        }

        $request->validate([
            'birthdate' => 'sometimes|required|date',
            'gender' => 'sometimes|required|string|in:male,female,other',
            'profile_image' => 'nullable|image|max:2048',
        ]);

        $userProfile = UserProfile::find($id);

        if (!$userProfile) {
            return response()->json([
                'success' => false,
                'message' => 'User profile not found.',
            ], 404);
        }

        $userProfile->update($request->only([
                                            'birthdate', 
                                            'gender', 
                                            'profile_image']));

        return response()->json([
            'success' => true,
            'message' => 'User profile updated successfully.',
            'data' => $userProfile,
        ]);
    }

    public function getUserProfileCredentials($id): JsonResponse
    {
        // Only allow users to view their own profile or admins to view any user profile
        $user = auth()->user();
        if (!$user || ($user->id !== $id && !$user->hasRole('admin'))) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized. You can only view your own profile.',
            ], 403);
        }

        $userProfile = UserProfile::find($id);

        if (!$userProfile) {
            return response()->json([
                'success' => false,
                'message' => 'User profile not found.',
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data' => $userProfile,
        ]);
    }

    public function deleteUserProfileCredentials($id): JsonResponse
    {
        // only admins can delete user profiles
        $user = auth()->user();
        if (!$user || !$user->hasRole('admin')) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized. Only admins can delete user profiles.',
            ], 403);
        }

        $userProfile = UserProfile::find($id);

        if (!$userProfile) {
            return response()->json([
                'success' => false,
                'message' => 'User profile not found.',
            ], 404);
        }

        $userProfile->delete();

        return response()->json([
            'success' => true,
            'message' => 'User profile deleted successfully.',
        ]);
    }

    public function restoreUserProfileCredentials($id): JsonResponse
    {
        // only admins can restore user profiles
        $user = auth()->user();
        if (!$user || !$user->hasRole('admin')) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized. Only admins can restore user profiles.',
            ], 403);
        }

        $userProfile = UserProfile::onlyTrashed()->find($id);

        if (!$userProfile) {
            return response()->json([
                'success' => false,
                'message' => 'User profile not found or not deleted.',
            ], 404);
        }

        $userProfile->restore();

        return response()->json([
            'success' => true,
            'message' => 'User profile restored successfully.',
            'data' => $userProfile,
        ]);
    }
}
