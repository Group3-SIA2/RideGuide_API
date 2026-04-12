<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Mail\OtpMail;
use App\Models\Otp;
use App\Models\Role;
use App\Models\User;
use App\Support\TransactionLogbook;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;

class Auth2faController extends Controller
{
    public function showLogin()
    {
        return view('auth.login');
    }

    public function loginStep1(Request $request)
    {
        $validated = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
        ]);

        $user = User::where('email', $validated['email'])->first();

        if (!$user || !Hash::check($validated['password'], $user->password)) {
            return back()->withErrors(['email' => 'Invalid credentials'])->withInput();
        }

        if (!$user->isAccountActive()) {
            return back()->withErrors(['email' => 'Your account is not active.'])->withInput();
        }

        // invalidate old unused login_2fa otp
        Otp::where('user_id', $user->id)->where('type', 'login_2fa')->whereNull('used_at')->update(['used_at' => now()]);

        $code = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);

        Otp::create([
            'user_id' => $user->id,
            'code' => $code,
            'type' => 'login_2fa',
            'expires_at' => now()->addMinutes(10),
        ]);

        Mail::to($user->email)->send(new OtpMail(
            otpCode: $code,
            type: 'login_2fa',
            recipientEmail: $user->email
        ));

        session([
            '2fa:user_id' => $user->id,
            '2fa:remember' => (bool) $request->boolean('remember'),
        ]);

        return redirect()->route('admin.2fa.form');
    }

    public function show2faForm()
    {
        abort_unless(session()->has('2fa:user_id'), 403);
        return view('auth.otp');
    }

    public function verify2fa(Request $request)
    {
        $validated = $request->validate([
            'otp' => ['required', 'digits:6'],
        ]);

        $userId = session('2fa:user_id');
        abort_unless($userId, 403);

        $otp = Otp::where('user_id', $userId)
            ->where('type', 'login_2fa')
            ->where('code', $validated['otp'])
            ->whereNull('used_at')
            ->where('expires_at', '>', now())
            ->latest()
            ->first();

        if (!$otp) {
            return back()->withErrors(['otp' => 'Invalid or expired OTP']);
        }

        $otp->update(['used_at' => now()]);

        $user = User::findOrFail($userId);

        if (!$user->isAccountActive()) {
            session()->forget(['2fa:user_id', '2fa:remember']);
            return redirect()->route('login')->withErrors(['email' => 'Your account is not active.']);
        }

        Auth::login($user, (bool) session('2fa:remember', false));
        $request->session()->regenerate();

        if ($user->hasRole(Role::SUPER_ADMIN) || $user->hasRole(Role::ADMIN)) {
            TransactionLogbook::write(
                request: $request,
                module: 'auth',
                transactionType: 'login',
                status: 'success',
                referenceType: 'user',
                referenceId: (string) $user->id,
                after: [
                    'role_scope' => $user->hasRole(Role::SUPER_ADMIN) ? Role::SUPER_ADMIN : Role::ADMIN,
                ]
            );
        }

        session()->forget(['2fa:user_id', '2fa:remember']);

        if ($user->hasRole(Role::SUPER_ADMIN)) {
            return redirect()->route('super-admin.dashboard');
        }

        if (
            ($user->hasRole(Role::ORGANIZATION) || $user->hasAnyActiveOrganizationManagement())
            && !$user->hasRole(Role::ADMIN)
            && !$user->hasRole(Role::SUPER_ADMIN)
        ) {
            return redirect()->route('org-manager.dashboard');
        }

        return redirect()->intended('/admin/dashboard');
    }

    public function logout(Request $request)
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();
        return redirect()->route('login');
    }
}