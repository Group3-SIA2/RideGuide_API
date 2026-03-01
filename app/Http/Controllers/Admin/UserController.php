<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;

class UserController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth');
    }

    public function index()
    {
        $users = User::with('role')
            ->whereNotNull('email_verified_at')
            ->latest()
            ->paginate(15);

        return view('admin.users.index', compact('users'));
    }
}
