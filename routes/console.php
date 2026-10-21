<?php

use App\Services\BookingService;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::call(fn () => app(BookingService::class)->expirePendingBookings())
    ->everyMinute()
    ->name('bookings.expire-pending')
    ->withoutOverlapping();

Schedule::call(fn () => app(BookingService::class)->expirePastDepartures())
    ->dailyAt('00:05')
    ->name('availability.expire-past-departures')
    ->withoutOverlapping();

Schedule::command('admin:prune-login-events')
    ->dailyAt('01:00')
    ->name('admin.prune-login-events')
    ->withoutOverlapping();

Schedule::command('agencies:prune-unsubmitted')
    ->everyFifteenMinutes()
    ->name('agencies.prune-unsubmitted')
    ->withoutOverlapping();
