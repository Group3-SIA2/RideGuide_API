<?php

namespace App\Http\Controllers\Auth;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CheckPermission
{
    /**
     * Handle an incoming request.
     *
     * Usage in routes:  ->middleware('permission:manage_users')
     *                   ->middleware('permission:manage_users,manage_drivers')  (any of)
     */
    public function handle(Request $request, Closure $next, string ...$permissions): Response
    {
        $user = $request->user();

        if (!$user) {
            abort(403, 'Unauthorized.');
        }

        // Super admins bypass all permission checks
        if ($user->isSuperAdmin()) {
            return $next($request);
        }

        // Check if the user has any of the required permissions
        if (!$user->hasAnyPermission($permissions)) {
            abort(403, 'You do not have permission to perform this action.');
        }

        return $next($request);
    }
}
