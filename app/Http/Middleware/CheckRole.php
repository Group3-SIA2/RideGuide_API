<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CheckRole
{
    /**
     * Handle an incoming request.
     * Checks if the authenticated user has the required role.
     *
     * @param  string  $role  The role to check for (e.g., 'driver', 'commuter', 'admin')
     */
    public function handle(Request $request, Closure $next, string $role): Response
    {
        $user = $request->user();

        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthenticated.',
            ], 401);
        }

        if (!$user->hasRole($role)) {
            return response()->json([
                'success' => false,
                'message' => "Unauthorized. This endpoint requires the '{$role}' role.",
            ], 403);
        }

        return $next($request);
    }
}
