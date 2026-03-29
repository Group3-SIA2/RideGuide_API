<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\Role;
use Illuminate\Foundation\Auth\AuthenticatesUsers;

class LoginController extends Controller
{
    /*
    |--------------------------------------------------------------------------
    | Login Controller
    |--------------------------------------------------------------------------
    |
    | This controller handles authenticating users for the application and
    | redirecting them to your home screen. The controller uses a trait
    | to conveniently provide its functionality to your applications.
    |
    */

    use AuthenticatesUsers;

    protected function redirectTo(): string
    {
        $user = auth()->user();

        if ($user && $user->hasRole(Role::SUPER_ADMIN)) {
            return route('super-admin.dashboard');
        }

        if (
            $user
            && ($user->hasRole(Role::ORGANIZATION) || $user->hasAnyActiveOrganizationManagement())
            && !$user->hasRole(Role::ADMIN)
            && !$user->hasRole(Role::SUPER_ADMIN)
        ) {
            return route('org-manager.dashboard');
        }

        return route('admin.dashboard');
    }

    /**
     * Create a new controller instance.
     *
     * @return void
     */
    public function __construct()
    {
        $this->middleware('guest')->except('logout');
        $this->middleware('auth')->only('logout');
    }
}
