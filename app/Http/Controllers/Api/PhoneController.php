<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Otp;
use App\Models\User;
use App\Support\InputValidation;
use App\Support\TransactionLogbook;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Throwable;

/**
 * Phone Number Authentication via iProgSMS
 *
 * Flow:
 *   Register  → POST /api/auth/phone/register   → OTP sent → POST /api/auth/phone/verify-otp (type: phone_verification)
 *   Login     → POST /api/auth/phone/login      → OTP sent → POST /api/auth/phone/verify-otp (type: login_2fa)
 *   Resend    → POST /api/auth/phone/resend-otp (phone_verification only)
 *
 * OTP generation & delivery is handled entirely by iProgSMS.
 * Our local `otps` table stores a dispatch record for rate limiting and
 * pending-OTP detection only — the actual OTP code is managed by iProgSMS.
 *
 * NOTE: The iProgSMS "GET OTP Lists" endpoint is intentionally NOT used.
 * It is an external admin/monitoring tool. All local tracking is handled
 * through our own otps table, keeping sensitive OTP data within our system.
 *
 * Accepted phone formats (all normalized to E.164 +639XXXXXXXXX in the DB):
 *   09XXXXXXXXX | 639XXXXXXXXX | +639XXXXXXXXX
 */
class PhoneController extends Controller
{
    private const OTP_COOLDOWN_SECONDS = 60;

    private const OTP_DAILY_LIMIT = 3;

    private const OTP_EXPIRY_MINUTES = 5; // mirrors iProgSMS default OTP lifetime

    // -------------------------------------------------------------------------
    // Public Endpoints
    // -------------------------------------------------------------------------

