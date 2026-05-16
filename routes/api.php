<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\AvailableCommutersController;
use App\Http\Controllers\Api\CommuterController;
use App\Http\Controllers\Api\DashboardController;
use App\Http\Controllers\Api\DriverController;
use App\Http\Controllers\Api\DriverLocationController;
use App\Http\Controllers\Api\EmergencyContactController;
use App\Http\Controllers\Api\OrganizationController;
use App\Http\Controllers\Api\OrganizationOperationsController;
use App\Http\Controllers\Api\PhoneController;
use App\Http\Controllers\Api\RideRequestController;
use App\Http\Controllers\Api\SearchController;
use App\Http\Controllers\Api\SetUpController;
use App\Http\Controllers\Api\TransactionHistoryController;
use App\Http\Controllers\Api\UserController;
use App\Http\Controllers\Api\VehicleController;
use App\Http\Controllers\Api\FareController;
use App\Http\Controllers\Api\FeedbackController;
use App\Http\Controllers\Api\LocationController;
use App\Http\Controllers\Api\MapExperienceController;
use App\Http\Controllers\Api\UserLiveLocationController;
use App\Http\Controllers\Api\CommuterTripController;
use App\Http\Controllers\Api\InquiryController;
use App\Http\Controllers\Api\TripController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

/** @var list<string> */
$sanctumStack = ['auth:sanctum', 'resolve.active.role', 'active.user'];

/*
|--------------------------------------------------------------------------
| Public Endpoints (Location Data - No Authentication Required)
|--------------------------------------------------------------------------
*/
Route::controller(LocationController::class)->prefix('locations')->group(function (): void {
    Route::get('/terminals', 'getTerminals')->name('api.locations.terminals');
    Route::get('/routes', 'getRoutes')->name('api.locations.routes');
    Route::get('/barangays', 'getBarangays')->name('api.locations.barangays');
    Route::get('/provinces', 'getProvinces')->name('api.locations.provinces');
});

Route::controller(LocationController::class)->prefix('map')->group(function (): void {
    Route::get('/available-filters', 'getAvailableFilters')->middleware(['auth:sanctum', 'resolve.active.role'])->name('api.map.available-filters');
});

