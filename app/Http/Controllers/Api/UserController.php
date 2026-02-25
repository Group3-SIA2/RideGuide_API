<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class UserController extends Controller
{
    public function addUserProfileCredentials(Request $request): JsonResponse
    {
        // Only allow users to add their own profile or admins to add any profile
        $user = auth()->user();
        if (!$user || ($user->id !== $request->input('user_id') && !$user->hasRole('admin'))) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized. You can only add your own profile.',
            ], 403);
        }

        $request->validate([
            'user_id' => 'required|exists:users,id',
            'first_name' => 'required|string|max:255|regex:/^[a-zA-Z\s]+$/',
            'last_name' => 'required|string|max:255|regex:/^[a-zA-Z\s]+$/',
            'birthdate' => 'required|date',
            'gender' => 'required|string|in:male,female,other',
            'profile_image' => 'nullable|image|max:2048',
        ]);

        $userProfile = User_profile::create([
            'user_id' => $request->input('user_id'),
            'first_name' => $request->input('first_name'),
            'last_name' => $request->input('last_name'),
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
            'first_name' => 'sometimes|required|string|max:255|regex:/^[a-zA-Z\s]+$/',
            'last_name' => 'sometimes|required|string|max:255|regex:/^[a-zA-Z\s]+$/',
            'phone_number' => 'sometimes|required|string|max:20|regex:/^\+?[0-9\s\-]+$/',
            'address' => 'nullable|string|max:500|regex:/^[a-zA-Z0-9\s,.-]+$/',
            'birthdate' => 'sometimes|required|date',
            'gender' => 'sometimes|required|string|in:male,female,other',
            'profile_image' => 'nullable|image|max:2048',
        ]);

        $userProfile = User_profile::find($id);

        if (!$userProfile) {
            return response()->json([
                'success' => false,
                'message' => 'User profile not found.',
            ], 404);
        }

        $userProfile->update($request->only(['first_name', 
                                            'last_name', 
                                            'phone_number', 
                                            'address', 
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

        $userProfile = User_profile::find($id);

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

        $userProfile = User_profile::find($id);

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

        $userProfile = User_profile::onlyTrashed()->find($id);

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
