<?php

namespace App\Http\Controllers\Auth;

use App\Models\User;
use App\Http\Controllers\Controller;
use Illuminate\Foundation\Auth\SendsPasswordResetEmails;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class ForgotPasswordController extends Controller
{
    /*
    |--------------------------------------------------------------------------
    | Password Reset Controller
    |--------------------------------------------------------------------------
    |
    | This controller is responsible for handling password reset emails and
    | includes a trait which assists in sending these notifications from
    | your application to your users. Feel free to explore this trait.
    |
    */

    use SendsPasswordResetEmails;

    public function sendResetLinkEmail(Request $request): RedirectResponse
    {
        $this->validateEmail($request);

        $email = strtolower(trim((string) $request->input('email')));

        $user = User::whereRaw('LOWER(email) = ?', [$email])->first();

        if ($user && $user->isAdminOrSuperAdmin()) {
            return back()
                ->withInput($request->only('email'))
                ->withErrors([
                    'email' => 'Your account is not eligible for password reset.',
                ]);
        }

        return parent::sendResetLinkEmail($request);
    }
}
