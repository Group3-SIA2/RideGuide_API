<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\CommuterController;
use App\Http\Controllers\Api\DashboardController;
use App\Http\Controllers\Api\DriverController;
use App\Http\Controllers\Api\EmergencyContactController;
use App\Http\Controllers\Api\OrganizationController;
use App\Http\Controllers\Api\PhoneController;
use App\Http\Controllers\Api\RouteController as TransitDataController;
use App\Http\Controllers\Api\SearchController;
use App\Http\Controllers\Api\SetUpController;
use App\Http\Controllers\Api\UserController;
use App\Http\Controllers\Api\VehicleController;
use App\Http\Controllers\Api\FareController;
use App\Http\Controllers\Api\FeedbackController;
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
    Route::post('/social/firebase', 'socialLoginFirebase')
        ->middleware('throttle:10,1')
        ->name('api.auth.social.firebase');
    Route::post('/verify-otp', 'verifyOtp')->name('api.auth.verify-otp');
    Route::post('/forgot-password', 'forgotPassword')->name('api.auth.forgot-password');
    Route::post('/reset-password', 'resetPassword')->name('api.auth.reset-password');
    Route::post('/resend-otp', 'resendOtp')->name('api.auth.resend-otp');
});

// Phone Number Authentication — iProgSMS (Philippine format: 09XXXXXXXXX | +639XXXXXXXXX)
Route::controller(PhoneController::class)->prefix('auth/phone')->group(function (): void {
    Route::post('/register', 'register')->name('api.auth.phone.register');
    Route::post('/login', 'login')->name('api.auth.phone.login');
    Route::post('/verify-otp', 'verifyOtp')->name('api.auth.phone.verify-otp');
    Route::post('/resend-otp', 'resendOtp')->name('api.auth.phone.resend-otp');
    Route::post('/forgot-password', 'forgotPassword')->name('api.auth.phone.forgot-password');
    Route::post('/reset-password', 'resetPassword')->name('api.auth.phone.reset-password');
});

