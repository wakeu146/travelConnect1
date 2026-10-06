<?php

namespace App\Services;

use App\Contracts\AdminSecondFactor;
use App\Models\AdminLoginChallenge;
use App\Models\User;

class ConfiguredAdminSecondFactor implements AdminSecondFactor
{
    public function __construct(
        private readonly EmailAdminSecondFactor $email,
        private readonly TotpAdminSecondFactor $totp,
    ) {}

    public function usesAuthenticator(User $admin): bool
    {
        return $this->totp->isEnrolled($admin);
    }

    public function issue(User $admin, string $sessionKey): AdminLoginChallenge
    {
        return $this->usesAuthenticator($admin)
            ? $this->totp->issue($admin, $sessionKey)
            : $this->email->issue($admin, $sessionKey);
    }

    public function verify(User $admin, AdminLoginChallenge $challenge, string $proof): bool
    {
        return $this->usesAuthenticator($admin)
            ? $this->totp->verify($admin, $challenge, $proof)
            : $this->email->verify($admin, $challenge, $proof);
    }
}
