<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\HomeController;
use App\Http\Controllers\Admin\DashboardController;
use App\Http\Controllers\Admin\UserController;
use App\Http\Controllers\Admin\CommuterController;
use App\Http\Controllers\Admin\DriverController;
use App\Http\Controllers\Admin\ProfileController;
use App\Http\Controllers\Admin\LogoutController;

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

/*
|--------------------------------------------------------------------------
| AdminLTE Admin Panel Routes
|--------------------------------------------------------------------------
*/

Route::middleware(['auth'])->group(function () {
    Route::get('/home', [HomeController::class, 'index'])->name('home');

    Route::prefix('admin')->name('admin.')->group(function () {
        Route::get('/dashboard',  [DashboardController::class, 'index'])->name('dashboard');
        Route::get('/users',      [UserController::class,      'index'])->name('users.index');
        Route::get('/commuters',  [CommuterController::class,  'index'])->name('commuters.index');
        Route::get('/drivers',    [DriverController::class,    'index'])->name('drivers.index');
        Route::get('/profile',    [ProfileController::class,   'index'])->name('profile.index');
        Route::get('/logout',     [LogoutController::class,    'confirm'])->name('logout.confirm');
        Route::post('/logout',    [LogoutController::class,    'logout'])->name('logout');
    });
});
