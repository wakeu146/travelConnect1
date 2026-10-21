<?php

namespace App\Services;

use App\Contracts\AdminSecondFactor;
use App\Models\AdminLoginChallenge;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Cookie;

class AdminAuthenticationService
{
    private const PENDING_USER_ID = 'pending_admin_login.user_id';

    private const PENDING_STARTED_AT = 'pending_admin_login.started_at';

    private const PENDING_SESSION_KEY = 'pending_admin_login.session_key';

    private const PENDING_SESSION_VERSION = 'pending_admin_login.session_version';

    private const VERIFIED_USER_ID = 'admin_login.verified_user_id';

    private const VERIFIED_SESSION_VERSION = 'admin_login.session_version';

    private const VERIFIED_AT = 'admin_login.verified_at';

    public function __construct(
        private readonly AdminSecondFactor $secondFactor,
        private readonly AdminLoginSecurityService $security,
    ) {}

    public function begin(User $admin, Request $request): void
    {
        abort_unless($admin->isAdmin(), 403);

        Auth::guard('web')->logout();
        $admin->forceFill(['remember_token' => Str::random(60)])->save();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        $sessionKey = Str::random(64);
        $request->session()->put([
            self::PENDING_USER_ID => $admin->id,
            self::PENDING_STARTED_AT => now()->timestamp,
            self::PENDING_SESSION_KEY => $sessionKey,
            self::PENDING_SESSION_VERSION => $admin->admin_session_version,
        ]);

        try {
            $this->security->alertIfPasswordAcceptedFromUnknownDevice($admin, $request);
            $usesAuthenticator = $this->secondFactor->usesAuthenticator($admin);
            $this->secondFactor->issue($admin, $sessionKey);
            $this->security->recordEvent(
                $admin,
                $admin->email,
                $request,
                $usesAuthenticator ? 'factor_required' : 'code_sent',
                $usesAuthenticator ? 'totp' : 'email_code',
            );
        } catch (\Throwable $exception) {
            AdminLoginChallenge::query()
                ->where('session_hash', hash('sha256', $sessionKey))
                ->update(['invalidated_at' => now()]);
            $request->session()->forget($this->pendingKeys());
            $this->security->recordEvent($admin, $admin->email, $request, 'blocked', 'email_delivery');

            throw $exception;
        }
    }

    public function pendingAdmin(Request $request): ?User
    {
        $userId = $request->session()->get(self::PENDING_USER_ID);
        $startedAt = $request->session()->get(self::PENDING_STARTED_AT);
        $sessionVersion = $request->session()->get(self::PENDING_SESSION_VERSION);

        if (! is_int($userId) || ! is_int($startedAt)
            || now()->timestamp - $startedAt > (int) config('admin.idle_timeout_minutes') * 60) {
            $this->cancelPending($request);

            return null;
        }

        $admin = User::query()->find($userId);
        if (! $admin?->isAdmin() || $admin->admin_session_version !== $sessionVersion) {
            $this->cancelPending($request);

            return null;
        }

        return $admin;
    }

    public function resendCode(Request $request): bool
    {
        $admin = $this->pendingAdmin($request);
        $sessionKey = $request->session()->get(self::PENDING_SESSION_KEY);

        if (! $admin || ! is_string($sessionKey) || $sessionKey === '') {
            return false;
        }

        if ($this->secondFactor->usesAuthenticator($admin)) {
            return false;
        }

        $challenge = AdminLoginChallenge::query()
            ->where('user_id', $admin->id)
            ->where('session_hash', hash('sha256', $sessionKey))
            ->first();
        if (! $challenge || $challenge->consumed_at || $challenge->invalidated_at
            || $challenge->attempts >= (int) config('admin.login_code_max_attempts')) {
            return false;
        }

        $this->secondFactor->issue($admin, $sessionKey);
        $this->security->recordEvent($admin, $admin->email, $request, 'code_sent', 'second_factor_resend');

        return true;
    }

