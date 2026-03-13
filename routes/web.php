<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\HomeController;
use App\Http\Controllers\LegalController;
use App\Http\Controllers\Admin\DashboardController;
use App\Http\Controllers\Admin\UserController;
use App\Http\Controllers\Admin\CommuterController;
use App\Http\Controllers\Admin\DriverController;
use App\Http\Controllers\Admin\ProfileController;
use App\Http\Controllers\Admin\LogoutController;
use App\Http\Controllers\Admin\OrganizationController;
use App\Http\Controllers\Admin\BackupController;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
*/

Route::get('/', function () {
    return auth()->check()
        ? redirect()->route('admin.dashboard')
        : redirect()->route('login');
});

Auth::routes();

Route::get('/legal/privacy-policy', [LegalController::class, 'privacyPolicy'])
    ->name('legal.privacy-policy');
Route::get('/legal/terms-of-service', [LegalController::class, 'termsOfService'])
    ->name('legal.terms-of-service');
Route::get('/legal/data-deletion', [LegalController::class, 'dataDeletionInstructions'])
    ->name('legal.data-deletion');
Route::post('/facebook/data-deletion/callback', [LegalController::class, 'facebookDataDeletionCallback'])
    ->name('legal.facebook.data-deletion.callback');
Route::get('/facebook/data-deletion/status', [LegalController::class, 'dataDeletionStatus'])
    ->name('legal.data-deletion.status');

/*
|--------------------------------------------------------------------------
| AdminLTE Admin Panel Routes
|--------------------------------------------------------------------------
*/

Route::middleware(['auth'])->group(function () {
    Route::get('/home', [HomeController::class, 'index'])->name('home');

    Route::prefix('admin')->name('admin.')->group(function () {
        Route::get('/dashboard',  [DashboardController::class, 'index'])->name('dashboard');

        // Existing + New User Routes
        Route::get('/users',              [UserController::class, 'index'])->name('users.index');
        Route::get('/users/create',       [UserController::class, 'create'])->name('users.create');
        Route::post('/users',             [UserController::class, 'store'])->name('users.store');
        Route::get('/users/{user}/edit',  [UserController::class, 'edit'])->name('users.edit');
        Route::put('/users/{user}',       [UserController::class, 'update'])->name('users.update');
        Route::delete('/users/{user}',    [UserController::class, 'destroy'])->name('users.destroy');

        Route::get('/commuters',  [CommuterController::class,  'index'])->name('commuters.index');
        Route::get('/drivers',         [DriverController::class,       'index'])->name('drivers.index');
        Route::get('/organizations',              [OrganizationController::class, 'index'])->name('organizations.index');
        Route::get('/organizations/create',         [OrganizationController::class, 'create'])->name('organizations.create');
        Route::post('/organizations',               [OrganizationController::class, 'store'])->name('organizations.store');
        Route::get('/organizations/{id}/edit',      [OrganizationController::class, 'edit'])->name('organizations.edit');
        Route::put('/organizations/{id}',           [OrganizationController::class, 'update'])->name('organizations.update');
        Route::delete('/organizations/{id}',        [OrganizationController::class, 'destroy'])->name('organizations.destroy');
        Route::post('/organizations/{id}/restore',  [OrganizationController::class, 'restore'])->name('organizations.restore');
        Route::get('/profile',         [ProfileController::class,      'index'])->name('profile.index');
        Route::get('/logout',     [LogoutController::class,    'confirm'])->name('logout.confirm');
        Route::post('/logout',    [LogoutController::class,    'logout'])->name('logout');

        Route::get('/backups', [BackupController::class, 'index'])->name('backups.index');
        Route::post('/backups', [BackupController::class, 'create'])->name('backups.create');
        Route::get('/backups/{filename}/download', [BackupController::class, 'download'])->name('backups.download');
        Route::post('/backups/{filename}/restore', [BackupController::class, 'restore'])->name('backups.restore');
    });
});
