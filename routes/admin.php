<?php

use App\Http\Controllers\Admin\AdminAgencyModerationController;
use App\Http\Controllers\Admin\AdminAlertsController;
use App\Http\Controllers\Admin\AdminBookingController;
use App\Http\Controllers\Admin\AdminBookingDisputeController;
use App\Http\Controllers\Admin\AdminCustomRequestController;
use App\Http\Controllers\Admin\AdminDashboardController;
use App\Http\Controllers\Admin\AdminLoginVerificationController;
use App\Http\Controllers\Admin\AdminNotificationController;
use App\Http\Controllers\Admin\AdminPackageModerationController;
use App\Http\Controllers\Admin\AdminReviewReportController;
use App\Http\Controllers\Admin\AdminTotpEnrollmentController;
use App\Http\Controllers\Admin\AdminTravelCatalogueController;
use App\Http\Controllers\Admin\AdminUserController;
use App\Http\Controllers\AuthController;
use App\Http\Middleware\EnsureAdminSession;
use App\Http\Middleware\EnsurePendingAdminLogin;
use Illuminate\Support\Facades\Route;

Route::get('/dashboard', AdminDashboardController::class)
    ->middleware(['auth', EnsureAdminSession::class])
    ->name('dashboard');

Route::get('/dashboard/sections/{section}', [AdminDashboardController::class, 'section'])
    ->middleware(['auth', EnsureAdminSession::class])
    ->name('dashboard.section');

Route::get('/dashboard/pages/{page}', [AdminDashboardController::class, 'page'])
    ->middleware(['auth', EnsureAdminSession::class])
    ->name('dashboard.page');

Route::get('/dashboard/agencies/{agency}', [AdminDashboardController::class, 'agency'])
    ->middleware(['auth', EnsureAdminSession::class])
    ->whereNumber('agency')
    ->name('agencies.show');

Route::get('/dashboard/packages/{package}', [AdminPackageModerationController::class, 'show'])
    ->middleware(['auth', EnsureAdminSession::class])
    ->whereNumber('package')
    ->name('packages.show');

Route::post('/dashboard/packages/{package}/moderation', [AdminPackageModerationController::class, 'moderate'])
    ->middleware(['auth', EnsureAdminSession::class])
    ->whereNumber('package')
    ->name('packages.moderation');

Route::middleware(['auth', EnsureAdminSession::class])->group(function (): void {
    Route::post('/dashboard/reviews/{review}/moderation', [AdminReviewReportController::class, 'moderateReview'])
        ->whereNumber('review')
        ->middleware('throttle:admin.users.actions')
        ->name('reviews.moderation');
    Route::post('/dashboard/reports/{report}/action', [AdminReviewReportController::class, 'actOnReport'])
        ->whereNumber('report')
        ->middleware('throttle:admin.users.actions')
        ->name('reports.action');
    Route::post('/dashboard/complaints/{issue}/action', [AdminReviewReportController::class, 'actOnComplaint'])
        ->whereNumber('issue')
        ->middleware('throttle:admin.users.actions')
        ->name('complaints.action');
});

Route::post('/dashboard/catalogue/countries', [AdminTravelCatalogueController::class, 'storeCountry'])
    ->middleware(['auth', EnsureAdminSession::class])
    ->name('catalogue.countries.store');

Route::patch('/dashboard/catalogue/countries/{country}', [AdminTravelCatalogueController::class, 'updateCountry'])
    ->middleware(['auth', EnsureAdminSession::class])
    ->whereNumber('country')
    ->name('catalogue.countries.update');

Route::post('/dashboard/catalogue/services', [AdminTravelCatalogueController::class, 'storeService'])
    ->middleware(['auth', EnsureAdminSession::class])
    ->name('catalogue.services.store');

Route::patch('/dashboard/catalogue/services/{service}', [AdminTravelCatalogueController::class, 'updateService'])
    ->middleware(['auth', EnsureAdminSession::class])
    ->whereNumber('service')
    ->name('catalogue.services.update');

