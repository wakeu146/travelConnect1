<?php

namespace App\Contracts;

use App\Models\AdminLoginChallenge;
use App\Models\User;

interface AdminSecondFactor
{
    public function usesAuthenticator(User $admin): bool;

    public function issue(User $admin, string $sessionKey): AdminLoginChallenge;

    public function verify(User $admin, AdminLoginChallenge $challenge, string $proof): bool;
}
