<?php

namespace App\Http\Middleware;

use App\Support\AppRoleContext;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ResolveActiveRole
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (!$user) {
            return $next($request);
        }

        $activeRole = is_string($user->active_role) ? strtolower($user->active_role) : null;
        $assignedRoles = AppRoleContext::assignedMobileRoles($user);

        if ($activeRole !== null && !in_array($activeRole, $assignedRoles, true)) {
            $activeRole = null;
        }

        if ($activeRole === null && count($assignedRoles) === 1) {
            $activeRole = $assignedRoles[0];
        }

        if (($user->active_role ?? null) !== $activeRole) {
            // Revoke stale contexts or normalize one-role users to deterministic active role.
            $user->forceFill(['active_role' => $activeRole])->save();
        }

        if (is_string($activeRole) && in_array($activeRole, $assignedRoles, true)) {
            $request->attributes->set('active_role', $activeRole);
        } else {
            $request->attributes->set('active_role', null);
        }

        $request->attributes->set('assigned_mobile_roles', $assignedRoles);

        return $next($request);
    }
}