Route::post('/dashboard/agencies/{agency}/verification', [AdminAgencyModerationController::class, 'verification'])
    ->middleware(['auth', EnsureAdminSession::class])
    ->whereNumber('agency')
    ->name('agencies.verification');

Route::post('/dashboard/agencies/{agency}/suspend', [AdminAgencyModerationController::class, 'suspend'])
    ->middleware(['auth', EnsureAdminSession::class])
    ->whereNumber('agency')
    ->name('agencies.suspend');

Route::post('/dashboard/agencies/{agency}/reinstate', [AdminAgencyModerationController::class, 'reinstate'])
    ->middleware(['auth', EnsureAdminSession::class])
    ->whereNumber('agency')
    ->name('agencies.reinstate');

Route::delete('/dashboard/agencies/{agency}', [AdminAgencyModerationController::class, 'destroy'])
    ->middleware(['auth', EnsureAdminSession::class])
    ->whereNumber('agency')
    ->name('agencies.destroy');

Route::get('/dashboard/agencies/{agency}/documents/{document}', [AdminAgencyModerationController::class, 'document'])
    ->middleware(['auth', EnsureAdminSession::class])
    ->whereNumber(['agency', 'document'])
    ->scopeBindings()
    ->name('agencies.documents.show');

Route::prefix('users')->name('users.')->middleware(['auth', EnsureAdminSession::class])->group(function (): void {
    Route::get('/', [AdminUserController::class, 'index'])->name('index');
    Route::get('/travelers', [AdminUserController::class, 'travelers'])->name('travelers');
    Route::get('/agency-owners', [AdminUserController::class, 'agencyOwners'])->name('agency-owners');
    Route::get('/administrators', [AdminUserController::class, 'administrators'])->name('administrators');
    Route::get('/suspended', [AdminUserController::class, 'suspended'])->name('suspended');
    Route::post('/bulk', [AdminUserController::class, 'bulk'])
        ->middleware('throttle:admin.users.actions')
        ->name('bulk');
    Route::post('/{userId}/deactivate', [AdminUserController::class, 'deactivate'])
        ->whereNumber('userId')
        ->middleware('throttle:admin.users.actions')
        ->name('deactivate');
    Route::post('/{userId}/revoke-sessions', [AdminUserController::class, 'revokeSessions'])
        ->whereNumber('userId')
        ->middleware('throttle:admin.users.actions')
        ->name('revoke-sessions');
    Route::post('/{userId}/restore', [AdminUserController::class, 'restore'])
        ->whereNumber('userId')
        ->middleware('throttle:admin.users.actions')
        ->name('restore');
    Route::post('/{userId}/notes', [AdminUserController::class, 'note'])
        ->whereNumber('userId')
        ->middleware('throttle:admin.users.actions')
        ->name('notes.store');
    Route::post('/{userId}/password-reset', [AdminUserController::class, 'passwordReset'])
        ->whereNumber('userId')
        ->middleware('throttle:admin.users.actions')
        ->name('password-reset');
    Route::delete('/{userId}', [AdminUserController::class, 'destroy'])
        ->whereNumber('userId')
        ->middleware('throttle:admin.users.actions')
        ->name('destroy');
    Route::get('/{userId}', [AdminUserController::class, 'show'])->whereNumber('userId')->name('show');
});

Route::prefix('bookings')->name('bookings.')->middleware(['auth', EnsureAdminSession::class])->group(function (): void {
    Route::get('/', [AdminBookingController::class, 'index'])->name('index');
    Route::get('/pending', [AdminBookingController::class, 'pending'])->name('pending');
    Route::get('/confirmed', [AdminBookingController::class, 'confirmed'])->name('confirmed');
    Route::get('/completed', [AdminBookingController::class, 'completed'])->name('completed');
    Route::get('/cancelled-rejected', [AdminBookingController::class, 'cancelledRejected'])->name('cancelled-rejected');
    Route::get('/expired', [AdminBookingController::class, 'expired'])->name('expired');
    Route::get('/export', [AdminBookingController::class, 'export'])->name('export');
    Route::get('/{booking}', [AdminBookingController::class, 'show'])->whereNumber('booking')->name('show');
    Route::patch('/{booking}/status', [AdminBookingController::class, 'transition'])->whereNumber('booking')->name('status');
    Route::post('/{booking}/notes', [AdminBookingController::class, 'note'])->whereNumber('booking')->name('notes.store');
    Route::post('/{booking}/disputes', [AdminBookingController::class, 'openDispute'])->whereNumber('booking')->name('disputes.store');
});

