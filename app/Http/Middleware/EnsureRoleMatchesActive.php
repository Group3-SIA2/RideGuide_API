<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureRoleMatchesActive
{
    public function handle(Request $request, Closure $next, string $role): Response
    {
        $requiredRole = strtolower($role);
        $activeRole = $request->attributes->get('active_role');

        if (!is_string($activeRole) || strtolower($activeRole) !== $requiredRole) {
            return response()->json([
                'success' => false,
                'message' => "Active role must be '{$requiredRole}' for this endpoint.",
            ], 403);
        }

        return $next($request);
    }
}

