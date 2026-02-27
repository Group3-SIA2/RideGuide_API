<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Mail\OtpMail;
use App\Models\Otp;
use App\Models\Role;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password;

class AuthController extends Controller
{
    /**
     * Register a new user.
     *
     * POST /api/register
     * Body: name, email, password, password_confirmation, role (admin|driver|commuter)
     */
    public function register(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'email'=> ['required', 'string', 'email', 'max:255', 'unique:users,email'],
            'password'=> ['required', 'string', 'confirmed', Password::min(8)],
        ]);

        $user = User::create([
            'email'=> $validated['email'],
            'password'=> $validated['password'],
        ]);

        // Generate and send email verification OTP
        $this->generateAndSendOtp($user, 'email_verification');

        return response()->json([
            'success' => true,
            'message' => 'Registration successful. Please check your email for the OTP to verify your account.',
            'data'=> [
                'user'=> [
                    'id'=> $user->id,
                    'email'=> $user->email,
                ],
            ],
        ], 201);

    }

    /**
     * Login user with email & password, then send 2FA OTP via email.
     *
     * POST /api/login
     * Body: email, password
     */
    public function login(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'email'=> ['required', 'string', 'email'],
            'password' => ['required', 'string'],
        ]);

        $user = User::where('email', $validated['email'])->first();

        if (!$user || !Hash::check($validated['password'], $user->password)) {
            return response()->json([
                'success' => false,
                'message' => 'The provided credentials are incorrect.',
            ], 401);
        }

        if (!$user->isEmailVerified()) {
            // Resend email verification OTP
            $this->generateAndSendOtp($user, 'email_verification');

            return response()->json([
                'success' => false,
                'message' => 'Your email is not verified. A new verification OTP has been sent to your email.',
            ], 403);
        }

        // Check if user already has an active token (already logged in)
        if ($user->tokens()->count() > 0) {
            return response()->json([
                'success' => false,
                'message' => 'You are already logged in. Please logout first before logging in again.',
            ], 409);
        }

        // Check if there is already a pending unused 2FA OTP
        $pendingOtp = Otp::where('user_id', $user->id)
            ->where('type', 'login_2fa')
            ->whereNull('used_at')
            ->where('expires_at', '>', now())
            ->first();

        if ($pendingOtp) {
            return response()->json([
                'success' => false,
                'message' => 'A 2FA OTP has already been sent to your email. Please check your email or wait for it to expire before requesting a new one.',
            ], 429);
        }

        // Generate and send 2FA login OTP
        $this->generateAndSendOtp($user, 'login_2fa');

        return response()->json([
            'success' => true,
            'message' => 'Credentials verified. Please check your email for the 2FA OTP to complete login.',
        ], 200);
    }

    /**
     * Verify OTP for email verification or 2FA login.
     *
     * POST /api/verify-otp
     * Body: email, otp, type (email_verification|login_2fa)
     */
    public function verifyOtp(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'email'=> ['required', 'string', 'email'],
            'otp'=> ['required', 'string', 'size:6'],
            'type'=> ['required', 'string', 'in:email_verification,login_2fa'],
        ]);

        $user = User::where('email', $validated['email'])->first();

        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'User not found.',
            ], 404);
        }

        $otp = Otp::where('user_id', $user->id)
            ->where('code', $validated['otp'])
            ->where('type', $validated['type'])
            ->whereNull('used_at')
            ->where('expires_at', '>', now())
            ->latest()
            ->first();

        if (!$otp) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid or expired OTP.',
            ], 422);
        }

        $otp->markAsUsed();

        // Handle email verification — verify email and auto-login
        if ($validated['type'] === 'email_verification') {
            $user->email_verified_at = now();
            $user->save();

            // Revoke any existing tokens
            $user->tokens()->delete();

            // Auto-login: issue Sanctum token immediately after email verification
            $token = $user->createToken('auth-token')->plainTextToken;

            return response()->json([
                'success' => true,
                'message' => 'Email verified successfully. You are now logged in.',
                'data'=> [
                    'user' => [
                        'id'=> $user->id,
                        'email'=> $user->email,
                        'email_verified_at'=> $user->email_verified_at,
                    ],
                    'token' => $token,
                    'token_type' => 'Bearer',
                ],
            ], 200);
        }

        // Handle 2FA login - issue Sanctum token
        if ($validated['type'] === 'login_2fa') {
            // Revoke all previous tokens
            $user->tokens()->delete();

            $token = $user->createToken('auth-token')->plainTextToken;

            return response()->json([
                'success' => true,
                'message' => 'Login successful.',
                'data'=> [
                    'user'  => [
                        'id'=> $user->id,
                        'email'=> $user->email,
                        'email_verified_at'=> $user->email_verified_at,
                    ],
                    'token' => $token,
                    'token_type' => 'Bearer',
                ],
            ], 200);
        }

        return response()->json([
            'success'=> false,
            'message'=> 'Invalid OTP type.',
        ], 422);
    }

    /**
     * Logout the authenticated user (revoke current token).
     *
     * POST /api/logout
     * Header: Authorization: Bearer {token}
     */
    public function logout(Request $request): JsonResponse
    {
        // Revoke the current access token
        $request->user()->currentAccessToken()->delete();

        return response()->json([
            'success' => true,
            'message' =>'Logged out successfully.',
        ], 200);
    }

    /**
     * Request OTP for password reset.
     *
     * POST /api/forgot-password
     * Body: email
     */
    public function forgotPassword(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'email' => ['required', 'string', 'email'],
        ]);

        $user = User::where('email', $validated['email'])->first();

        if (!$user) {
            // Return success even if user not found to prevent email enumeration
            return response()->json([
                'success' => true,
                'message' => 'If an account with that email exists, a password reset OTP has been sent.',
            ], 200);
        }

        // Limit: max 3 forgot-password requests per day
        $dailyCount = Otp::where('user_id', $user->id)
            ->where('type', 'password_reset')
            ->where('created_at', '>', now()->startOfDay())
            ->count();

        if ($dailyCount >= 3) {
            return response()->json([
                'success' => false,
                'message' => 'You have reached the maximum of 3 password reset requests for today. Please try again tomorrow.',
            ], 429);
        }

        $this->generateAndSendOtp($user, 'password_reset');

        return response()->json([
            'success' => true,
            'message' => 'If an account with that email exists, a password reset OTP has been sent.',
        ], 200);
    }

    /**
     * Reset password using OTP.
     *
     * POST /api/reset-password
     * Body: email, otp, password, password_confirmation
     */
    public function resetPassword(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'email'=> ['required', 'string', 'email'],
            'otp'=> ['required', 'string', 'size:6'],
            'password'=> ['required', 'string', 'confirmed', Password::min(8)],
        ]);

        $user = User::where('email', $validated['email'])->first();

        if (!$user) {
            return response()->json([
                'success'=> false,
                'message'=> 'User not found.',
            ], 404);
        }

        $otp = Otp::where('user_id', $user->id)
            ->where('code', $validated['otp'])
            ->where('type', 'password_reset')
            ->whereNull('used_at')
            ->where('expires_at', '>', now())
            ->latest()
            ->first();

        if (!$otp) {
            return response()->json([
                'success'=> false,
                'message'=> 'Invalid or expired OTP.',
            ], 422);
        }

        // Limit: max 3 reset-password attempts per day
        $dailyResetAttempts = Otp::where('user_id', $user->id)
            ->where('type', 'password_reset')
            ->whereNotNull('used_at')
            ->where('updated_at', '>', now()->startOfDay())
            ->count();

        if ($dailyResetAttempts >= 3) {
            return response()->json([
                'success'=> false,
                'message'=> 'You have reached the maximum of 3 password reset attempts for today. Please try again tomorrow.',
            ], 429);
        }

        $otp->markAsUsed();

        // Update the password
        $user->update([
            'password'=> $validated['password'],
        ]);

        // Revoke all existing tokens for security
        $user->tokens()->delete();

        return response()->json([
            'success' => true,
            'message' => 'Password reset successfully. Please login with your new password.',
        ], 200);
    }

    /**
     * Resend OTP for email verification.
     *
     * POST /api/resend-otp
     * Body: email, type (email_verification|password_reset)
     */
    public function resendOtp(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'email'=> ['required', 'string', 'email'],
            'type'=> ['required', 'string', 'in:email_verification,password_reset'],
        ]);

        $user = User::where('email', $validated['email'])->first();

        if (!$user) {
            return response()->json([
                'success'=> true,
                'message'=> 'If the email exists, a new OTP has been sent.',
            ], 200);
        }

        // Rate limit: check if an OTP was sent in the last 60 seconds
        $recentOtp = Otp::where('user_id', $user->id)
            ->where('type', $validated['type'])
            ->where('created_at', '>', now()->subSeconds(60))
            ->first();

        if ($recentOtp) {
            return response()->json([
                'success'=> false,
                'message'=> 'Please wait 60 seconds before requesting a new OTP.',
            ], 429);
        }

        // Limit: max 3 resend-otp requests per type per day
        $dailyResendCount = Otp::where('user_id', $user->id)
            ->where('type', $validated['type'])
            ->where('created_at', '>', now()->startOfDay())
            ->count();

        if ($dailyResendCount >= 3) {
            return response()->json([
                'success'=> false,
                'message'=> 'You have reached the maximum of 3 OTP resend requests for today. Please try again tomorrow.',
            ], 429);
        }

        $this->generateAndSendOtp($user, $validated['type']);

        return response()->json([
            'success'=> true,
            'message'=> 'If the email exists, a new OTP has been sent.',
        ], 200);
    }

    /**
     * Generate a 6-digit OTP, save it, and send it via email.
     */
    private function generateAndSendOtp(User $user, string $type): void
    {
        // Invalidate any existing unused OTPs of the same type
        Otp::where('user_id', $user->id)
            ->where('type', $type)
            ->whereNull('used_at')
            ->update(['used_at' => now()]);

        // Generate a 6-digit OTP
        $code = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);

        // Store OTP with 10-minute expiration
        Otp::create([
            'user_id'=> $user->id,
            'code'=> $code,
            'type'=> $type,
            'expires_at' => now()->addMinutes(10),
        ]);

        // Send OTP via email (Gmail SMTP)
        Mail::to($user->email)->send(new OtpMail(
            otpCode:$code,
            type: $type,
            recipientEmail: $user->email,
        ));
    }
}
