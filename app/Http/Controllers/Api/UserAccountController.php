<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\ChangePasswordRequest;
use App\Http\Requests\DeleteAccountRequest;
use App\Models\Organization;
use App\Models\Role;
use App\Models\User;
use App\Services\AccountDeletionService;
use App\Support\AppRoleContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class UserAccountController extends Controller
{
    public function __construct(
        private readonly AccountDeletionService $accountDeletionService,
    ) {}

    public function show(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        $user->load('roles');

        return response()->json([
            'success' => true,
            'data' => $this->accountPayload($user),
        ]);
    }

    public function rolesStatus(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        return response()->json([
            'success' => true,
            'data' => $this->rolesStatusPayload($user),
        ]);
    }

    public function requestDeletion(DeleteAccountRequest $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        if ($user->hasPendingDeletion()) {
            return response()->json([
                'success' => false,
                'message' => 'Account deletion is already scheduled.',
            ], 422);
        }

        $user = $this->accountDeletionService->requestDeletion($user);

        return response()->json([
            'success' => true,
            'message' => 'Your account has been deactivated and is scheduled for permanent deletion after the grace period.',
            'data' => [
                'account_deletion' => $this->accountDeletionService->deletionMeta($user),
            ],
        ]);
    }

    public function changePassword(ChangePasswordRequest $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        $user->forceFill([
            'password' => $request->validated('password'),
        ])->save();

        return response()->json([
            'success' => true,
            'message' => 'Password updated successfully.',
        ]);
    }

    private function accountPayload(User $user): array
    {
        $roles = AppRoleContext::assignedMobileRoles($user);

        return [
            'user' => [
                'id' => $user->id,
                'first_name' => $user->first_name,
                'last_name' => $user->last_name,
                'middle_name' => $user->middle_name,
                'email' => $user->email,
                'phone_number' => $user->phone_number,
                'status' => $user->status,
                'status_reason' => $user->status_reason,
                'active_role' => $user->active_role,
                'email_verified_at' => $user->email_verified_at,
                'phone_verified_at' => $user->phone_verified_at,
            ],
            'roles' => $roles,
            'role_selection_required' => count($roles) > 1 && !$user->active_role,
            'roles_status' => $this->rolesStatusPayload($user),
            'account_deletion' => $this->accountDeletionService->deletionMeta($user),
        ];
    }

    private function rolesStatusPayload(User $user): array
    {
        $assigned = AppRoleContext::assignedMobileRoles($user);
        $profiles = [];

        foreach ($assigned as $role) {
            $profiles[$role] = match ($role) {
                Role::DRIVER => $user->driver()->exists(),
                Role::COMMUTER => $user->commuter()->exists(),
                Role::ORGANIZATION => Organization::query()
                    ->where('owner_user_id', $user->id)
                    ->exists(),
                default => false,
            };
        }

        return [
            'assigned_roles' => $assigned,
            'active_role' => $user->active_role,
            'profiles' => $profiles,
        ];
    }
}
