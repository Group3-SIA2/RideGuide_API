<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\DashboardController;
use App\Http\Controllers\Api\DriverController;
use App\Http\Controllers\Api\UserController;
use App\Http\Controllers\Api\CommuterController;
use App\Http\Controllers\Api\SetUpController;
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

    // Auth 
    Route::post('/auth/logout', [AuthController::class, 'logout'])->name('api.auth.logout');

    // Driver Routes
    Route::controller(DriverController::class)->prefix('drivers')->group(function (): void {
        Route::post('/create-profile',       'createProfile')->name('api.drivers.create-profile');
        Route::get('/read-profile/{id}',     'readProfile')->name('api.drivers.read-profile');
        Route::put('/update-profile/{id}',   'updateProfile')->name('api.drivers.update-profile');
        Route::delete('/delete-profile/{id}','deleteProfile')->name('api.drivers.delete-profile');
        Route::put('/restore-profile/{id}',  'restoreProfile')->name('api.drivers.restore-profile');
    });

    // User Routes 
    Route::controller(UserController::class)->prefix('users')->group(function (): void {
        Route::get('/',       'index')->name('api.users.index');
        Route::get('/{id}',   'show')->name('api.users.show');
    });

    // Commuter Routes
    Route::controller(CommuterController::class)->prefix('commuter')->group(function (): void {
        Route::post('/add-commuter',        'addCommuter')->name('api.commuter.create-profile');
        Route::put('/update-commuter/{id}',    'updateCommuterClassification')->name('api.commuter.update-profile');
        Route::get('/read-commuter/{id}',      'getCommuter')->name('api.commuter.read-profile');
        Route::delete('/delete-commuter/{id}', 'deleteCommuter')->name('api.commuter.delete-profile');
        Route::put('/restore-commuter/{id}',   'restoreCommuter')->name('api.commuter.restore-profile');
    });

    //Dashboard Routes 
    Route::controller(DashboardController::class)->prefix('dashboard')->group(function (): void {
        Route::get('/admin',  'adminDashboard')->name('api.dashboard.admin');
        Route::get('/driver', 'driverDashboard')->name('api.dashboard.driver');
        Route::get('/commuter',   'commuterDashboard')->name('api.dashboard.commuter');
    });

    //SetUp Routes
    Route::controller(SetUpController::class)->prefix('setup')->group(function (): void {
        Route::post('/setup-users', 'setUpUsers')->name('api.setup.setUpUsers');
    });

});