/*
|--------------------------------------------------------------------------
| Public Authentication Routes (No token required)
|--------------------------------------------------------------------------
*/
Route::controller(AuthController::class)->prefix('auth')->group(function (): void {
    Route::post('/register', 'register')->name('api.auth.register');
    Route::post('/login', 'login')->name('api.auth.login');
    Route::post('/social/firebase', 'socialLoginFirebase')->middleware('throttle:10,1')->name('api.auth.social.firebase');
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
| Driver-Only Endpoints (Auth + Driver Role Required)
|--------------------------------------------------------------------------
*/
Route::middleware([...$sanctumStack, 'active.role.required', 'role:driver', 'active.role.match:driver'])->group(function (): void {
    Route::controller(AvailableCommutersController::class)->prefix('available-commuters')->group(function (): void {
        Route::get('/', 'getAvailableCommuters')->name('api.available-commuters.get');
        Route::post('/respond', 'respondToCommuter')->name('api.available-commuters.respond');
    });

    Route::controller(DriverLocationController::class)->prefix('drivers/location')->group(function (): void {
        Route::post('/', 'updateLocation')->middleware('throttle:live-location-post')->name('api.driver-location.update');
        Route::get('/', 'getLocation')->name('api.driver-location.get');
        Route::delete('/', 'clearLocation')->name('api.driver-location.clear');
    });

    Route::controller(InquiryController::class)->prefix('inquiry/driver')->group(function (): void {
        Route::get('/', 'driverList')->name('api.inquiry.driver.list');
        Route::put('/{id}', 'driverRespond')->name('api.inquiry.driver.respond');
    });

    Route::controller(TripController::class)->prefix('trips')->group(function (): void {
        Route::post('/', 'startTrip')->name('api.trips.start');
        Route::patch('/{id}/end', 'endTrip')->name('api.trips.end');
        Route::post('/{id}/passengers', 'addPassenger')->name('api.trips.passengers.add');
        Route::delete('/{id}/passengers/{passengerId}', 'removePassenger')->name('api.trips.passengers.remove');
        Route::post('/{id}/waypoints', 'addWaypoint')->name('api.trips.waypoints.add');
        Route::delete('/{id}/waypoints/{waypointId}', 'removeWaypoint')->name('api.trips.waypoints.remove');
        Route::get('/', 'listTrips')->name('api.trips.list');
        Route::get('/{id}', 'showTrip')->name('api.trips.show');
    });
});

/*
|--------------------------------------------------------------------------
| Commuter Endpoints (Auth Required)
|--------------------------------------------------------------------------
*/
Route::middleware([...$sanctumStack, 'active.role.required', 'role:commuter', 'active.role.match:commuter'])->group(function (): void {
    Route::controller(RideRequestController::class)->prefix('commuter/ride-requests')->group(function (): void {
        Route::post('/', 'createRideRequest')->name('api.commuter.ride-requests.create');
        Route::get('/', 'listRideRequests')->name('api.commuter.ride-requests.list');
        Route::put('/{id}', 'updateRideRequestResponse')->name('api.commuter.ride-requests.update');
    });

    Route::controller(InquiryController::class)->prefix('inquiry/commuter')->group(function (): void {
        Route::get('/', 'commuterList')->name('api.inquiry.commuter.list');
        Route::put('/{id}', 'commuterRespond')->name('api.inquiry.commuter.respond');
    });

    Route::controller(CommuterTripController::class)->prefix('commuter/trips')->group(function (): void {
        Route::get('/', 'listMyTrips')->name('api.commuter.trips.list');
        Route::get('/current', 'getCurrentTrip')->name('api.commuter.trips.current');
        Route::get('/nearby', 'findNearbyTrips')->name('api.commuter.trips.nearby');
        Route::get('/{id}', 'showTrip')->name('api.commuter.trips.show');
    });
});

/*
|--------------------------------------------------------------------------
| Organization Role Endpoints
|--------------------------------------------------------------------------
*/
Route::middleware([...$sanctumStack, 'active.role.required', 'role:organization', 'active.role.match:organization'])->group(function (): void {
    Route::controller(OrganizationOperationsController::class)->prefix('organization')->group(function (): void {
        Route::get('/terminals', 'listTerminals')->name('api.organization.terminals.list');
        Route::post('/terminals', 'createTerminal')->name('api.organization.terminals.create');
        Route::put('/terminals/{terminalId}', 'updateTerminal')->name('api.organization.terminals.update');
        Route::delete('/terminals/{terminalId}', 'deleteTerminal')->name('api.organization.terminals.delete');
        Route::get('/assign/drivers', 'listAssignableDrivers')->name('api.organization.assign.drivers.list');
        Route::post('/assign/drivers', 'assignDriver')->name('api.organization.assign.drivers.assign');
        Route::delete('/assign/drivers/{driverId}', 'unassignDriver')->name('api.organization.assign.drivers.unassign');
    });
});

/*
|--------------------------------------------------------------------------
| Protected Routes (Sanctum token required)
|--------------------------------------------------------------------------
*/
Route::middleware($sanctumStack)->group(function (): void {

    // Auth
    Route::post('/auth/logout', [AuthController::class, 'logout'])->name('api.auth.logout');
    Route::post('/auth/select-role', [AuthController::class, 'selectRole'])->name('api.auth.select-role');

    // Driver Routes
    Route::controller(DriverController::class)->prefix('drivers')->group(function (): void {
        Route::post('/create-profile', 'createProfile')->middleware('throttle:api-upload-auth')->name('api.drivers.create-profile');
        Route::get('/read-profile/{id}', 'readProfile')->name('api.drivers.read-profile');
        Route::put('/update-profile/{id}', 'updateProfile')->middleware('throttle:api-upload-auth')->name('api.drivers.update-profile');
        Route::delete('/delete-profile/{id}', 'deleteProfile')->name('api.drivers.delete-profile');
        Route::put('/restore-profile/{id}', 'restoreProfile')->name('api.drivers.restore-profile');
    });

    // User Routes
    Route::controller(UserController::class)->prefix('users')->group(function (): void {
        Route::get('/', 'index')->name('api.users.index');
        Route::get('/{id}', 'show')->name('api.users.show');
        Route::patch('/me/active-role', 'updateActiveRole')->name('api.users.me.active-role');
    });

    // Commuter Routes
    Route::controller(CommuterController::class)->prefix('commuter')->group(function (): void {
        Route::post('/add-commuter', 'addCommuter')->middleware('throttle:api-upload-auth')->name('api.commuter.create-profile');
        Route::put('/update-commuter/{id}', 'updateCommuterClassification')->middleware('throttle:api-upload-auth')->name('api.commuter.update-profile');
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
    Route::controller(SearchController::class)->prefix('search')->group(function (): void {
            Route::get('/drivers', 'searchDrivers')->name('api.search.drivers');
            Route::get('/commuters', 'searchCommuters')->name('api.search.commuters');
        });

    // Organization Routes
    Route::get('/organization-types', [OrganizationController::class, 'organizationTypes'])->name('api.organization-types.index');

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
        Route::post('/', 'addVehicle')->middleware('throttle:api-upload-auth')->name('api.vehicles.add');
        Route::get('/my-vehicles', 'listVehicledPerDriver')->name('api.vehicles.list-per-driver');
        Route::put('/update/{id}', 'updateVehicle')->middleware('throttle:api-upload-auth')->name('api.vehicles.update');
        Route::delete('/delete/{id}', 'deleteVehicle')->name('api.vehicles.delete');
        Route::put('/restore/{id}', 'restoreVehicle')->name('api.vehicles.restore');
    });

    // Fare Routes
    Route::controller(FareController::class)->prefix('fare')->group(function (): void {
        Route::post('/calculate', 'calculateFare')->name('api.fare.calculate');
    });

    // Transaction history (map/inquiry/history offline sync source)
    Route::controller(TransactionHistoryController::class)->prefix('transactions')->middleware(['active.role.required'])->group(function (): void {
        Route::get('/history', 'index')->name('api.transactions.history');
    });

    // Role-scoped map shell behavior + overlays.
    Route::controller(MapExperienceController::class)->prefix('map')->middleware(['active.role.required'])->group(function (): void {
        Route::get('/experience', 'experience')->name('api.map.experience');
        Route::get('/overlays', 'overlays')->name('api.map.overlays');
    });

    Route::controller(UserLiveLocationController::class)->prefix('map')->middleware(['active.role.required'])->group(function (): void {
        Route::post('/live-location', 'store')->middleware('throttle:live-location-post')->name('api.map.live-location.store');
        Route::delete('/live-location', 'destroy')->name('api.map.live-location.destroy');
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