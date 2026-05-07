<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class RequireActiveRole
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthenticated.',
            ], 401);
        }

        $activeRole = $request->attributes->get('active_role');
        if (is_string($activeRole) && $activeRole !== '') {
            return $next($request);
        }

        return response()->json([
            'success' => false,
            'message' => 'Role selection is required before accessing this endpoint.',
            'code' => 'role_selection_required',
            'data' => [
                'roles' => $request->attributes->get('assigned_mobile_roles', []),
                'active_role' => null,
            ],
        ], 409);
    }
}

