<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Mail\OtpMail;
use App\Models\Otp;
use App\Models\Role;
use App\Models\User;
use App\Support\InputValidation;
use App\Support\TransactionLogbook;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Kreait\Firebase\Exception\Auth\FailedToVerifyToken;
use Kreait\Firebase\Factory;
use RuntimeException;
use Throwable;

class AuthController extends Controller
{
    /*
        POST /api/register
        Body: name, email, password, password_confirmation, role (admin|driver|commuter)
    */
    public function register(Request $request): JsonResponse
    {
        $request->merge([
            'email' => $this->normalizeEmail($request->input('email')),
        ]);

        $validated = $request->validate([
            'email' => ['required', 'string', 'email:rfc,filter', 'max:255', 'unique:users,email'],
            'password' => InputValidation::passwordRequiredRules(),
        ]);

        $user = User::create([
            'email' => $validated['email'],
            'password' => $validated['password'],
            'status' => User::STATUS_ACTIVE,
            'status_reason' => null,
            'status_changed_at' => now(),
        ]);

        // Generate and send email verification OTP
        $this->generateAndSendOtp($user, 'email_verification');

        $this->writeAuthLog(
            request: $request,
            user: $user,
            transactionType: 'register',
            status: 'success',
            metadata: [
                'auth_channel' => 'email_password',
            ]
        );

        return response()->json([
            'success' => true,
            'message' => 'Registration successful. Please check your email for the OTP to verify your account.',
            'data' => [
                'user' => [
                    'id' => $user->id,
                    'email' => $user->email,
                    'status' => $user->status,
                    'status_reason' => $user->status_reason,
                    'status_changed_at' => $user->status_changed_at,
                ],
            ],
        ], 201);

    }

    /*
        POST /api/login
        Body: email, password
    */
    public function login(Request $request): JsonResponse
    {
        $request->merge([
            'email' => $this->normalizeEmail($request->input('email')),
        ]);

        $validated = $request->validate([
            'email' => ['required', 'string', 'email:rfc,filter'],
            'password' => ['required', 'string'],
        ]);

        $user = User::where('email', $validated['email'])->first();

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

        if (! $user->isEmailVerified()) {

            $this->generateAndSendOtp($user, 'email_verification');

            return response()->json([
                'success' => false,
                'message' => 'Your email is not verified. A new verification OTP has been sent to your email.',
            ], 403);
        }

        // Check if user already logged in
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

    /*
        POST /api/auth/social/firebase
        Body: id_token, provider (google.com|facebook.com)
    */
    public function socialLoginFirebase(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'id_token' => ['required', 'string'],
            'provider' => ['nullable', 'string', 'in:google.com,facebook.com,google,facebook,Google,Facebook'],
        ]);

