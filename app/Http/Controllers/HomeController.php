<?php

namespace App\Http\Controllers;

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

        if ($user && $user->isSuperAdmin()) {
            return redirect()->route('super-admin.dashboard');
        }

        if ($user && $user->isOrganizationScoped()) {
            return redirect()->route('org-manager.dashboard');
        }

        return redirect()->route('admin.dashboard');
    }
}