/*
|--------------------------------------------------------------------------
| Protected Routes (Sanctum token required)
|--------------------------------------------------------------------------
*/
Route::middleware(['auth:sanctum', 'active.user'])->group(function (): void {

    // Auth
    Route::post('/auth/logout', [AuthController::class, 'logout'])->name('api.auth.logout');

    // Driver Routes
    Route::controller(DriverController::class)->prefix('drivers')->group(function (): void {
        Route::post('/create-profile', 'createProfile')
            ->middleware('throttle:api-upload-auth')
            ->name('api.drivers.create-profile');
        Route::get('/read-profile/{id}', 'readProfile')->name('api.drivers.read-profile');
        Route::put('/update-profile/{id}', 'updateProfile')
            ->middleware('throttle:api-upload-auth')
            ->name('api.drivers.update-profile');
        Route::delete('/delete-profile/{id}', 'deleteProfile')->name('api.drivers.delete-profile');
        Route::put('/restore-profile/{id}', 'restoreProfile')->name('api.drivers.restore-profile');
    });

    // User Routes
    Route::controller(UserController::class)->prefix('users')->group(function (): void {
        Route::get('/', 'index')->name('api.users.index');
        Route::get('/{id}', 'show')->name('api.users.show');
    });

    // Commuter Routes
    Route::controller(CommuterController::class)->prefix('commuter')->group(function (): void {
        Route::post('/add-commuter', 'addCommuter')
            ->middleware('throttle:api-upload-auth')
            ->name('api.commuter.create-profile');
        Route::put('/update-commuter/{id}', 'updateCommuterClassification')
            ->middleware('throttle:api-upload-auth')
            ->name('api.commuter.update-profile');
        Route::get('/read-commuter/{id}', 'getCommuter')->name('api.commuter.read-profile');
        Route::delete('/delete-commuter/{id}', 'deleteCommuter')->name('api.commuter.delete-profile');
        Route::put('/restore-commuter/{id}', 'restoreCommuter')->name('api.commuter.restore-profile');
    });

    // Dashboard Routes
    Route::controller(DashboardController::class)->prefix('dashboard')->group(function (): void {
        Route::get('/admin', 'adminDashboard')->name('api.dashboard.admin');
        Route::get('/driver', 'driverDashboard')->name('api.dashboard.driver');
        Route::get('/commuter', 'commuterDashboard')->name('api.dashboard.commuter');
    });

    // SetUp Routes
    Route::controller(SetUpController::class)->prefix('setup')->group(function (): void {
        Route::match(['post', 'patch'], '/setup-users', 'setUpUsers')->name('api.setup.setUpUsers');
    });

    // Search Routes
    Route::controller(SearchController::class)
        ->prefix('search')
        ->group(function (): void {
            Route::get('/drivers', 'searchDrivers')->name('api.search.drivers');
            Route::get('/commuters', 'searchCommuters')->name('api.search.commuters');
        });

    // Transit data sync routes (Flutter cache + sqlite/mysql mirror sync).
    Route::controller(TransitDataController::class)->group(function (): void {
        Route::get('/terminals', 'terminals')->name('api.transit.terminals');
        Route::get('/routes', 'routes')->name('api.transit.routes');
        Route::get('/route-stops', 'routeStops')->name('api.transit.route-stops');
        Route::get('/fares', 'fares')->name('api.transit.fares');
        Route::get('/vehicle-types', 'vehicleTypes')->name('api.transit.vehicle-types');
    });

    // Organization Routes
    Route::controller(OrganizationController::class)->prefix('organizations')->group(function (): void {
        Route::get('/', 'index')->name('api.organizations.index');
        Route::get('/assigned-drivers', 'getAssignedDrivers')->name('api.organizations.assigned-drivers');
        Route::get('/{id}', 'show')->name('api.organizations.show');
        Route::post('/', 'store')->name('api.organizations.store');
        Route::post('/create-profile', 'createProfile')->name('api.organizations.create-profile');
        Route::put('/{id}', 'update')->name('api.organizations.update');
        Route::delete('/{id}', 'destroy')->name('api.organizations.destroy');
        Route::put('/{id}/restore', 'restore')->name('api.organizations.restore');
    });

    // Emergency Contact Routes
    Route::controller(EmergencyContactController::class)->prefix('emergency-contacts')->group(function (): void {
        Route::post('/', 'addEmergencyContact')->name('api.emergency-contacts.add');
        Route::put('/{id}', 'updateEmergencyContact')->name('api.emergency-contacts.update');
        Route::get('/', 'getEmergencyContacts')->name('api.emergency-contacts.get');
        Route::delete('/{id}', 'softDeleteEmergencyContact')->name('api.emergency-contacts.delete');
    });

    // Vehicle Routes
    Route::controller(VehicleController::class)->prefix('vehicles')->group(function (): void {
        Route::post('/', 'addVehicle')
            ->middleware('throttle:api-upload-auth')
            ->name('api.vehicles.add');
        Route::get('/my-vehicles', 'listVehicledPerDriver')->name('api.vehicles.list-per-driver');
        Route::put('/update/{id}', 'updateVehicle')
            ->middleware('throttle:api-upload-auth')
            ->name('api.vehicles.update');
        Route::delete('/delete/{id}', 'deleteVehicle')->name('api.vehicles.delete');
        Route::put('/restore/{id}', 'restoreVehicle')->name('api.vehicles.restore');
    });

    // Fare Routes
    Route::controller(FareController::class)->prefix('fare')->group(function (): void {
        Route::post('/calculate', 'calculateFare')->name('api.fare.calculate');
    });

    // Feedback Routes
    Route::controller(FeedbackController::class)->prefix('feedback')->group(function (): void {
        Route::post('/', 'newFeedback')->name('api.feedback.create');
        Route::get('/trip/{tripId}', 'getAllFeedbackByTrip')->name('api.feedback.trip');
        Route::get('/{id}', 'readFeedback')->name('api.feedback.read');
        Route::put('/{id}', 'updateFeedback')->name('api.feedback.update');
        Route::delete('/{id}', 'deleteFeedback')->name('api.feedback.delete');
        Route::put('/{id}/restore', 'restoreFeedback')->name('api.feedback.restore');
    });
});
