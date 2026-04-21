<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Role;
use App\Support\TransactionLogbook;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class LogoutController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth');
    }

    /**
     * Show the logout confirmation page.
     */
    public function confirm()
    {
        return view('admin.logout');
    }

    /**
     * Log the admin out and redirect to login.
     */
    public function logout(Request $request)
    {
        $user = $request->user();

        if ($user) {
            TransactionLogbook::write(
                request: $request,
                module: 'auth',
                transactionType: 'logout',
                status: 'success',
                referenceType: 'user',
                referenceId: (string) $user->id,
                after: [
                    'role_scope' => $user->hasRole(Role::SUPER_ADMIN)
                        ? Role::SUPER_ADMIN
                        : ($user->hasRole(Role::ADMIN) ? Role::ADMIN : 'standard_user'),
                ]
            );
        }

        Auth::logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login');
    }
}