Route::prefix('custom-requests')->name('custom-requests.')->middleware(['auth', EnsureAdminSession::class])->group(function (): void {
    Route::get('/', [AdminCustomRequestController::class, 'index'])->name('index');
    Route::get('/export', [AdminCustomRequestController::class, 'export'])->name('export');
    Route::get('/{travelRequest}', [AdminCustomRequestController::class, 'show'])->whereNumber('travelRequest')->name('show');
    Route::post('/{travelRequest}/moderate/{action}', [AdminCustomRequestController::class, 'moderate'])
        ->whereNumber('travelRequest')->whereIn('action', ['hold', 'restore', 'reject', 'close', 'reopen'])->name('moderate');
    Route::post('/{travelRequest}/notes', [AdminCustomRequestController::class, 'note'])->whereNumber('travelRequest')->name('notes.store');
});

Route::prefix('booking-disputes')->name('booking-disputes.')->middleware(['auth', EnsureAdminSession::class])->group(function (): void {
    Route::get('/', [AdminBookingDisputeController::class, 'index'])->name('index');
    Route::get('/{issue}', [AdminBookingDisputeController::class, 'show'])->whereNumber('issue')->name('show');
    Route::patch('/{issue}/assignment', [AdminBookingDisputeController::class, 'assign'])->whereNumber('issue')->name('assignment');
    Route::patch('/{issue}/status', [AdminBookingDisputeController::class, 'transition'])->whereNumber('issue')->name('status');
    Route::post('/{issue}/notes', [AdminBookingDisputeController::class, 'note'])->whereNumber('issue')->name('notes.store');
});

Route::get('/notifications/{notification}', [AdminNotificationController::class, 'open'])
    ->middleware(['auth', EnsureAdminSession::class])
    ->name('notifications.open');

Route::patch('/notifications/read-all', [AdminNotificationController::class, 'markAllRead'])
    ->middleware(['auth', EnsureAdminSession::class])
    ->name('notifications.read-all');

Route::post('/dashboard/alerts/{source}/{sourceId}/acknowledge', [AdminAlertsController::class, 'acknowledge'])
    ->whereNumber('sourceId')
    ->middleware(['auth', EnsureAdminSession::class])
    ->name('alerts.acknowledge');

Route::post('/dashboard/alerts/{source}/{sourceId}/resolve', [AdminAlertsController::class, 'resolve'])
    ->whereNumber('sourceId')
    ->middleware(['auth', EnsureAdminSession::class])
    ->name('alerts.resolve');

Route::post('/logout', [AuthController::class, 'logout'])
    ->middleware(['auth', EnsureAdminSession::class])
    ->name('logout');

Route::middleware(['auth', EnsureAdminSession::class])->prefix('security/totp')->name('totp.')->group(function (): void {
    Route::get('/setup', [AdminTotpEnrollmentController::class, 'show'])->name('setup');
    Route::post('/setup/confirm', [AdminTotpEnrollmentController::class, 'confirm'])
        ->middleware('throttle:admin.login.verify')
        ->name('confirm');
    Route::post('/setup/skip', [AdminTotpEnrollmentController::class, 'skip'])->name('skip');
});

Route::middleware(EnsurePendingAdminLogin::class)->group(function (): void {
    Route::get('/login/verify', [AdminLoginVerificationController::class, 'show'])->name('login.verify');
    Route::post('/login/verify', [AdminLoginVerificationController::class, 'verify'])
        ->middleware('throttle:admin.login.verify')
        ->name('login.verify.submit');
    Route::post('/login/resend', [AdminLoginVerificationController::class, 'resend'])
        ->middleware('throttle:admin.login.resend')
        ->name('login.resend');
});
