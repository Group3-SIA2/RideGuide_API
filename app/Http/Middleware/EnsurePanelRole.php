<?php

namespace App\Http\Middleware;

use App\Models\Role;
use Closure;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsurePanelRole
{
    /**
     * Ensure users only access their intended panel URL.
     */
    public function handle(Request $request, Closure $next, string $panel): Response|RedirectResponse
    {
        $user = $request->user();

        if (!$user) {
            return redirect()->route('login');
        }

        $targetRoute = 'admin.dashboard';

        if ($user->hasRole(Role::SUPER_ADMIN)) {
            $targetRoute = 'super-admin.dashboard';
        } elseif (
            ($user->hasRole(Role::ORGANIZATION) || $user->hasAnyActiveOrganizationManagement())
            && !$user->hasRole(Role::ADMIN)
            && !$user->hasRole(Role::SUPER_ADMIN)
        ) {
            $targetRoute = 'org-manager.dashboard';
        }

        $isAllowed = match ($panel) {
            'super-admin' => $user->hasRole(Role::SUPER_ADMIN),
            'org-manager' => (
                ($user->hasRole(Role::ORGANIZATION) || $user->hasAnyActiveOrganizationManagement())
                && !$user->hasRole(Role::ADMIN)
                && !$user->hasRole(Role::SUPER_ADMIN)
            ),
            'admin' => $user->hasRole(Role::ADMIN) && !$user->hasRole(Role::SUPER_ADMIN),
            default => false,
        };

        if (!$isAllowed) {
            return redirect()->route($targetRoute);
        }

        return $next($request);
    }
}
