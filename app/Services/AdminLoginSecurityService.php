<?php

namespace App\Services;

use App\Enums\UserRole;
use App\Mail\AdminSecurityAlertMail;
use App\Models\AdminLoginEvent;
use App\Models\TrustedAdminDevice;
use App\Models\User;
use App\Notifications\AdminSecurityAlertReceived;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Cookie;
use Throwable;

class AdminLoginSecurityService
{
    public function recordEvent(
        ?User $user,
        string $email,
        Request $request,
        string $outcome,
        ?string $failedStep = null,
    ): AdminLoginEvent {
        return AdminLoginEvent::query()->create([
            'user_id' => $user?->id,
            'email' => Str::lower($email),
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'outcome' => $outcome,
            'failed_step' => $failedStep,
            'device_hash' => $this->deviceHash($request),
        ]);
    }

    public function recordFailedPasswordAttempt(?User $user, string $email, Request $request): void
    {
        if ($user && ! $user->isAdmin()) {
            return;
        }

        $event = $this->recordEvent($user, $email, $request, 'password_failed', 'password');

        if ($user?->isAdmin() && $this->isPasswordLockedOut($user)) {
            $lockoutMinutes = (int) config('admin.password_lockout_minutes');
            $alertKey = 'admin-password-lockout-alert:'.$user->id;

            if (Cache::add($alertKey, true, $lockoutMinutes * 60)) {
                $this->trackAlert($event);
                $this->notifySecurityAdmins(collect([$user]), $event, 'failed_password_burst');
                $this->sendAlert(
                    $user,
                    $request,
                    __('messages.admin_alert_password_lockout', [
                        'attempts' => (int) config('admin.password_lockout_attempts'),
                        'minutes' => $lockoutMinutes,
                    ]),
                );
            }

            return;
        }

        $windowStart = now()->subMinutes((int) config('admin.failed_alert_window_minutes'));
        $failures = AdminLoginEvent::query()
            ->where('outcome', 'password_failed')
            ->where('created_at', '>=', $windowStart)
            ->where(function ($query) use ($email, $request): void {
                $query->where('email', Str::lower($email));
                if ($request->ip()) {
                    $query->orWhere('ip_address', $request->ip());
                }
            })
            ->count();

        if ($failures < (int) config('admin.failed_alert_threshold')) {
            return;
        }

        $alertKey = 'admin-failed-login-alert:'.hash('sha256', Str::lower($email).'|'.$request->ip());
        if (! Cache::add($alertKey, true, (int) config('admin.failed_alert_cooldown_minutes') * 60)) {
            return;
        }

        $administrators = $user?->isAdmin() ? collect([$user]) : User::query()->where('role', UserRole::ADMIN)->get();
        $this->trackAlert($event);
        $this->notifySecurityAdmins($administrators, $event, 'failed_password_burst');

        foreach ($administrators as $administrator) {
            $this->sendAlert($administrator, $request, __('messages.admin_alert_failed_burst'));
        }
    }

    public function isPasswordLockedOut(User $admin): bool
    {
        return $admin->adminLoginEvents()
            ->where('outcome', 'password_failed')
            ->where('failed_step', 'password')
            ->where('created_at', '>=', now()->subMinutes((int) config('admin.password_lockout_minutes')))
            ->count() >= (int) config('admin.password_lockout_attempts');
    }

    public function recordPasswordLockoutAttempt(User $admin, string $email, Request $request): void
    {
        $event = $this->recordEvent($admin, $email, $request, 'blocked', 'password_lockout');

        $alertKey = 'admin-password-lockout-alert:'.$admin->id;
        if (Cache::add($alertKey, true, (int) config('admin.password_lockout_minutes') * 60)) {
            $this->trackAlert($event);
            $this->notifySecurityAdmins(collect([$admin]), $event, 'failed_password_burst');
            $this->sendAlert(
                $admin,
                $request,
                __('messages.admin_alert_password_lockout', [
                    'attempts' => (int) config('admin.password_lockout_attempts'),
                    'minutes' => (int) config('admin.password_lockout_minutes'),
                ]),
            );
        }
    }

