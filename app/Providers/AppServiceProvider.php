<?php

namespace App\Providers;

use App\Contracts\AdminSecondFactor;
use App\Models\Agency;
use App\Models\Booking;
use App\Models\Package;
use App\Models\PackageAvailability;
use App\Policies\AgencyPolicy;
use App\Policies\BookingPolicy;
use App\Policies\PackageAvailabilityPolicy;
use App\Policies\PackagePolicy;
use App\Services\ConfiguredAdminSecondFactor;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Middleware\TrustProxies;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->bind(AdminSecondFactor::class, ConfiguredAdminSecondFactor::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        if (app()->environment('production')) {
            URL::forceScheme('https');
        }

        Gate::policy(Agency::class, AgencyPolicy::class);
        Gate::policy(Package::class, PackagePolicy::class);
        Gate::policy(PackageAvailability::class, PackageAvailabilityPolicy::class);
        Gate::policy(Booking::class, BookingPolicy::class);

        $trustedProxies = config('admin.trusted_proxies', []);
        if ($trustedProxies !== []) {
            TrustProxies::at($trustedProxies);
        }

        RateLimiter::for('login', fn (Request $request) => Limit::perMinute(5)->by($request->string('email')->lower().'|'.$request->ip()));
        RateLimiter::for('register', fn (Request $request) => Limit::perMinute(5)->by($request->ip()));
        RateLimiter::for('password.email', fn (Request $request) => [
            Limit::perMinute(5)->by('password-email:'.mb_strtolower($request->string('email')->toString())),
            Limit::perMinute(20)->by('password-ip:'.$request->ip()),
        ]);
        RateLimiter::for('password.code', fn (Request $request) => Limit::perMinute(10)->by($request->string('email')->lower().'|'.$request->ip()));
        RateLimiter::for('password.reset', fn (Request $request) => [
            Limit::perMinute(5)->by('password-reset:'.mb_strtolower((string) $request->session()->get('password_reset_verified_email')).'|'.$request->ip()),
            Limit::perMinute(20)->by('password-reset-ip:'.$request->ip()),
        ]);
        RateLimiter::for('admin.login.verify', function (Request $request) {
            $sessionKey = (string) $request->session()->get('pending_admin_login.session_key', '');
            $identifier = $sessionKey !== '' ? hash('sha256', $sessionKey) : $request->session()->getId().'|'.$request->ip();

            return [
                Limit::perMinute(5)->by('admin-code:'.$identifier),
                Limit::perMinute(20)->by('admin-code-ip:'.$request->ip()),
            ];
        });
        RateLimiter::for('admin.login.resend', function (Request $request) {
            $sessionKey = (string) $request->session()->get('pending_admin_login.session_key', '');
            $identifier = $sessionKey !== '' ? hash('sha256', $sessionKey) : $request->session()->getId().'|'.$request->ip();

            return [
                Limit::perSecond(1, (int) config('admin.login_code_resend_seconds'))->by('admin-resend:'.$identifier),
                Limit::perMinute(5)->by('admin-resend-ip:'.$request->ip()),
            ];
        });
        RateLimiter::for('admin.users.actions', fn (Request $request) => [
            Limit::perMinute(5)->by('admin-user-action:'.$request->user()?->id.'|'.$request->ip()),
            Limit::perHour(20)->by('admin-user-action-hourly:'.$request->user()?->id),
        ]);
    }
}
