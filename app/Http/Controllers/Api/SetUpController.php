<?php

namespace App\Http\Controllers\Api;

use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\Role;
use Illuminate\Support\Str;

class SetUpController extends Controller
{
    public function setUpUsers(Request $request): JsonResponse
    {
        $user = auth()->user();

        if (!$user) {
            return response()->json(['error' => 'Unauthenticated'], 401);
        }

        // Check if user is already set up
        if ($user->first_name && $user->last_name && $user->roles()->exists()) {
            return response()->json([
                'success' => false,
                'message' => 'Your profile is already set up.',
            ], 400);
        }

        $validated = $request->validate([
            'first_name'  => 'required|string|max:255|regex:/^[\p{L}\s]+$/u',
            'last_name'   => 'required|string|max:255|regex:/^[\p{L}\s]+$/u',
            'middle_name' => 'nullable|string|max:255|regex:/^[\p{L}\s]+$/u',
            'roles'       => 'required|array|min:1',
            'roles.*'     => 'string|in:driver,commuter',
        ]);

        $roleNames = array_unique($validated['roles']);

        // Look up all requested roles
        $roles = Role::whereIn('name', $roleNames)->get();

        if ($roles->count() !== count($roleNames)) {
            return response()->json([
                'success' => false,
                'message' => 'One or more roles not found.',
            ], 404);
        }

        $user->first_name  = $validated['first_name'];
        $user->last_name   = $validated['last_name'];
        $user->middle_name = $validated['middle_name'] ?? null;
        $user->save();

        $user->roles()->sync($roles->pluck('id')->toArray());

        return response()->json([
            'success' => true,
            'message' => 'You\'re All Set Up.',
            'data' => [
                'id'          => $user->id,
                'first_name'  => $user->first_name,
                'last_name'   => $user->last_name,
                'middle_name' => $user->middle_name,
                'email'       => $user->email,
                'roles'       => $roles->pluck('name'),
            ],
        ]);
    }
}