    public function recordFailedSecondFactorAttempt(
        User $admin,
        Request $request,
        string $failedStep,
    ): void {
        $event = $this->recordEvent($admin, $admin->email, $request, 'code_failed', $failedStep);
        $failureCount = AdminLoginEvent::query()
            ->where('user_id', $admin->id)
            ->where('outcome', 'code_failed')
            ->whereIn('failed_step', ['email_code', 'totp'])
            ->where('created_at', '>=', now()->subMinutes(10))
            ->count();

        if ($failureCount < 3) {
            return;
        }

        $alertKey = 'admin-second-factor-failure-alert:'.$admin->id;
        if (! Cache::add($alertKey, true, 10 * 60)) {
            return;
        }

        $this->trackAlert($event);
        $this->notifySecurityAdmins(collect([$admin]), $event, 'second_factor_failures');
        $this->sendAlert($admin, $request, __('messages.admin_alert_second_factor_failures'));
    }

    public function alertIfPasswordAcceptedFromUnknownDevice(User $admin, Request $request): void
    {
        if ($this->findTrustedDevice($admin, $request)) {
            return;
        }

        $event = $this->recordEvent($admin, $admin->email, $request, 'password_accepted', 'unknown_device_password');
        $alertKey = 'admin-unknown-device-password-alert:'.$admin->id;
        if (Cache::add($alertKey, true, 10 * 60)) {
            $this->trackAlert($event);
            $this->notifySecurityAdmins(collect([$admin]), $event, 'unknown_device_password');
        }

        $this->sendAlert($admin, $request, __('messages.admin_alert_password_accepted_unknown_device'));
    }

    public function completeSuccessfulLogin(User $admin, Request $request): Cookie
    {
        $device = $this->findTrustedDevice($admin, $request);
        $ipAddress = $request->ip();
        $userAgent = $request->userAgent();

        if (! $device) {
            $token = Str::random(64);
            $device = $admin->trustedAdminDevices()->create([
                'token_hash' => hash('sha256', $token),
                'user_agent' => $userAgent,
                'first_ip' => $ipAddress,
                'last_ip' => $ipAddress,
                'last_used_at' => now(),
            ]);
            $this->sendAlert($admin, $request, __('messages.admin_alert_login_new_device'));
        } else {
            $token = (string) $request->cookie(config('admin.trusted_device_cookie'));
            if ($device->last_ip !== $ipAddress) {
                $this->sendAlert($admin, $request, __('messages.admin_alert_login_new_ip'));
            }
            $device->update([
                'user_agent' => $userAgent,
                'last_ip' => $ipAddress,
                'last_used_at' => now(),
            ]);
        }

        return cookie(
            config('admin.trusted_device_cookie'),
            $token,
            (int) config('admin.trusted_device_days') * 24 * 60,
            '/',
            null,
            app()->isProduction(),
            true,
            false,
            'lax',
        );
    }

    public function findTrustedDevice(User $admin, Request $request): ?TrustedAdminDevice
    {
        $token = $request->cookie(config('admin.trusted_device_cookie'));
        if (! is_string($token) || $token === '') {
            return null;
        }

        return $admin->trustedAdminDevices()
            ->where('token_hash', hash('sha256', $token))
            ->first();
    }

    public function deviceHash(Request $request): string
    {
        $token = $request->cookie(config('admin.trusted_device_cookie'));
        $value = is_string($token) && $token !== ''
            ? $token
            : (string) $request->userAgent();

        return hash_hmac('sha256', $value, (string) config('app.key'));
    }

    private function sendAlert(User $admin, Request $request, string $alertMessage): void
    {
        try {
            Mail::to($admin->email)->send(new AdminSecurityAlertMail(
                $admin->name,
                $alertMessage,
                now()->toDayDateTimeString(),
                $request->ip(),
                Str::limit((string) $request->userAgent(), 160),
            ));
        } catch (Throwable $exception) {
            Log::warning('Admin security alert email could not be delivered.', [
                'admin_id' => $admin->id,
                'exception' => $exception,
            ]);
        }
    }

    /**
     * @param  Collection<int, User>  $administrators
     */
    private function notifySecurityAdmins(
        Collection $administrators,
        AdminLoginEvent $event,
        string $kind,
    ): void {
        foreach ($administrators as $administrator) {
            $administrator->notify(new AdminSecurityAlertReceived($event, $kind));
        }
    }

    private function trackAlert(AdminLoginEvent $event): void
    {
        DB::table('admin_alerts')->insertOrIgnore([
            'source_type' => 'login_burst',
            'source_id' => $event->id,
            'status' => 'open',
            'created_at' => $event->created_at,
            'updated_at' => $event->created_at,
        ]);
    }
}
