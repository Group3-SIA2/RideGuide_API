<?php

namespace App\Http\Middleware;

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

        if ($user->isSuperAdmin()) {
            $targetRoute = 'super-admin.dashboard';
        } elseif ($user->isOrganizationScoped()) {
            $targetRoute = 'org-manager.dashboard';
        }

        $isAllowed = match ($panel) {
            'super-admin' => $user->isSuperAdmin(),
            'org-manager' => $user->isOrganizationScoped(),
            'admin' => !$user->isSuperAdmin() && !$user->isOrganizationScoped() && $user->hasPermission('view_admin_dashboard'),
            default => false,
        };

        if (!$isAllowed) {
            return redirect()->route($targetRoute);
        }

        return $next($request);
    }
}
