<?php

namespace App\Http\Controllers;

use App\Models\Role;
use Illuminate\Http\Request;

class HomeController extends Controller
{
    /**
     * Create a new controller instance.
     *
     * @return void
     */
    public function __construct()
    {
        $this->middleware('auth');
    }

    /**
     * Show the application dashboard.
     *
     * @return \Illuminate\Http\RedirectResponse
     */
    public function index()
    {
        $user = auth()->user();

        if ($user && $user->hasRole(Role::SUPER_ADMIN)) {
            return redirect()->route('super-admin.dashboard');
        }

        if (
            $user
            && ($user->hasRole(Role::ORGANIZATION) || $user->hasAnyActiveOrganizationManagement())
            && !$user->hasRole(Role::ADMIN)
            && !$user->hasRole(Role::SUPER_ADMIN)
        ) {
            return redirect()->route('org-manager.dashboard');
        }

        return redirect()->route('admin.dashboard');
    }
}
