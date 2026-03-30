<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\HomeController;
use App\Http\Controllers\LegalController;
use App\Models\Role;
use App\Http\Controllers\Admin\DashboardController;
use App\Http\Controllers\Admin\UserController;
use App\Http\Controllers\Admin\CommuterController;
use App\Http\Controllers\Admin\DriverController;
use App\Http\Controllers\Admin\ProfileController;
use App\Http\Controllers\Admin\LogoutController;
use App\Http\Controllers\Admin\OrganizationController;
use App\Http\Controllers\Admin\BackupController;
use App\Http\Controllers\Admin\UserAuthorizationController;
use App\Http\Controllers\Admin\UserManagementController;
use App\Http\Controllers\Admin\Auth2faController;
use App\Http\Controllers\Admin\TransactionLogController;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
*/

Route::get('/', function () {
    if (!auth()->check()) {
        return redirect()->route('login');
    }

    $user = auth()->user();

    if ($user->hasRole(Role::SUPER_ADMIN)) {
        return redirect()->route('super-admin.dashboard');
    }

    if (
        ($user->hasRole(Role::ORGANIZATION) || $user->hasAnyActiveOrganizationManagement())
        && !$user->hasRole(Role::ADMIN)
        && !$user->hasRole(Role::SUPER_ADMIN)
    ) {
        return redirect()->route('org-manager.dashboard');
    }

    return redirect()->route('admin.dashboard');
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

Route::middleware(['auth', 'active.user'])->group(function () {
    Route::get('/home', [HomeController::class, 'index'])->name('home');

    Route::prefix('admin')->name('admin.')->group(function () {
        Route::get('/', fn () => redirect()->route('admin.dashboard'))->name('root');
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
        Route::get('/organizations/manager/dashboard', [OrganizationController::class, 'managerDashboard'])->name('organizations.manager-dashboard');
        Route::get('/organizations/manager/assignments', [OrganizationController::class, 'assignmentIndex'])->name('organizations.assignments.index');
        Route::post('/organizations/manager/terminals', [OrganizationController::class, 'storeTerminal'])->name('organizations.terminals.store');
        Route::delete('/organizations/manager/terminals/{terminal}', [OrganizationController::class, 'unassignTerminal'])->name('organizations.terminals.remove');
        Route::post('/organizations/manager/assignments/drivers/{driver}/assign', [OrganizationController::class, 'assignDriver'])->name('organizations.assignments.assign');
        Route::put('/organizations/manager/assignments/drivers/{driver}', [OrganizationController::class, 'updateDriverAssignment'])->name('organizations.assignments.update');
        Route::delete('/organizations/manager/assignments/drivers/{driver}', [OrganizationController::class, 'unassignDriver'])->name('organizations.assignments.unassign');
        Route::patch('organizations/{id}/address', [OrganizationController::class, 'updateAddress'])->name('organizations.address.update');
        Route::get('/organizations/types', [OrganizationController::class, 'organizationTypesIndex'])->name('organizations.types.index');
        Route::post('/organizations/types', [OrganizationController::class, 'organizationTypesStore'])->name('organizations.types.store');
        Route::get('/profile',         [ProfileController::class,      'index'])->name('profile.index');
        Route::get('/logout',     [LogoutController::class,    'confirm'])->name('logout.confirm');
        Route::post('/logout',    [LogoutController::class,    'logout'])->name('logout');

        Route::get('/backups', [BackupController::class, 'index'])->name('backups.index');
        Route::post('/backups', [BackupController::class, 'create'])->name('backups.create');
        Route::get('/backups/{filename}/download', [BackupController::class, 'download'])->name('backups.download');
        Route::post('/backups/{filename}/restore', [BackupController::class, 'restore'])->name('backups.restore');

        Route::get('/transactions', [TransactionLogController::class, 'index'])->name('transactions.index');

        Route::get('/user-authorization',                     [UserAuthorizationController::class, 'index'])->name('user-authorization.index');
        Route::get('/user-authorization/role/{role}/edit',    [UserAuthorizationController::class, 'editRole'])->name('user-authorization.edit-role');
        Route::put('/user-authorization/role/{role}',         [UserAuthorizationController::class, 'updateRole'])->name('user-authorization.update-role');
        Route::get('/user-authorization/user/{user}/edit',    [UserAuthorizationController::class, 'editUser'])->name('user-authorization.edit-user');
        Route::put('/user-authorization/user/{user}',         [UserAuthorizationController::class, 'updateUser'])->name('user-authorization.update-user');
        Route::get( 'user-authorization/roles/create', [UserAuthorizationController::class, 'createRole'])->name('user-authorization.create-role');
        Route::post('user-authorization/roles',         [UserAuthorizationController::class, 'storeRole'])->name('user-authorization.store-role');

        Route::get('/user-status', [UserManagementController::class, 'index'])->name('user-status.index');
        Route::patch('/user-status/users/{user}', [UserManagementController::class, 'updateUserStatus'])->name('user-status.users.update');
        Route::patch('/user-status/discounts/{discount}', [UserManagementController::class, 'updateDiscountStatus'])->name('user-status.discounts.update');
        Route::patch('/user-status/vehicles/{vehicle}', [UserManagementController::class, 'updateVehicleStatus'])->name('user-status.vehicles.update');
        Route::patch('/user-status/drivers/{driver}', [UserManagementController::class, 'updateDriverStatus'])->name('user-status.drivers.update');
        Route::get('/user-status/restore/search', [UserManagementController::class, 'searchDeletedRecords'])->name('user-status.restore.search');
        Route::post('/user-status/restore', [UserManagementController::class, 'restoreRecord'])->name('user-status.restore.record');
         Route::get('/user-status/create',           [UserManagementController::class, 'createUser'])->name('user-status.create');
        Route::post('/user-status/store',           [UserManagementController::class, 'storeUser'])->name('user-status.store');
        Route::post('/user-status/verify-email',    [UserManagementController::class, 'verifyUserEmail'])->name('user-status.verify-email');
        Route::post('/user-status/resend-otp',      [UserManagementController::class, 'resendUserVerificationOtp'])->name('user-status.resend-otp');
    });

    Route::prefix('super-admin')->name('super-admin.')->middleware('panel.role:super-admin')->group(function () {
        Route::get('/', fn () => redirect()->route('super-admin.dashboard'))->name('root');
        Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');

        Route::get('/users', [UserController::class, 'index'])->name('users.index');
        Route::get('/users/create', [UserController::class, 'create'])->name('users.create');
        Route::post('/users', [UserController::class, 'store'])->name('users.store');
        Route::get('/users/{user}/edit', [UserController::class, 'edit'])->name('users.edit');
        Route::put('/users/{user}', [UserController::class, 'update'])->name('users.update');
        Route::delete('/users/{user}', [UserController::class, 'destroy'])->name('users.destroy');
        Route::get('/commuters', [CommuterController::class, 'index'])->name('commuters.index');
        Route::get('/drivers', [DriverController::class, 'index'])->name('drivers.index');

        Route::get('/organizations', [OrganizationController::class, 'index'])->name('organizations.index');
        Route::get('/organizations/create', [OrganizationController::class, 'create'])->name('organizations.create');
        Route::post('/organizations', [OrganizationController::class, 'store'])->name('organizations.store');
        Route::get('/organizations/{id}/edit', [OrganizationController::class, 'edit'])->name('organizations.edit');
        Route::put('/organizations/{id}', [OrganizationController::class, 'update'])->name('organizations.update');
        Route::delete('/organizations/{id}', [OrganizationController::class, 'destroy'])->name('organizations.destroy');
        Route::post('/organizations/{id}/restore', [OrganizationController::class, 'restore'])->name('organizations.restore');
        Route::patch('/organizations/{id}/address', [OrganizationController::class, 'updateAddress'])->name('organizations.address.update');
        Route::get('/organizations/types', [OrganizationController::class, 'organizationTypesIndex'])->name('organizations.types.index');
        Route::post('/organizations/types', [OrganizationController::class, 'organizationTypesStore'])->name('organizations.types.store');

        Route::get('/organizations/manager/dashboard', [OrganizationController::class, 'managerDashboard'])->name('organizations.manager-dashboard');
        Route::get('/organizations/manager/assignments', [OrganizationController::class, 'assignmentIndex'])->name('organizations.assignments.index');
        Route::post('/organizations/manager/terminals', [OrganizationController::class, 'storeTerminal'])->name('organizations.terminals.store');
        Route::delete('/organizations/manager/terminals/{terminal}', [OrganizationController::class, 'unassignTerminal'])->name('organizations.terminals.remove');
        Route::post('/organizations/manager/assignments/drivers/{driver}/assign', [OrganizationController::class, 'assignDriver'])->name('organizations.assignments.assign');
        Route::put('/organizations/manager/assignments/drivers/{driver}', [OrganizationController::class, 'updateDriverAssignment'])->name('organizations.assignments.update');
        Route::delete('/organizations/manager/assignments/drivers/{driver}', [OrganizationController::class, 'unassignDriver'])->name('organizations.assignments.unassign');

        Route::get('/user-authorization', [UserAuthorizationController::class, 'index'])->name('user-authorization.index');
        Route::get('/user-authorization/role/{role}/edit', [UserAuthorizationController::class, 'editRole'])->name('user-authorization.edit-role');
        Route::put('/user-authorization/role/{role}', [UserAuthorizationController::class, 'updateRole'])->name('user-authorization.update-role');
        Route::get('/user-authorization/user/{user}/edit', [UserAuthorizationController::class, 'editUser'])->name('user-authorization.edit-user');
        Route::put('/user-authorization/user/{user}', [UserAuthorizationController::class, 'updateUser'])->name('user-authorization.update-user');
        Route::get( 'user-authorization/roles/create', [UserAuthorizationController::class, 'createRole'])->name('user-authorization.create-role');
        Route::post('user-authorization/roles',         [UserAuthorizationController::class, 'storeRole'])->name('user-authorization.store-role');

        Route::get('/user-status', [UserManagementController::class, 'index'])->name('user-status.index');
        Route::patch('/user-status/users/{user}', [UserManagementController::class, 'updateUserStatus'])->name('user-status.users.update');
        Route::patch('/user-status/discounts/{discount}', [UserManagementController::class, 'updateDiscountStatus'])->name('user-status.discounts.update');
        Route::patch('/user-status/vehicles/{vehicle}', [UserManagementController::class, 'updateVehicleStatus'])->name('user-status.vehicles.update');
        Route::patch('/user-status/drivers/{driver}', [UserManagementController::class, 'updateDriverStatus'])->name('user-status.drivers.update');
        Route::get('/user-status/restore/search', [UserManagementController::class, 'searchDeletedRecords'])->name('user-status.restore.search');
        Route::post('/user-status/restore', [UserManagementController::class, 'restoreRecord'])->name('user-status.restore.record');
         Route::get('/user-status/create',           [UserManagementController::class, 'createUser'])->name('user-status.create');
        Route::post('/user-status/store',           [UserManagementController::class, 'storeUser'])->name('user-status.store');
        Route::post('/user-status/verify-email',    [UserManagementController::class, 'verifyUserEmail'])->name('user-status.verify-email');
        Route::post('/user-status/resend-otp',      [UserManagementController::class, 'resendUserVerificationOtp'])->name('user-status.resend-otp');

        Route::get('/backups', [BackupController::class, 'index'])->name('backups.index');
        Route::post('/backups', [BackupController::class, 'create'])->name('backups.create');
        Route::get('/backups/{filename}/download', [BackupController::class, 'download'])->name('backups.download');
        Route::post('/backups/{filename}/restore', [BackupController::class, 'restore'])->name('backups.restore');

        Route::get('/transactions', [TransactionLogController::class, 'index'])->name('transactions.index');
        Route::get('/profile', [ProfileController::class, 'index'])->name('profile.index');
        Route::get('/logout', [LogoutController::class, 'confirm'])->name('logout.confirm');
        Route::post('/logout', [LogoutController::class, 'logout'])->name('logout');
    });

    Route::prefix('org-manager')->name('org-manager.')->middleware('panel.role:org-manager')->group(function () {
        Route::get('/', fn () => redirect()->route('org-manager.dashboard'))->name('root');
        Route::get('/dashboard', [OrganizationController::class, 'managerDashboard'])->name('dashboard');
        Route::get('/organizations/manager/dashboard', [OrganizationController::class, 'managerDashboard'])->name('organizations.manager-dashboard');
        Route::get('/organizations/manager/assignments', [OrganizationController::class, 'assignmentIndex'])->name('organizations.assignments.index');
        Route::post('/organizations/manager/terminals', [OrganizationController::class, 'storeTerminal'])->name('organizations.terminals.store');
        Route::delete('/organizations/manager/terminals/{terminal}', [OrganizationController::class, 'unassignTerminal'])->name('organizations.terminals.remove');
        Route::post('/organizations/manager/assignments/drivers/{driver}/assign', [OrganizationController::class, 'assignDriver'])->name('organizations.assignments.assign');
        Route::put('/organizations/manager/assignments/drivers/{driver}', [OrganizationController::class, 'updateDriverAssignment'])->name('organizations.assignments.update');
        Route::delete('/organizations/manager/assignments/drivers/{driver}', [OrganizationController::class, 'unassignDriver'])->name('organizations.assignments.unassign');
        Route::get('/profile', [ProfileController::class, 'index'])->name('profile.index');
        Route::get('/logout', [LogoutController::class, 'confirm'])->name('logout.confirm');
        Route::post('/logout', [LogoutController::class, 'logout'])->name('logout');
    });
});


Route::prefix('admin')->group(function () {
    Route::get('/login', [Auth2faController::class, 'showLogin'])->name('admin.login');
    Route::post('/login', [Auth2faController::class, 'loginStep1'])->name('admin.login.step1');

    Route::get('/2fa', [Auth2faController::class, 'show2faForm'])->name('admin.2fa.form');
    Route::post('/2fa', [Auth2faController::class, 'verify2fa'])->name('admin.2fa.verify');
});
