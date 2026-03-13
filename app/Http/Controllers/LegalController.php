<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

class LegalController extends Controller
{
    public function privacyPolicy(): View
    {
        return view('legal.privacy-policy');
    }

    public function termsOfService(): View
    {
        return view('legal.terms-of-service');
    }

    public function dataDeletionInstructions(): View
    {
        return view('legal.data-deletion');
    }

    public function facebookDataDeletionCallback(Request $request): JsonResponse
    {
        $signedRequest = (string) $request->input('signed_request', '');

        if ($signedRequest === '') {
            return response()->json([
                'error' => 'Missing signed_request.',
            ], 422);
        }

        $payload = $this->parseSignedRequest($signedRequest);

        if ($payload === null) {
            return response()->json([
                'error' => 'Invalid signed_request.',
            ], 422);
        }

        $providerUserId = data_get($payload, 'user_id');

        if (
            is_string($providerUserId)
            && $providerUserId !== ''
            && Schema::hasColumn('users', 'facebook_id')
        ) {
            $user = User::where('facebook_id', $providerUserId)->first();

            if ($user) {
                $user->tokens()->delete();
                $user->delete();
            }
        }

        $confirmationCode = strtoupper(Str::random(12));

        return response()->json([
            'url' => route('legal.data-deletion.status', ['code' => $confirmationCode]),
            'confirmation_code' => $confirmationCode,
        ]);
    }

    public function dataDeletionStatus(Request $request): View
    {
        return view('legal.data-deletion-status', [
            'confirmationCode' => (string) $request->query('code', ''),
        ]);
    }

    private function parseSignedRequest(string $signedRequest): ?array
    {
        $parts = explode('.', $signedRequest, 2);

        if (count($parts) !== 2) {
            return null;
        }

        [$encodedSignature, $encodedPayload] = $parts;

        $signature = $this->base64UrlDecode($encodedSignature);
        $payloadJson = $this->base64UrlDecode($encodedPayload);

        if ($signature === null || $payloadJson === null) {
            return null;
        }

        $payload = json_decode($payloadJson, true);

        if (!is_array($payload)) {
            return null;
        }

        if (($payload['algorithm'] ?? null) !== 'HMAC-SHA256') {
            return null;
        }

        $appSecret = (string) config('services.facebook.app_secret');

        if ($appSecret === '') {
            return null;
        }

        $expectedSignature = hash_hmac('sha256', $encodedPayload, $appSecret, true);

        if (!hash_equals($expectedSignature, $signature)) {
            return null;
        }

        return $payload;
    }

    private function base64UrlDecode(string $input): ?string
    {
        $remainder = strlen($input) % 4;
        if ($remainder > 0) {
            $input .= str_repeat('=', 4 - $remainder);
        }

        $decoded = base64_decode(strtr($input, '-_', '+/'), true);

        return $decoded === false ? null : $decoded;
    }
}
