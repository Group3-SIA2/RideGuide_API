<?php
// filepath: c:\Users\ACER\Herd\RideGuide\app\Http\Controllers\Admin\UserController.php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Role;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;

class UserController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth');
    }

    // OLD (kept as-is)
    public function index(Request $request)
    {
        $totalActiveUsers = User::whereNotNull('email_verified_at')
            ->where('status', User::STATUS_ACTIVE)
            ->count();
        $totalInactiveUsers = User::whereNotNull('email_verified_at')
            ->where('status', User::STATUS_INACTIVE)
            ->count();
        $totalSuspendedUsers = User::whereNotNull('email_verified_at')
            ->where('status', User::STATUS_SUSPENDED)
            ->count();

        $query = User::with('roles')
            ->whereNotNull('email_verified_at');

        if ($search = $request->input('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('first_name', 'like', "%{$search}%")
                  ->orWhere('last_name', 'like', "%{$search}%")
                  ->orWhere('email', 'like', "%{$search}%");
            });
        }

        if ($role = $request->input('role')) {
            $query->whereHas('roles', fn ($q) => $q->where('name', $role));
        }

        if ($status = $request->input('status')) {
            $query->where('status', $status);
        }

        $users = $query->latest()->paginate(15)->withQueryString();

        if ($request->ajax()) {
            return response()->json([
                'rows'       => view('admin.users._rows', compact('users'))->render(),
                'pagination' => $users->hasPages() ? (string) $users->links() : '',
                'total'      => $users->total(),
            ]);
        }

        return view('admin.users.index', compact(
            'users',
            'totalActiveUsers',
            'totalInactiveUsers',
            'totalSuspendedUsers'
        ));
    }

    // NEW
    public function create()
    {
        $roles = Role::orderBy('name')->get();
        return view('admin.users.create', compact('roles'));
    }

    // NEW
    public function store(Request $request)
    {
        $validated = $request->validate([
            'first_name'   => ['required', 'string', 'max:255'],
            'middle_name'  => ['nullable', 'string', 'max:255'],
            'last_name'    => ['required', 'string', 'max:255'],
            'email'        => ['required', 'email', 'max:255', 'unique:users,email'],
            'phone_number' => ['nullable', 'string', 'max:20'],
            'password'     => ['required', 'string', 'min:8', 'confirmed'],
            'role'         => ['nullable', 'string', Rule::exists('roles', 'name')],
            'status'       => ['nullable', Rule::in([User::STATUS_ACTIVE, User::STATUS_INACTIVE, User::STATUS_SUSPENDED])],
            'status_reason' => ['nullable', 'string', 'max:255'],
        ]);

        $status = $validated['status'] ?? User::STATUS_ACTIVE;

        $user = User::create([
            'first_name'   => $validated['first_name'],
            'middle_name'  => $validated['middle_name'] ?? null,
            'last_name'    => $validated['last_name'],
            'email'        => $validated['email'],
            'phone_number' => $validated['phone_number'] ?? null,
            'password'     => Hash::make($validated['password']),
            'status'       => $status,
            'status_reason' => $validated['status_reason'] ?? null,
            'status_changed_at' => $status !== User::STATUS_ACTIVE ? now() : null,
        ]);

        if (!empty($validated['role'])) {
            $role = Role::where('name', $validated['role'])->first();
            if ($role) {
                $user->roles()->sync([$role->id]);
            }
        }

        return redirect()->route('admin.users.index')
            ->with('success', 'User registered successfully.');
    }

    // NEW
    public function edit(User $user)
    {
        $roles = Role::orderBy('name')->get();
        return view('admin.users.edit', compact('user', 'roles'));
    }

    // NEW
    public function update(Request $request, User $user)
    {
        $validated = $request->validate([
            'first_name'   => ['required', 'string', 'max:255'],
            'middle_name'  => ['nullable', 'string', 'max:255'],
            'last_name'    => ['required', 'string', 'max:255'],
            'email'        => ['required', 'email', 'max:255', Rule::unique('users', 'email')->ignore($user->id)],
            'phone_number' => ['nullable', 'string', 'max:20'],
            'password'     => ['nullable', 'string', 'min:8', 'confirmed'],
            'role'         => ['nullable', 'string', Rule::exists('roles', 'name')],
            'status'       => ['nullable', Rule::in([User::STATUS_ACTIVE, User::STATUS_INACTIVE, User::STATUS_SUSPENDED])],
            'status_reason' => ['nullable', 'string', 'max:255'],
        ]);

        $status = $validated['status'] ?? $user->status;
        $statusChanged = $status !== $user->status;

        $user->fill([
            'first_name'   => $validated['first_name'],
            'middle_name'  => $validated['middle_name'] ?? null,
            'last_name'    => $validated['last_name'],
            'email'        => $validated['email'],
            'phone_number' => $validated['phone_number'] ?? null,
            'status'       => $status,
            'status_reason' => $validated['status_reason'] ?? $user->status_reason,
            'status_changed_at' => $statusChanged ? now() : $user->status_changed_at,
        ]);

        if (!empty($validated['password'])) {
            $user->password = Hash::make($validated['password']);
        }

        $user->save();

        if (!empty($validated['role'])) {
            $role = Role::where('name', $validated['role'])->first();
            $user->roles()->sync($role ? [$role->id] : []);
        }

        return redirect()->route('admin.users.index')
            ->with('success', 'Profile updated successfully.');
    }

    // NEW
    public function destroy(User $user)
    {
        if (auth()->id() === $user->id) {
            return back()->withErrors(['delete' => 'You cannot delete your own account.']);
        }

        $user->delete();

        return redirect()->route('admin.users.index')
            ->with('success', 'Account deleted successfully.');
    }
}