<?php

namespace App\Providers;

use App\Models\Agency;
use App\Models\Booking;
use App\Models\Package;
use App\Models\PackageAvailability;
use App\Policies\AgencyPolicy;
use App\Policies\BookingPolicy;
use App\Policies\PackageAvailabilityPolicy;
use App\Policies\PackagePolicy;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Gate::policy(Agency::class, AgencyPolicy::class);
        Gate::policy(Package::class, PackagePolicy::class);
        Gate::policy(PackageAvailability::class, PackageAvailabilityPolicy::class);
        Gate::policy(Booking::class, BookingPolicy::class);

        RateLimiter::for('login', fn (Request $request) => Limit::perMinute(5)->by($request->string('email')->lower().'|'.$request->ip()));
        RateLimiter::for('register', fn (Request $request) => Limit::perMinute(5)->by($request->ip()));
        RateLimiter::for('password.email', fn (Request $request) => Limit::perMinute(5)->by($request->string('email')->lower().'|'.$request->ip()));
        RateLimiter::for('password.code', fn (Request $request) => Limit::perMinute(10)->by($request->string('email')->lower().'|'.$request->ip()));
        RateLimiter::for('password.reset', fn (Request $request) => Limit::perMinute(5)->by($request->ip()));
    }
}
