<?php

namespace App\Policies;

use App\Models\Agency;
use App\Models\User;

class AgencyPolicy
{
    public function viewDashboard(User $user, Agency $agency): bool
    {
        return $user->isAgencyOwner() && $agency->user_id === $user->id;
    }
}
