<?php

namespace App\Http\Controllers\Api;

use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use App\Http\Controllers\Controller;
use App\Models\Commuter;
use App\Models\Discount;
use App\Models\DiscountTypes;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Validation\Rule;

class CommuterController extends Controller
{
    /** Retention window (days) – soft-deleted profiles older than this should not be restorable. */
    private const RESTORE_WINDOW_DAYS = 30;

    // Create Commuter Profile

    public function addCommuter(Request $request): JsonResponse
    {
        $user = $request->user();

        if (!$user) {
            return response()->json(['error' => 'Unauthenticated'], 401);
        }

        // Commuter role only
        if (!$user->hasRole('commuter')) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized. Only users with the commuter role can create a commuter profile.',
            ], 403);
        }

        // One profile per commuter
        if (Commuter::where('user_id', $user->id)->exists()) {
            return response()->json([
                'success' => false,
                'message' => 'You already have a commuter profile. You can only have one.',
            ], 400);
        }

        $validated = $request->validate([
            'classification_name' => ['required', 'string', Rule::in(['Regular', 'Student', 'Senior', 'PWD'])],
            'ID_number'           => ['nullable', 'required_unless:classification_name,Regular', 'string', 'max:255', 'unique:discounts,ID_number','regex:/^[0-9\s]+$/'],
            'ID_image'            => ['nullable', 'required_unless:classification_name,Regular', 'image', 'max:2048',],
        ],);

        // Look up the classification type
        $classificationType = DiscountTypes::where('classification_name', $validated['classification_name'])->first();
        if (!$classificationType) {
            return response()->json([
                'success' => false,
                'message' => 'Classification type not found.',
            ], 404);
        }

        // Create discount record if not regular (student/senior/PWD need ID verification)
        $discountId = null;

        if ($validated['classification_name'] !== 'Regular') {
            $idImagePath = null;
            if ($request->hasFile('ID_image')) {
                $idImagePath = $request->file('ID_image')->store('discount_ids', 'public');
            }

            $discount = Discount::create([
                'ID_number'               => $validated['ID_number'],
                'ID_image_path'           => $idImagePath,
                'classification_type_id'  => $classificationType->id,
            ]);

            $discountId = $discount->id;
        }

        // Create commuter profile
        $commuter = Commuter::create([
            'user_id'     => $user->id,
            'discount_id' => $discountId,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Commuter profile created successfully.',
            'data'    => $this->formatCommuter($commuter->load('user', 'discount.classificationType')),
        ], 201);
    }

    // Read Commuter Profile

    public function getCommuter(string $id): JsonResponse
    {
        $user = auth()->user();

        if (!$user) {
            return response()->json(['error' => 'Unauthenticated'], 401);
        }

        $commuter = Commuter::with('user', 'discount.classificationType')->find($id);

        if (!$commuter) {
            return response()->json([
                'success' => false,
                'message' => 'Commuter not found.',
            ], 404);
        }

        // Commuter can only view their own profile, admin can view all
        if (!$user->hasRole('admin') && $commuter->user_id !== $user->id) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized. You can only view your own profile.',
            ], 403);
        }

        return response()->json([
            'success' => true,
            'data'    => $this->formatCommuter($commuter),
        ]);
    }

    // Update Commuter Classification

    public function updateCommuterClassification(Request $request, string $id): JsonResponse
    {
        $user = auth()->user();

        if (!$user) {
            return response()->json(['error' => 'Unauthenticated'], 401);
        }

        $commuter = Commuter::with('user', 'discount.classificationType')->find($id);

        if (!$commuter) {
            return response()->json([
                'success' => false,
                'message' => 'Commuter not found.',
            ], 404);
        }

        // Only admin or the owning commuter can update
        if (!$user->hasRole('admin') && !($user->hasRole('commuter') && $commuter->user_id === $user->id)) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized. Only Admin or the owning Commuter can update this profile.',
            ], 403);
        }

        $validated = $request->validate([
            'classification_name' => ['sometimes', 'string', Rule::in(['Regular', 'Student', 'Senior', 'PWD'])],
            'ID_number'           => ['nullable', 'string', 'max:255','unique:discounts,ID_number','regex:/^[0-9\s]+$/'],
            'ID_image'            => ['nullable', 'image', 'max:2048'],
        ]);

        // If classification is changing
        if (isset($validated['classification_name'])) {
            $classificationType = DiscountTypes::where('classification_name', $validated['classification_name'])->first();

            if (!$classificationType) {
                return response()->json([
                    'success' => false,
                    'message' => 'Classification type not found.',
                ], 404);
            }

            if ($validated['classification_name'] === 'Regular') {
                // Switching to regular — remove discount if exists
                if ($commuter->discount_id) {
                    $oldDiscount = Discount::find($commuter->discount_id);
                    if ($oldDiscount) {
                        $oldDiscount->delete();
                    }
                    $commuter->discount_id = null;
                    $commuter->save();
                }
            } else {
                // Switching to student/senior/PWD — need ID
                if (!$commuter->discount_id && (!isset($validated['ID_number']))) {
                    return response()->json([
                        'success' => false,
                        'message' => 'ID number is required when switching to student, senior, or PWD classification.',
                    ], 422);
                }

                $idImagePath = null;
                if ($request->hasFile('ID_image')) {
                    $idImagePath = $request->file('ID_image')->store('discount_ids', 'public');
                }

                if ($commuter->discount_id) {
                    // Update existing discount
                    $discount = Discount::find($commuter->discount_id);
                    if ($discount) {
                        $updateData = ['classification_type_id' => $classificationType->id];
                        if (isset($validated['ID_number'])) {
                            $updateData['ID_number'] = $validated['ID_number'];
                        }
                        if ($idImagePath) {
                            $updateData['ID_image_path'] = $idImagePath;
                        }
                        $discount->update($updateData);
                    }
                } else {
                    // Create new discount
                    $discount = Discount::create([
                        'ID_number'              => $validated['ID_number'] ?? null,
                        'ID_image_path'          => $idImagePath,
                        'classification_type_id' => $classificationType->id,
                    ]);
                    $commuter->discount_id = $discount->id;
                    $commuter->save();
                }
            }
        } else {
            // No classification change — just update ID fields on existing discount
            if ($commuter->discount_id) {
                $discount = Discount::find($commuter->discount_id);
                if ($discount) {
                    $updateData = [];
                    if (isset($validated['ID_number'])) {
                        $updateData['ID_number'] = $validated['ID_number'];
                    }
                    if ($request->hasFile('ID_image')) {
                        $updateData['ID_image_path'] = $request->file('ID_image')->store('discount_ids', 'public');
                    }
                    if (!empty($updateData)) {
                        $discount->update($updateData);
                    }
                }
            }
        }

        return response()->json([
            'success' => true,
            'message' => 'Commuter profile updated successfully.',
            'data'    => $this->formatCommuter($commuter->fresh('user', 'discount.classificationType')),
        ]);
    }

    // Delete (Admin only)

    public function deleteCommuter(string $id): JsonResponse
    {
        $user = auth()->user();

        if (!$user) {
            return response()->json(['error' => 'Unauthenticated'], 401);
        }

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

        // Soft delete the discount too if exists
        if ($commuter->discount_id) {
            $discount = Discount::find($commuter->discount_id);
            if ($discount) {
                $discount->delete();
            }
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

        if (!$user) {
            return response()->json(['error' => 'Unauthenticated'], 401);
        }

        if (!$user->hasRole('admin')) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized. Only admins can restore commuter profiles.',
            ], 403);
        }

        $commuter = Commuter::onlyTrashed()->with('user')->find($id);

        if (!$commuter) {
            return response()->json([
                'success' => false,
                'message' => 'Commuter profile not found or not deleted.',
            ], 404);
        }

        // Data-privacy guard
        $deletedAt = Carbon::parse($commuter->deleted_at);
        if ($deletedAt->diffInDays(now()) > self::RESTORE_WINDOW_DAYS) {
            return response()->json([
                'success' => false,
                'message' => 'This profile was deleted more than ' . self::RESTORE_WINDOW_DAYS
                             . ' days ago and can no longer be restored for data-privacy compliance.',
            ], 403);
        }

        // Restore discount too if it was soft deleted
        if ($commuter->discount_id) {
            $discount = Discount::onlyTrashed()->find($commuter->discount_id);
            if ($discount) {
                $discount->restore();
            }
        }

        $commuter->restore();

        return response()->json([
            'success' => true,
            'message' => 'Commuter profile restored successfully.',
            'data'    => $this->formatCommuter($commuter->fresh('user', 'discount.classificationType')),
        ]);
    }

    // Format commuter data

    private function formatCommuter(Commuter $commuter): array
    {
        return [
            'id'                  => $commuter->id,
            'user_id'             => $commuter->user_id,
            'user'                => $commuter->user ? [
                'id'         => $commuter->user->id,
                'first_name' => $commuter->user->first_name,
                'last_name'  => $commuter->user->last_name,
                'middle_name' => $commuter->user->middle_name,
                'email'      => $commuter->user->email,
            ] : null,
            'classification_name' => $commuter->discount?->classificationType?->classification_name ?? 'Regular',
            'discount'            => $commuter->discount ? [
                'id'             => $commuter->discount->id,
                'ID_number'      => $commuter->discount->ID_number,
                'ID_image_path'  => $commuter->discount->ID_image_path,
                'classification' => $commuter->discount->classificationType?->classification_name ?? 'Regular',
            ] : null,
            'created_at'          => $commuter->created_at,
            'updated_at'          => $commuter->updated_at,
        ];
    }
}