<?php

namespace App\Http\Controllers;

use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Foundation\Validation\ValidatesRequests;
use Illuminate\Routing\Controller as BaseController;

class Controller extends BaseController
{
    use AuthorizesRequests, ValidatesRequests;


    /**
     * Abort with 403 if the authenticated user lacks any of the given permissions.
     * Super-admins always pass.
     *
     * Usage: $this->authorizePermissions($request, 'view_users', 'edit_users');
     */
    protected function authorizePermissions(\Illuminate\Http\Request $request, string ...$permissions): void
    {
        $user = $request->user();

        if (!$user) {
            abort(403, 'Unauthorized.');
        }

        if ($user->isSuperAdmin()) {
            return;
        }

        if (!$user->hasAnyPermission($permissions)) {
            abort(403, 'You do not have permission to perform this action.');
        }
    }
}
