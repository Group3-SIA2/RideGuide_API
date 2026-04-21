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

    public function confirm()
    {
        return view('admin.logout');
    }

    public function logout(Request $request)
    {
        $user = $request->user();

        if ($user && ($user->hasRole(Role::SUPER_ADMIN) || $user->hasRole(Role::ADMIN))) {
            TransactionLogbook::write(
                request: $request,
                module: 'auth',
                transactionType: 'logout',
                status: 'success',
                referenceType: 'user',
                referenceId: (string) $user->id,
                after: [
                    'role_scope' => $user->hasRole(Role::SUPER_ADMIN) ? Role::SUPER_ADMIN : Role::ADMIN,
                ]
            );
        }

        Auth::logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login');
    }
}