    /**
     * Register a new user with a Philippine mobile number.
     *
     * POST /api/auth/phone/register
     * Body: phone_number, password
     */
    public function register(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'phone_number' => ['required', 'string', 'regex:/^(\+639|639|09)\d{9}$/'],
            'password' => InputValidation::passwordRequiredRules(),
        ]);

        $phone = $this->normalizePhone($validated['phone_number']);

        if (User::where('phone_number', $phone)->exists()) {
            return response()->json([
                'success' => false,
                'message' => 'A user with this phone number is already registered.',
            ], 409);
        }

        $user = User::create([
            'phone_number' => $phone,
            'password' => $validated['password'],
        ]);

        $sent = $this->sendOtp($user, 'phone_verification');

        if (! $sent) {
            // Roll back user creation so the number can be retried cleanly
            $user->forceDelete();

            return response()->json([
                'success' => false,
                'message' => 'We could not send the verification OTP. Please try again.',
            ], 503);
        }

        $this->writeAuthLog(
            request: $request,
            user: $user,
            transactionType: 'register',
            status: 'success',
            metadata: [
                'auth_channel' => 'phone_password',
            ]
        );

        return response()->json([
            'success' => true,
            'message' => 'Registration successful. An OTP has been sent to your phone number.',
            'data' => [
                'user' => [
                    'id' => $user->id,
                    'phone_number' => $user->phone_number,
                ],
            ],
        ], 201);
    }

    /**
     * Login with a Philippine mobile number + password.
     * On valid credentials, sends a 2FA OTP via iProgSMS.
     *
     * POST /api/auth/phone/login
     * Body: phone_number, password
     */
    public function login(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'phone_number' => ['required', 'string', 'regex:/^(\+639|639|09)\d{9}$/'],
            'password' => ['required', 'string'],
        ]);

        $phone = $this->normalizePhone($validated['phone_number']);
        $user = User::where('phone_number', $phone)->first();

        if (! $user || ! Hash::check($validated['password'], $user->password)) {
            return response()->json([
                'success' => false,
                'message' => 'The provided credentials are incorrect.',
            ], 401);
        }

        if (! $user->isAccountActive()) {
            return response()->json([
                'success' => false,
                'message' => 'Your account is not active.',
            ], 403);
        }

        if (! $user->isPhoneVerified()) {
            // Resend phone verification OTP automatically
            $this->sendOtp($user, 'phone_verification');

            return response()->json([
                'success' => false,
                'message' => 'Your phone number is not verified. A new verification OTP has been sent.',
            ], 403);
        }

        if ($user->tokens()->count() > 0) {
            return response()->json([
                'success' => false,
                'message' => 'You are already logged in. Please logout first before logging in again.',
            ], 409);
        }

        // Guard against duplicate 2FA requests
        $pendingOtp = Otp::where('user_id', $user->id)
            ->where('type', 'login_2fa')
            ->whereNull('used_at')
            ->where('expires_at', '>', now())
            ->first();

        if ($pendingOtp) {
            return response()->json([
                'success' => false,
                'message' => 'A 2FA OTP has already been sent. Please check your phone or wait for it to expire before requesting a new one.',
            ], 429);
        }

        $sent = $this->sendOtp($user, 'login_2fa');

        if (! $sent) {
            return response()->json([
                'success' => false,
                'message' => 'We could not send the 2FA OTP. Please try again.',
            ], 503);
        }

        return response()->json([
            'success' => true,
            'message' => 'Credentials verified. An OTP has been sent to your phone to complete login.',
        ], 200);
    }

    /**
     * Verify an OTP sent by iProgSMS.
     * Issues a Sanctum token on success.
     *
     * POST /api/auth/phone/verify-otp
     * Body: phone_number, otp, type (phone_verification | login_2fa)
     */
    public function verifyOtp(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'phone_number' => ['required', 'string', 'regex:/^(\+639|639|09)\d{9}$/'],
            'otp' => ['required', 'string', 'size:6'],
            'type' => ['required', 'string', 'in:phone_verification,login_2fa'],
        ]);

        $phone = $this->normalizePhone($validated['phone_number']);
        $user = User::where('phone_number', $phone)->first();

        if (! $user) {
            return response()->json([
                'success' => false,
                'message' => 'User not found.',
            ], 404);
        }

        if (! $user->isAccountActive()) {
            return response()->json([
                'success' => false,
                'message' => 'Your account is not active.',
            ], 403);
        }

        // Ensure a dispatch record exists on our side before calling iProgSMS
        $localOtp = Otp::where('user_id', $user->id)
            ->where('type', $validated['type'])
            ->whereNull('used_at')
            ->where('expires_at', '>', now())
            ->latest()
            ->first();

        if (! $localOtp) {
            return response()->json([
                'success' => false,
                'message' => 'No active OTP found. Please request a new OTP.',
            ], 422);
        }

        // Delegate verification to iProgSMS (source of truth for OTP codes)
        $verified = $this->verifyWithIProgSMS(
            $this->toLocalPhone($phone),
            $validated['otp']
        );

        if (! $verified) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid or expired OTP.',
            ], 422);
        }

        // Mark our local dispatch record as consumed
        $localOtp->markAsUsed();

        if ($validated['type'] === 'phone_verification') {
            $user->phone_verified_at = now();
            $user->save();

            $user->tokens()->delete();
            $token = $user->createToken('auth-token')->plainTextToken;

            $this->writeAuthLog(
                request: $request,
                user: $user,
                transactionType: 'login',
                status: 'success',
                metadata: [
                    'auth_channel' => 'phone_password',
                    'via' => 'phone_verification_otp',
                ]
            );

            return response()->json([
                'success' => true,
                'message' => 'Phone number verified successfully. You are now logged in.',
                'data' => [
                    'user' => [
                        'id' => $user->id,
                        'phone_number' => $user->phone_number,
                        'phone_verified_at' => $user->phone_verified_at,
                    ],
                    'token' => $token,
                    'token_type' => 'Bearer',
                ],
            ], 200);
        }

        // login_2fa — issue Sanctum token
        $user->tokens()->delete();
        $token = $user->createToken('auth-token')->plainTextToken;

        $this->writeAuthLog(
            request: $request,
            user: $user,
            transactionType: 'login',
            status: 'success',
            metadata: [
                'auth_channel' => 'phone_password',
                'via' => 'login_2fa_otp',
            ]
        );

        return response()->json([
            'success' => true,
            'message' => 'Login successful.',
            'data' => [
                'user' => [
                    'id' => $user->id,
                    'phone_number' => $user->phone_number,
                    'phone_verified_at' => $user->phone_verified_at,
                ],
                'token' => $token,
                'token_type' => 'Bearer',
            ],
        ], 200);
    }

    /**
     * Request OTP for password reset.
     * Subject to max 3 requests per day.
     *
     * POST /api/auth/phone/forgot-password
     * Body: phone_number
     */
    public function forgotPassword(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'phone_number' => ['required', 'string', 'regex:/^(\+639|639|09)\d{9}$/'],
        ]);

        $phone = $this->normalizePhone($validated['phone_number']);
        $user = User::where('phone_number', $phone)->first();

        if (! $user) {
            // Return success even if user not found to prevent phone number enumeration
            return response()->json([
                'success' => true,
                'message' => 'If an account with that phone number exists, a password reset OTP has been sent.',
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

        $sent = $this->sendOtp($user, 'password_reset');

        if (! $sent) {
            return response()->json([
                'success' => false,
                'message' => 'We could not send the password reset OTP. Please try again.',
            ], 503);
        }

        return response()->json([
            'success' => true,
            'message' => 'If an account with that phone number exists, a password reset OTP has been sent.',
        ], 200);
    }

    /**
     * Reset password using OTP.
     *
     * POST /api/auth/phone/reset-password
     * Body: phone_number, otp, password, password_confirmation
     */
    public function resetPassword(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'phone_number' => ['required', 'string', 'regex:/^(\+639|639|09)\d{9}$/'],
            'otp' => ['required', 'string', 'size:6'],
            'password' => InputValidation::passwordConfirmedRules(),
        ]);

        $phone = $this->normalizePhone($validated['phone_number']);
        $user = User::where('phone_number', $phone)->first();

        if (! $user) {
            return response()->json([
                'success' => false,
                'message' => 'User not found.',
            ], 404);
        }

        $otp = Otp::where('user_id', $user->id)
            ->where('code', 'iprogs')
            ->where('type', 'password_reset')
            ->whereNull('used_at')
            ->where('expires_at', '>', now())
            ->latest()
            ->first();

        if (! $otp) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid or expired OTP.',
            ], 422);
        }

        // Verify OTP with iProgSMS
        $verified = $this->verifyWithIProgSMS(
            $this->toLocalPhone($phone),
            $validated['otp']
        );

        if (! $verified) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid or expired OTP.',
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
                'success' => false,
                'message' => 'You have reached the maximum of 3 password reset attempts for today. Please try again tomorrow.',
            ], 429);
        }

        $otp->markAsUsed();

        // Update the password
        $user->update([
            'password' => $validated['password'],
        ]);

        // Revoke all existing tokens for security
        $user->tokens()->delete();

        return response()->json([
            'success' => true,
            'message' => 'Password reset successfully. Please login with your new password.',
        ], 200);
    }

    /**
     * Resend a phone verification OTP.
     * Subject to 60-second cooldown and max 3 requests per day.
     *
     * POST /api/auth/phone/resend-otp
     * Body: phone_number, type (phone_verification)
     */
    public function resendOtp(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'phone_number' => ['required', 'string', 'regex:/^(\+639|639|09)\d{9}$/'],
            'type' => ['required', 'string', 'in:phone_verification'],
        ]);

        $phone = $this->normalizePhone($validated['phone_number']);
        $user = User::where('phone_number', $phone)->first();

        // Generic message to prevent phone number enumeration
        if (! $user) {
            return response()->json([
                'success' => true,
                'message' => 'If the phone number exists, a new OTP has been sent.',
            ], 200);
        }

        // 60-second cooldown
        $recentOtp = Otp::where('user_id', $user->id)
            ->where('type', $validated['type'])
            ->where('created_at', '>', now()->subSeconds(self::OTP_COOLDOWN_SECONDS))
            ->first();

        if ($recentOtp) {
            return response()->json([
                'success' => false,
                'message' => 'Please wait 60 seconds before requesting a new OTP.',
            ], 429);
        }

        // Daily request cap
        $dailyCount = Otp::where('user_id', $user->id)
            ->where('type', $validated['type'])
            ->where('created_at', '>', now()->startOfDay())
            ->count();

        if ($dailyCount >= self::OTP_DAILY_LIMIT) {
            return response()->json([
                'success' => false,
                'message' => 'You have reached the maximum of '.self::OTP_DAILY_LIMIT.' OTP requests for today. Please try again tomorrow.',
            ], 429);
        }

        $this->sendOtp($user, $validated['type']);

        return response()->json([
            'success' => true,
            'message' => 'If the phone number exists, a new OTP has been sent.',
        ], 200);
    }

    // -------------------------------------------------------------------------
    // Private Helpers
    // -------------------------------------------------------------------------

    /**
     * Send an OTP via the iProgSMS API and record the dispatch locally.
     * Returns true on success, false if the API call fails.
     *
     * The actual OTP code is generated and owned by iProgSMS.
     * We store a sentinel value ('iprogs') in the local otps table strictly
     * for rate limiting and pending-OTP detection — never for code matching.
     */
    private function sendOtp(User $user, string $type): bool
    {
        $response = Http::post(config('services.iprogsms.url').'/send_otp', [
            'api_token' => config('services.iprogsms.api_token'),
            'phone_number' => $this->toLocalPhone($user->phone_number),
        ]);

        if (! $response->successful() || $response->json('status') !== 'success') {
            return false;
        }

        // Invalidate any existing pending OTPs of the same type
        Otp::where('user_id', $user->id)
            ->where('type', $type)
            ->whereNull('used_at')
            ->update(['used_at' => now()]);

        // Record dispatch for rate limiting & pending-OTP checks
        Otp::create([
            'user_id' => $user->id,
            'code' => 'iprogs', // sentinel – iProgSMS manages the real code
            'type' => $type,
            'expires_at' => now()->addMinutes(self::OTP_EXPIRY_MINUTES),
        ]);

        return true;
    }

    /**
     * Verify an OTP against the iProgSMS API.
     * Returns true only when iProgSMS confirms the code is valid.
     */
    private function verifyWithIProgSMS(string $localPhone, string $otp): bool
    {
        $response = Http::post(config('services.iprogsms.url').'/verify_otp', [
            'api_token' => config('services.iprogsms.api_token'),
            'phone_number' => $localPhone,
            'otp' => $otp,
        ]);

        return $response->successful() && $response->json('status') === 'success';
    }

    /**
     * Normalize any accepted PH phone format to E.164 (+639XXXXXXXXX).
     * This is what gets stored in users.phone_number.
     */
    private function normalizePhone(string $phone): string
    {
        $phone = preg_replace('/[\s\-()]/', '', $phone);

        if (str_starts_with($phone, '09')) {
            return '+63'.substr($phone, 1); // 09XXXXXXXXX  → +639XXXXXXXXX
        }

        if (str_starts_with($phone, '639')) {
            return '+'.$phone;              // 639XXXXXXXXX → +639XXXXXXXXX
        }

        return $phone; // already +639XXXXXXXXX
    }

    /**
     * Convert stored E.164 (+639XXXXXXXXX) to PH local format (09XXXXXXXXX)
     * as required by the iProgSMS API.
     */
    private function toLocalPhone(string $e164): string
    {
        // +639XXXXXXXXX → strip '+63', prepend '0' → 09XXXXXXXXX
        return '0'.substr($e164, 3);
    }

    private function writeAuthLog(
        Request $request,
        User $user,
        string $transactionType,
        string $status,
        array $metadata = [],
        ?string $reason = null
    ): void {
        try {
            TransactionLogbook::write(
                request: $request,
                module: 'mobile_auth',
                transactionType: $transactionType,
                status: $status,
                referenceType: 'user',
                referenceId: (string) $user->id,
                reason: $reason,
                metadata: array_merge($this->clientMetadata($request), [
                    'actor_name' => $this->resolveActorName($user),
                ], $metadata),
                actorUserId: (string) $user->id,
                actorEmail: $user->email
            );
        } catch (Throwable $e) {
            report($e);
        }
    }

    private function clientMetadata(Request $request): array
    {
        return [
            'client_platform' => $request->header('X-Client-Platform'),
            'client_app' => $request->header('X-Client-App'),
            'client_version' => $request->header('X-App-Version'),
            'device_id' => $request->header('X-Device-Id'),
        ];
    }

    private function resolveActorName(User $user): ?string
    {
        $name = is_string($user->name ?? null) ? trim((string) $user->name) : '';

        if ($name !== '') {
            return $name;
        }

        $firstName = is_string($user->first_name ?? null) ? trim((string) $user->first_name) : '';
        $lastName = is_string($user->last_name ?? null) ? trim((string) $user->last_name) : '';
        $fullName = trim($firstName . ' ' . $lastName);

        return $fullName !== '' ? $fullName : null;
    }
}
