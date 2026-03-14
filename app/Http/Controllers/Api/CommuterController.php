<?php

namespace App\Http\Controllers\Api;

use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use App\Http\Controllers\Controller;
use App\Models\Commuter;
use App\Models\EmergencyContact;
use App\Models\UsersEmergencyContact;
use App\Models\Driver;
use App\Models\Discount;
use App\Models\DiscountImage;
use App\Models\DiscountTypes;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Validation\Rule;
use App\Support\DashboardCache;

class CommuterController extends Controller
{
    /** Retention window (days) – soft-deleted profiles older than this should not be restorable. */
    private const RESTORE_WINDOW_DAYS = 30;

    // Create Commuter Profile

    /* Endpoint: /api/commuter/add-commuter
       Method: POST
       Body Params:
         - classification_name (string, required): One of 'Regular', 'Student', 'Senior Citizen', 'PWD'
         - ID_number (string, required if classification is not Regular): Alphanumeric ID number for discount verification
         - image_front (file, required if classification is not Regular): Front image of the ID for verification
         - image_back (file, required if classification is not Regular): Back image of the ID for verification
       Response:
         - success (boolean)
         - message (string)
         - data (object) – commuter profile details if successful
    */

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
            'classification_name' => ['required', 'string', Rule::in(['Regular', 'Student', 'Senior Citizen', 'PWD'])],
            'ID_number' => [
                Rule::requiredIf(fn () => $request->input('classification_name') !== 'Regular'),
                'nullable', 'string', 'max:255', 'regex:/^[0-9\s]+$/', 'unique:discounts,ID_number',
            ],
            'image_front' => [
                Rule::requiredIf(fn () => $request->input('classification_name') !== 'Regular'),
                'nullable', 'image', 'max:2048',
            ],
            'image_back' => [
                Rule::requiredIf(fn () => $request->input('classification_name') !== 'Regular'),
                'nullable', 'image', 'max:2048',
            ],
        ]);

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
            $discountImage = DiscountImage::create([
                'image_front' => $request->file('image_front')->store('discount_ids', 'public'),
                'image_back'  => $request->file('image_back')->store('discount_ids', 'public'),
            ]);

            $discount = Discount::create([
                'ID_number'              => $validated['ID_number'],
                'ID_image_id'            => $discountImage->id,
                'classification_type_id' => $classificationType->id,
            ]);

            $discountId = $discount->id;
        }

        // Create commuter profile
        $commuter = Commuter::create([
            'user_id'     => $user->id,
            'discount_id' => $discountId,
        ]);

        DashboardCache::forgetUserDashboards($user->id);

        

        return response()->json([
            'success' => true,
            'message' => 'Commuter profile created successfully.',
            'data'    => $this->formatCommuter(
                $commuter->fresh('user', 'discount.classificationType', 'discount.idImage')
            ),
        ], 201);
    }

    // Read Commuter Profile

    /* Endpoint: /api/commuter/{id}
       Method: GET
       URL Params:
         - id (string, required): Commuter profile ID
       Response:
         - success (boolean)
         - message (string)
         - data (object) – commuter profile details if successful
    */

    public function getCommuter(string $id): JsonResponse
    {
        $user = auth()->user();

        if (!$user) {
            return response()->json(['error' => 'Unauthenticated'], 401);
        }

        $commuter = Commuter::with('user', 'discount.classificationType', 'discount.idImage')->find($id);

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

    /* Endpoint: /api/commuter/update-commuter/{id}
       Method: PUT
       URL Params:
         - id (string, required): Commuter profile ID
       Body Params:
         - classification_name (string, optional): One of 'Regular', 'Student', 'Senior Citizen', 'PWD'
         - ID_number (string, required if classification is not Regular): Alphanumeric ID number for discount verification
         - image_front (file, required if classification is not Regular): Front image of the ID for verification
         - image_back (file, required if classification is not Regular): Back image of the ID for verification
       Response:
         - success (boolean)
         - message (string)
         - data (object) – updated commuter profile details if successful
    */

    public function updateCommuterClassification(Request $request, string $id): JsonResponse
    {
        $user = auth()->user();

        if (!$user) {
            return response()->json(['error' => 'Unauthenticated'], 401);
        }

        $commuter = Commuter::with('user', 'discount.classificationType', 'discount.idImage')->find($id);

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
            'classification_name' => ['sometimes', 'string', Rule::in(['Regular', 'Student', 'Senior Citizen', 'PWD'])],
            'ID_number' => [
                'nullable', 'string', 'max:255', 'regex:/^[0-9\s]+$/',
                Rule::unique('discounts', 'ID_number')->ignore($commuter->discount_id),
            ],
            'image_front' => ['nullable', 'image', 'max:2048'],
            'image_back'  => ['nullable', 'image', 'max:2048'],
        ]);

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
                        if ($oldDiscount->ID_image_id) {
                            $oldImage = DiscountImage::find($oldDiscount->ID_image_id);
                            if ($oldImage) {
                                $oldImage->delete();
                            }
                        }
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

                if ($commuter->discount_id) {
                    // Update existing discount
                    $discount = Discount::find($commuter->discount_id);
                    if ($discount) {
                        $updateData = [
                            'classification_type_id' => $classificationType->id,
                        ];

                        if (isset($validated['ID_number'])) {
                            $updateData['ID_number'] = $validated['ID_number'];
                        }

                        $image = $discount->idImage;
                        if (!$image && ($request->hasFile('image_front') || $request->hasFile('image_back'))) {
                            $image = DiscountImage::create([
                                'image_front' => null,
                                'image_back'  => null,
                            ]);
                            $updateData['ID_image_id'] = $image->id;
                        }

                        if ($image) {
                            $imgUpdate = [];
                            if ($request->hasFile('image_front')) {
                                $imgUpdate['image_front'] = $request->file('image_front')->store('discount_ids', 'public');
                            }
                            if ($request->hasFile('image_back')) {
                                $imgUpdate['image_back'] = $request->file('image_back')->store('discount_ids', 'public');
                            }
                            if (!empty($imgUpdate)) {
                                $image->update($imgUpdate);
                            }
                        }

                        $discount->update($updateData);
                    }
                } else {
                    if (!$request->hasFile('image_front') || !$request->hasFile('image_back')) {
                        return response()->json([
                            'success' => false,
                            'message' => 'Both front and back ID images are required for non-regular classifications.',
                        ], 422);
                    }

                    $discountImage = DiscountImage::create([
                        'image_front' => $request->file('image_front')->store('discount_ids', 'public'),
                        'image_back'  => $request->file('image_back')->store('discount_ids', 'public'),
                    ]);

                    $discount = Discount::create([
                        'ID_number'              => $validated['ID_number'],
                        'ID_image_id'            => $discountImage->id,
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

                    $image = $discount->idImage;
                    if (!$image && ($request->hasFile('image_front') || $request->hasFile('image_back'))) {
                        $image = DiscountImage::create([
                            'image_front' => null,
                            'image_back'  => null,
                        ]);
                        $updateData['ID_image_id'] = $image->id;
                    }

                    if ($image) {
                        $imgUpdate = [];
                        if ($request->hasFile('image_front')) {
                            $imgUpdate['image_front'] = $request->file('image_front')->store('discount_ids', 'public');
                        }
                        if ($request->hasFile('image_back')) {
                            $imgUpdate['image_back'] = $request->file('image_back')->store('discount_ids', 'public');
                        }
                        if (!empty($imgUpdate)) {
                            $image->update($imgUpdate);
                        }
                    }

                    if (!empty($updateData)) {
                        $discount->update($updateData);
                    }
                }
            }
        }

        DashboardCache::forgetUserDashboards($commuter->user_id);

        return response()->json([
            'success' => true,
            'message' => 'Commuter profile updated successfully.',
            'data'    => $this->formatCommuter(
                $commuter->fresh('user', 'discount.classificationType', 'discount.idImage')
            ),
        ]);
    }

    /* Endpoint: /api/commuter/delete-commuter/{id}
       Method: DELETE
       URL Params:
         - id (string, required): Commuter profile ID
       Response:
         - success (boolean)
         - message (string)
    */

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

        if ($commuter->discount_id) {
            $discount = Discount::find($commuter->discount_id);
            if ($discount) {
                if ($discount->ID_image_id) {
                    $image = DiscountImage::find($discount->ID_image_id);
                    if ($image) {
                        $image->delete();
                    }
                }
                $discount->delete();
            }
        }

        $commuter->delete();
        DashboardCache::forgetUserDashboards($commuter->user_id);

        return response()->json([
            'success' => true,
            'message' => 'Commuter profile deleted successfully.',
        ]);
    }

    /* Endpoint: /api/commuter/restore-commuter/{id}
       Method: PUT
       URL Params:
         - id (string, required): Commuter profile ID
       Response:
         - success (boolean)
         - message (string)
         - data (object) – restored commuter profile details if successful
    */

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

                if ($discount->ID_image_id) {
                    $image = DiscountImage::onlyTrashed()->find($discount->ID_image_id);
                    if ($image) {
                        $image->restore();
                    }
                }
            }
        }

        $commuter->restore();
        DashboardCache::forgetUserDashboards($commuter->user_id);

        return response()->json([
            'success' => true,
            'message' => 'Commuter profile restored successfully.',
            'data'    => $this->formatCommuter(
                $commuter->fresh('user', 'discount.classificationType', 'discount.idImage')
            ),
        ]);
    }

    // Format commuter data

    private function formatCommuter(Commuter $commuter): array
    {
        $emergencyContact = $commuter->usersEmergencyContact?->emergencyContact;
        return [
            'id' => $commuter->id,
            'user_id' => $commuter->user_id,
            'user' => $commuter->user ? [
                'id' => $commuter->user->id,
                'first_name' => $commuter->user->first_name,
                'last_name' => $commuter->user->last_name,
                'middle_name' => $commuter->user->middle_name,
                'email' => $commuter->user->email,
            ] : null,
            'classification_name' => $commuter->discount?->classificationType?->classification_name ?? 'Regular',
            'discount' => $commuter->discount ? [
                'id' => $commuter->discount->id,
                'ID_number' => $commuter->discount->ID_number,
                'images' => [
                    'front' => $commuter->discount->idImage?->image_front,
                    'back'  => $commuter->discount->idImage?->image_back,
                ],
                'classification' => $commuter->discount->classificationType?->classification_name ?? 'Regular',
            ] : null,
            'emergency_contact' => $emergencyContact ? [
                'id' => $emergencyContact->id,
                'contact_name' => $emergencyContact->contact_name,
                'contact_phone_number' => $emergencyContact->contact_phone_number,
                'contact_relationship' => $emergencyContact->contact_relationship,
             ] : null,
            
        ];
    }
}