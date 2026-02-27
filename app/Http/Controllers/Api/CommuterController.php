<?php

namespace App\Http\Controllers\Api;

use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use App\Http\Controllers\Controller;
use App\Models\Commuter;
use App\Models\User;
use Carbon\Carbon;

class CommuterController extends Controller
{
    /** Retention window (days) – soft-deleted profiles older than this should not be restorable. */
    private const RESTORE_WINDOW_DAYS = 30;

    // Create

    public function addCommuter(Request $request): JsonResponse
    {
        $user = $request->user();

        // Commuter role can only have one classification
        if (Commuter::where('user_id', $user->id)->exists()) {
            return response()->json([
                'success' => false,
                'message' => 'You already have a commuter classification. You can only have one classification.',
            ], 400);
        }

        $request->validate([
            'commuter_classification' => 'required|string|in:regular,student,senior,PWD',
        ]);

        $commuter = Commuter::create([
            'user_id'       => $user->id,
            'classification' => $request->input('commuter_classification'),
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Commuter classification created successfully.',
            'data'    => $commuter,
        ], 201);
    }

    // Read

    public function getCommuter(string $id): JsonResponse
    {
        $user = auth()->user();

        $commuter = Commuter::with('user')->find($id);

        if (!$commuter) {
            return response()->json([
                'success' => false,
                'message' => 'Commuter not found.',
            ], 404);
        }

        $profileOwner = $commuter->user;

        // Commuter can only view their own profile and admin can view all commuter profiles
        if (!$user->hasRole('admin')) {
            if ($commuter->user_id !== $user->id) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized. You can only view your own profile.',
                ], 403);
            }
        } 

        return response()->json([
            'success' => true,
            'data'    => $commuter,
        ]);
    }

    // Update

    public function updateCommuterClassification(Request $request, string $id): JsonResponse
    {
        $user = auth()->user();

        $commuter = Commuter::with('user')->find($id);

        if (!$commuter) {
            return response()->json([
                'success' => false,
                'message' => 'Commuter not found.',
            ], 404);
        }

        $profileOwner = $commuter->user;

        // Commuter on their own profile can update classification also admin
        if (!$user->hasRole('admin') && !($user->hasRole('commuter') && $commuter->user_id === $user->id)) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized. Only Admin or the owning Commuter can update this profile.',
            ], 403);
        }

        $request->validate([
            'commuter_classification' => 'sometimes|string|in:regular,student,senior,PWD',
        ]);

        $data = $request->only(['commuter_classification']);

        if ($request->hasFile('profile_image')) {
            $data['profile_image'] = $request->file('profile_image')->store('profile_images', 'public');
        }

        $commuter->update($data);

        return response()->json([
            'success' => true,
            'message' => 'Commuter classification updated successfully.',
            'data'    => $commuter->fresh(),
        ]);
    }

    // Delete (Admin only — commuter profiles)

    public function deleteCommuter(string $id): JsonResponse
    {
        $user = auth()->user();

        if (!$user->hasRole('admin')) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized. Only admins can delete commuter profiles.',
            ], 403);
        }

        $commuter = Commuter::with('user')->find($id);

        if (!$commuter) {
            return response()->json([
                'success' => false,
                'message' => 'Commuter not found.',
            ], 404);
        }

        $commuter->delete();

        return response()->json([
            'success' => true,
            'message' => 'Commuter profile deleted successfully.',
        ]);
    }

    // Restore (Admin only — within retention window) 

    public function restoreCommuter(string $id): JsonResponse
    {
        $user = auth()->user();

        if (!$user->hasRole('admin')) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized. Only admins can restore user profiles.',
            ], 403);
        }

        $commuter = Commuter::onlyTrashed()->with('user')->find($id);

        if (!$commuter) {
            return response()->json([
                'success' => false,
                'message' => 'User profile not found or not deleted.',
            ], 404);
        }

        // Data-privacy guard: only allow restore within the retention window
        $deletedAt = Carbon::parse($commuter->deleted_at);
        if ($deletedAt->diffInDays(now()) > self::RESTORE_WINDOW_DAYS) {
            return response()->json([
                'success' => false,
                'message' => 'This profile was deleted more than ' . self::RESTORE_WINDOW_DAYS
                             . ' days ago and can no longer be restored for data-privacy compliance.',
            ], 403);
        }

        $commuter->restore();

        return response()->json([
            'success' => true,
            'message' => 'User profile restored successfully.',
            'data'    => $commuter->fresh(),
        ]);
    }
}
