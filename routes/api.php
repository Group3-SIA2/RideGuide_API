<?php

use App\Http\Controllers\Api\AuthController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Public Authentication Routes (No token required)
|--------------------------------------------------------------------------
*/
Route::controller(AuthController::class)->prefix('auth')->group(function (): void {
    Route::post('/register', 'register')->name('api.auth.register');
    Route::post('/login', 'login')->name('api.auth.login');
    Route::post('/verify-otp', 'verifyOtp')->name('api.auth.verify-otp');
    Route::post('/forgot-password', 'forgotPassword')->name('api.auth.forgot-password');
    Route::post('/reset-password', 'resetPassword')->name('api.auth.reset-password');
    Route::post('/resend-otp', 'resendOtp')->name('api.auth.resend-otp');
});

/*
|--------------------------------------------------------------------------
| Protected Routes (Sanctum token required)
|--------------------------------------------------------------------------
*/
Route::middleware('auth:sanctum')->group(function (): void {
    // Auth
    Route::post('/auth/logout', [AuthController::class, 'logout'])->name('api.auth.logout');

    // Authenticated user info
    Route::get('/user', function (Request $request) {
        return response()->json([
            'success' => true,
            'data'    => [
                'user' => [
                    'id'                => $request->user()->id,
                    'name'              => $request->user()->name,
                    'email'             => $request->user()->email,
                    'role'              => $request->user()->role->name,
                    'email_verified_at' => $request->user()->email_verified_at,
                ],
            ],
        ]);
    })->name('api.user');
});