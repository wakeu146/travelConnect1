<?php

use App\Http\Controllers\Admin\AdminAlertsController;
use App\Http\Controllers\Admin\AdminDashboardController;
use App\Http\Controllers\Admin\AdminLoginVerificationController;
use App\Http\Controllers\Admin\AdminTotpEnrollmentController;
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