    public function verifyCode(Request $request, string $proof): ?Cookie
    {
        $admin = $this->pendingAdmin($request);
        $sessionKey = $request->session()->get(self::PENDING_SESSION_KEY);

        if (! $admin || ! is_string($sessionKey) || $sessionKey === '') {
            return null;
        }

        $sessionHash = hash('sha256', $sessionKey);
        $verified = DB::transaction(function () use ($admin, $proof, $sessionHash): bool {
            $challenge = AdminLoginChallenge::query()
                ->where('session_hash', $sessionHash)
                ->where('user_id', $admin->id)
                ->lockForUpdate()
                ->first();

            if (! $challenge
                || $challenge->consumed_at
                || $challenge->invalidated_at
                || $challenge->expires_at->isPast()
                || $challenge->attempts >= (int) config('admin.login_code_max_attempts')) {
                return false;
            }

            if (! $this->secondFactor->verify($admin, $challenge, $proof)) {
                $attempts = $challenge->attempts + 1;
                $challenge->update([
                    'attempts' => $attempts,
                    'invalidated_at' => $attempts >= (int) config('admin.login_code_max_attempts') ? now() : null,
                ]);

                return false;
            }

            $challenge->update(['consumed_at' => now()]);

            return true;
        });

        if (! $verified) {
            $failedStep = $this->secondFactor->usesAuthenticator($admin) ? 'totp' : 'email_code';
            $this->security->recordFailedSecondFactorAttempt($admin, $request, $failedStep);

            return null;
        }

        Auth::guard('web')->login($admin, false);
        $request->session()->regenerate();
        $request->session()->forget($this->pendingKeys());
        $request->session()->put([
            self::VERIFIED_USER_ID => $admin->id,
            self::VERIFIED_SESSION_VERSION => $admin->admin_session_version,
            self::VERIFIED_AT => now()->timestamp,
            'admin_login.last_activity' => now()->timestamp,
        ]);

        $this->security->recordEvent($admin, $admin->email, $request, 'success', 'complete');

        return $this->security->completeSuccessfulLogin($admin, $request);
    }

    public function isVerifiedSession(User $admin, Request $request): bool
    {
        return $request->session()->get(self::VERIFIED_USER_ID) === $admin->id
            && $request->session()->get(self::VERIFIED_SESSION_VERSION) === $admin->admin_session_version
            && is_int($request->session()->get(self::VERIFIED_AT));
    }

    public function pendingFactorUsesAuthenticator(Request $request): bool
    {
        $admin = $this->pendingAdmin($request);

        return $admin !== null && $this->secondFactor->usesAuthenticator($admin);
    }

    public function adminUsesAuthenticator(User $admin): bool
    {
        return $this->secondFactor->usesAuthenticator($admin);
    }

    public function touchAdminSession(Request $request): void
    {
        $request->session()->put('admin_login.last_activity', now()->timestamp);
    }

    public function hasExpiredIdleSession(Request $request): bool
    {
        $lastActivity = $request->session()->get('admin_login.last_activity');

        return ! is_int($lastActivity)
            || now()->timestamp - $lastActivity > (int) config('admin.idle_timeout_minutes') * 60;
    }

    public function cancelPending(Request $request): void
    {
        $sessionKey = $request->session()->get(self::PENDING_SESSION_KEY);
        if (is_string($sessionKey) && $sessionKey !== '') {
            AdminLoginChallenge::query()
                ->where('session_hash', hash('sha256', $sessionKey))
                ->update(['invalidated_at' => now()]);
        }

        $request->session()->forget($this->pendingKeys());
    }

    public function clearVerifiedSession(Request $request): void
    {
        $request->session()->forget([
            self::VERIFIED_USER_ID,
            self::VERIFIED_SESSION_VERSION,
            self::VERIFIED_AT,
            'admin_login.last_activity',
        ]);
    }

    /** @return array<int, string> */
    private function pendingKeys(): array
    {
        return [
            self::PENDING_USER_ID,
            self::PENDING_STARTED_AT,
            self::PENDING_SESSION_KEY,
            self::PENDING_SESSION_VERSION,
        ];
    }
}