        try {
            $claims = $this->verifyFirebaseIdToken($validated['id_token']);
        } catch (FailedToVerifyToken) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid or expired Firebase ID token.',
            ], 401);
        } catch (RuntimeException $exception) {
            return response()->json([
                'success' => false,
                'message' => $exception->getMessage(),
            ], 500);
        } catch (Throwable) {
            return response()->json([
                'success' => false,
                'message' => 'Unable to verify social login token at this time.',
            ], 500);
        }

        $providerFromToken = (string) data_get($claims, 'firebase.sign_in_provider', '');
        $requestedProvider = $this->normalizeSocialProvider($validated['provider'] ?? null);

        if (! in_array($providerFromToken, ['google.com', 'facebook.com'], true)) {
            return response()->json([
                'success' => false,
                'message' => 'Unsupported provider in Firebase token.',
            ], 422);
        }

        if ($requestedProvider !== null && $requestedProvider !== $providerFromToken) {
            return response()->json([
                'success' => false,
                'message' => 'Provider mismatch between request and Firebase token.',
            ], 422);
        }

        $firebaseUid = (string) data_get($claims, 'sub', '');
        $email = $this->normalizeEmail(data_get($claims, 'email'));
        $displayName = (string) data_get($claims, 'name', '');
        $emailVerified = (bool) data_get($claims, 'email_verified', false);

        if ($firebaseUid === '') {
            return response()->json([
                'success' => false,
                'message' => 'Firebase token is missing user identifier.',
            ], 422);
        }

        if (! is_string($email) || $email === '') {
            return response()->json([
                'success' => false,
                'message' => 'Your social account did not provide an email address. Please use an account with email permission.',
            ], 422);
        }

        $providerColumn = $providerFromToken === 'google.com' ? 'google_id' : 'facebook_id';
        $providerLabel = $providerFromToken === 'google.com' ? 'Google' : 'Facebook';

        $providerUser = User::withTrashed()
            ->where($providerColumn, $firebaseUid)
            ->first();

        $emailUser = User::withTrashed()
            ->whereRaw('LOWER(email) = ?', [$email])
            ->first();

        // Safety guard: one social account cannot resolve to a different stored email account.
        if ($providerUser && $emailUser && $providerUser->id !== $emailUser->id) {
            return response()->json([
                'success' => false,
                'message' => 'This social account conflicts with an existing user record. Please contact support.',
            ], 409);
        }

        $user = $providerUser ?? $emailUser;

        if ($user && $user->trashed()) {
            return response()->json([
                'success' => false,
                'message' => 'This account is deactivated. Please contact support.',
            ], 403);
        }

        // Policy: existing email/password accounts cannot be used via social sign-in unless already linked.
        if ($user && empty($user->{$providerColumn})) {
            return response()->json([
                'success' => false,
                'message' => "This email is already registered. Please sign in using email and password instead of {$providerLabel}.",
            ], 409);
        }

        if ($user && $user->{$providerColumn} !== $firebaseUid) {
            return response()->json([
                'success' => false,
                'message' => "This email is linked to a different {$providerLabel} account.",
            ], 409);
        }

        $isNewUser = false;

        if (! $user) {
            $nameParts = $this->splitDisplayName($displayName);

            $socialIdData = $providerFromToken === 'google.com'
                ? ['google_id' => $firebaseUid]
                : ['facebook_id' => $firebaseUid];

            $user = User::create([
                'email' => $email,
                'first_name' => $nameParts['first_name'],
                'last_name' => $nameParts['last_name'],
                'password' => Str::random(40),
                'status' => User::STATUS_ACTIVE,
                'status_reason' => null,
                'status_changed_at' => now(),
                ...$socialIdData,
            ]);

            if ($emailVerified) {
                $user->forceFill([
                    'email_verified_at' => now(),
                ])->save();
            }
            $isNewUser = true;
        }

        $updates = [];

        if ($emailVerified && is_null($user->email_verified_at)) {
            $updates['email_verified_at'] = now();
        }

        if ($displayName !== '' && (empty($user->first_name) || empty($user->last_name))) {
            $nameParts = $this->splitDisplayName($displayName);

            if (empty($user->first_name) && $nameParts['first_name'] !== null) {
                $updates['first_name'] = $nameParts['first_name'];
            }

            if (empty($user->last_name) && $nameParts['last_name'] !== null) {
                $updates['last_name'] = $nameParts['last_name'];
            }
        }

        if ($updates !== []) {
            $user->update($updates);
            $user->refresh();
        }

        if (! $user->isAccountActive()) {
            return response()->json([
                'success' => false,
                'message' => 'Your account is not active.',
            ], 403);
        }

        $user->tokens()->delete();
        $token = $user->createToken('auth-token')->plainTextToken;

        $this->writeAuthLog(
            request: $request,
            user: $user,
            transactionType: $isNewUser ? 'register' : 'login',
            status: 'success',
            metadata: [
                'auth_channel' => 'social_firebase',
                'provider' => $providerFromToken,
                'is_new_user' => $isNewUser,
            ]
        );

        return response()->json([
            'success' => true,
            'message' => $isNewUser ? 'Social signup successful.' : 'Social login successful.',
            'data' => [
                'user' => [
                    'id' => $user->id,
                    'email' => $user->email,
                    'email_verified_at' => $user->email_verified_at,
                    'status' => $user->status,
                    'status_reason' => $user->status_reason,
                    'status_changed_at' => $user->status_changed_at,
                ],
                'token' => $token,
                'token_type' => 'Bearer',
            ],
        ], 200);
    }

    /*
        POST /api/verify-otp
        Body: email, otp, type (email_verification|login_2fa)
    */
    public function verifyOtp(Request $request): JsonResponse
    {
        $request->merge([
            'email' => $this->normalizeEmail($request->input('email')),
        ]);

        $validated = $request->validate([
            'email' => ['required', 'string', 'email:rfc,filter'],
            'otp' => ['required', 'string', 'size:6'],
            'type' => ['required', 'string', 'in:email_verification,login_2fa'],
        ]);

        $user = User::where('email', $validated['email'])->first();

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

        $otp = Otp::where('user_id', $user->id)
            ->where('code', $validated['otp'])
            ->where('type', $validated['type'])
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

        $otp->markAsUsed();

        // Handle email verification — verify email and auto-login
        if ($validated['type'] === 'email_verification') {
            $user->email_verified_at = now();
            $user->save();

            // Revoke any existing tokens
            $user->tokens()->delete();

            // Auto-login: issue Sanctum token immediately after email verification
            $token = $user->createToken('auth-token')->plainTextToken;

            $this->writeAuthLog(
                request: $request,
                user: $user,
                transactionType: 'login',
                status: 'success',
                metadata: [
                    'auth_channel' => 'email_password',
                    'via' => 'email_verification_otp',
                ]
            );

            return response()->json([
                'success' => true,
                'message' => 'Email verified successfully. You are now logged in.',
                'data' => [
                    'user' => [
                        'id' => $user->id,
                        'email' => $user->email,
                        'email_verified_at' => $user->email_verified_at,
                        'status' => $user->status,
                        'status_reason' => $user->status_reason,
                        'status_changed_at' => $user->status_changed_at,
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

            $this->writeAuthLog(
                request: $request,
                user: $user,
                transactionType: 'login',
                status: 'success',
                metadata: [
                    'auth_channel' => 'email_password',
                    'via' => 'login_2fa_otp',
                ]
            );

            return response()->json([
                'success' => true,
                'message' => 'Login successful.',
                'data' => [
                    'user' => [
                        'id' => $user->id,
                        'email' => $user->email,
                        'email_verified_at' => $user->email_verified_at,
                        'status' => $user->status,
                        'status_reason' => $user->status_reason,
                        'status_changed_at' => $user->status_changed_at,
                    ],
                    'token' => $token,
                    'token_type' => 'Bearer',
                ],
            ], 200);
        }

        return response()->json([
            'success' => false,
            'message' => 'Invalid OTP type.',
        ], 422);
    }

    /*
        POST /api/logout
        Header: Authorization: Bearer {token}
    */
    public function logout(Request $request): JsonResponse
    {
        $user = $request->user();

        if ($user) {
            $this->writeAuthLog(
                request: $request,
                user: $user,
                transactionType: 'logout',
                status: 'success',
                metadata: [
                    'auth_channel' => 'sanctum_token',
                ]
            );
        }

        // Revoke the current access token
        $user?->currentAccessToken()?->delete();

        return response()->json([
            'success' => true,
            'message' => 'Logged out successfully.',
        ], 200);
    }

    /*
        POST /api/forgot-password
        Body: email
     */
    public function forgotPassword(Request $request): JsonResponse
    {
        $request->merge([
            'email' => $this->normalizeEmail($request->input('email')),
        ]);

        $validated = $request->validate([
            'email' => ['required', 'string', 'email:rfc,filter'],
        ]);

        $user = User::where('email', $validated['email'])->first();

        if (! $user) {
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

    /*
        POST /api/reset-password
        Body: email, otp, password, password_confirmation
    */
    public function resetPassword(Request $request): JsonResponse
    {
        $request->merge([
            'email' => $this->normalizeEmail($request->input('email')),
        ]);

        $validated = $request->validate([
            'email' => ['required', 'string', 'email:rfc,filter'],
            'otp' => ['required', 'string', 'size:6'],
            'password' => InputValidation::passwordConfirmedRules(),
        ]);

        $user = User::where('email', $validated['email'])->first();

        if (! $user) {
            return response()->json([
                'success' => false,
                'message' => 'User not found.',
            ], 404);
        }

        $otp = Otp::where('user_id', $user->id)
            ->where('code', $validated['otp'])
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

    /*
        POST /api/resend-otp
        Body: email, type (email_verification|password_reset)
     */
    public function resendOtp(Request $request): JsonResponse
    {
        $request->merge([
            'email' => $this->normalizeEmail($request->input('email')),
        ]);

        $validated = $request->validate([
            'email' => ['required', 'string', 'email:rfc,filter'],
            'type' => ['required', 'string', 'in:email_verification,password_reset'],
        ]);

        $user = User::where('email', $validated['email'])->first();

        if (! $user) {
            return response()->json([
                'success' => true,
                'message' => 'If the email exists, a new OTP has been sent.',
            ], 200);
        }

        // Rate limit: check if an OTP was sent in the last 60 seconds
        $recentOtp = Otp::where('user_id', $user->id)
            ->where('type', $validated['type'])
            ->where('created_at', '>', now()->subSeconds(60))
            ->first();

        if ($recentOtp) {
            return response()->json([
                'success' => false,
                'message' => 'Please wait 60 seconds before requesting a new OTP.',
            ], 429);
        }

        // Limit: max 3 resend-otp requests per type per day
        $dailyResendCount = Otp::where('user_id', $user->id)
            ->where('type', $validated['type'])
            ->where('created_at', '>', now()->startOfDay())
            ->count();

        if ($dailyResendCount >= 3) {
            return response()->json([
                'success' => false,
                'message' => 'You have reached the maximum of 3 OTP resend requests for today. Please try again tomorrow.',
            ], 429);
        }

        $this->generateAndSendOtp($user, $validated['type']);

        return response()->json([
            'success' => true,
            'message' => 'If the email exists, a new OTP has been sent.',
        ], 200);
    }

    /*
        Generate a 6-digit OTP, save it, and send it via email.
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
            'user_id' => $user->id,
            'code' => $code,
            'type' => $type,
            'expires_at' => now()->addMinutes(10),
        ]);

        // Send OTP via email (Gmail SMTP)
        Mail::to($user->email)->send(new OtpMail(
            otpCode: $code,
            type: $type,
            recipientEmail: $user->email,
        ));
    }

    // Verify Firebase ID token and return claims
    private function verifyFirebaseIdToken(string $idToken): array
    {
        if (! class_exists(Factory::class)) {
            throw new RuntimeException('Firebase Admin SDK is not installed. Run: composer require kreait/firebase-php');
        }

        $factory = new Factory;
        $projectId = (string) config('services.firebase.project_id', '');
        $credentialsPath = (string) config('services.firebase.credentials', '');
        $credentialsJson = (string) config('services.firebase.credentials_json', '');

        if ($projectId !== '') {
            $factory = $factory->withProjectId($projectId);
        }

        if ($credentialsJson !== '') {
            $decodedCredentials = json_decode($credentialsJson, true);

            if (! is_array($decodedCredentials)) {
                throw new RuntimeException('Invalid FIREBASE_CREDENTIALS_JSON value. Expected valid JSON object.');
            }

            $factory = $factory->withServiceAccount($decodedCredentials);
        } elseif ($credentialsPath !== '') {
            $factory = $factory->withServiceAccount($credentialsPath);
        } else {
            throw new RuntimeException('Firebase Admin credentials are missing. Set FIREBASE_CREDENTIALS or FIREBASE_CREDENTIALS_JSON in your .env file.');
        }

        $auth = $factory->createAuth();
        $verifiedToken = $auth->verifyIdToken($idToken, true);

        $claims = $verifiedToken->claims()->all();
        $expiresAt = data_get($claims, 'exp');

        if (is_numeric($expiresAt) && Carbon::createFromTimestamp((int) $expiresAt)->isPast()) {
            throw new RuntimeException('Firebase ID token has expired.');
        }

        return $claims;
    }

    private function splitDisplayName(string $displayName): array
    {
        $cleanName = trim($displayName);

        if ($cleanName === '') {
            return [
                'first_name' => null,
                'last_name' => null,
            ];
        }

        $nameParts = preg_split('/\s+/', $cleanName) ?: [];
        $firstName = $nameParts[0] ?? null;
        $lastName = count($nameParts) > 1 ? implode(' ', array_slice($nameParts, 1)) : null;

        return [
            'first_name' => $firstName,
            'last_name' => $lastName,
        ];
    }

    private function normalizeSocialProvider(mixed $provider): ?string
    {
        if (! is_string($provider) || trim($provider) === '') {
            return null;
        }

        $normalized = strtolower(trim($provider));

        return match ($normalized) {
            'google', 'google.com' => 'google.com',
            'facebook', 'facebook.com' => 'facebook.com',
            default => null,
        };
    }

    private function normalizeEmail(mixed $email): ?string
    {
        if (! is_string($email)) {
            return null;
        }

        $normalized = strtolower(trim($email));

        return $normalized !== '' ? $normalized : null;
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