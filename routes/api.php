<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\DashboardController;
use App\Http\Controllers\Api\DriverController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Public Authentication Routes (No token required)
|--------------------------------------------------------------------------
*/
Route::controller(AuthController::class)->prefix('auth')->group(function (): void {
    Route::post('/register',        'register')->name('api.auth.register');
    Route::post('/login',           'login')->name('api.auth.login');
    Route::post('/verify-otp',      'verifyOtp')->name('api.auth.verify-otp');
    Route::post('/forgot-password', 'forgotPassword')->name('api.auth.forgot-password');
    Route::post('/reset-password',  'resetPassword')->name('api.auth.reset-password');
    Route::post('/resend-otp',      'resendOtp')->name('api.auth.resend-otp');
});

/*
|--------------------------------------------------------------------------
| Protected Routes (Sanctum token required)
|--------------------------------------------------------------------------
*/
Route::middleware('auth:sanctum')->group(function (): void {

    // ── Auth ─────────────────────────────────────
    Route::post('/auth/logout', [AuthController::class, 'logout'])->name('api.auth.logout');

    // ── Authenticated User Info ──────────────────
    Route::get('/user', function (Request $request) {
        return response()->json([
            'success' => true,
            'data'    => [
                'user' => [
                    'id'                => $request->user()->id,
                    'first_name'        => $request->user()->first_name,
                    'last_name'         => $request->user()->last_name,
                    'middle_name'       => $request->user()->middle_name,
                    'email'             => $request->user()->email,
                    'role'              => $request->user()->role->name,
                    'email_verified_at' => $request->user()->email_verified_at,
                ],
            ],
        ]);
    })->name('api.user');

    // ── Driver Routes ────────────────────────────
    Route::controller(DriverController::class)->prefix('drivers')->group(function (): void {
        Route::post('/create-profile',       'createProfile')->name('api.drivers.create-profile');
        Route::get('/read-profile/{id}',     'readProfile')->name('api.drivers.read-profile');
        Route::put('/update-profile/{id}',   'updateProfile')->name('api.drivers.update-profile');
        Route::delete('/delete-profile/{id}','deleteProfile')->name('api.drivers.delete-profile');
        Route::put('/restore-profile/{id}',  'restoreProfile')->name('api.drivers.restore-profile');
    });

    // ── User Profile Routes ─────────────────────────
    Route::controller(\App\Http\Controllers\Api\UserProfileController::class)->prefix('users')->group(function (): void {
        Route::post('/create-profile',        'addUserProfileCredentials')->name('api.users.create-profile');
        Route::put('/update-profile/{id}',    'updateUserProfileCredentials')->name('api.users.update-profile');
        Route::get('/read-profile/{id}',      'getUserProfileCredentials')->name('api.users.read-profile');
        Route::delete('/delete-profile/{id}', 'deleteUserProfileCredentials')->name('api.users.delete-profile');
        Route::put('/restore-profile/{id}',   'restoreUserProfileCredentials')->name('api.users.restore-profile');
    });

    //Dashboard Routes 
    Route::controller(DashboardController::class)->prefix('dashboard')->group(function (): void {
        Route::get('/admin',  'adminDashboard')->name('api.dashboard.admin');
        Route::get('/driver', 'driverDashboard')->name('api.dashboard.driver');
        Route::get('/commuter',   'commuterDashboard')->name('api.dashboard.commuter');
    });

});