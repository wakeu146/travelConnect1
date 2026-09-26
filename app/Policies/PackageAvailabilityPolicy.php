<?php

namespace App\Policies;

use App\Models\PackageAvailability;
use App\Models\User;

class PackageAvailabilityPolicy
{
    public function view(User $user, PackageAvailability $availability): bool
    {
        return $user->isAgencyOwner() && $availability->package && $availability->package->agency_id === $user->agency?->id;
    }

    public function create(User $user): bool
    {
        return $user->isAgencyOwner() && $user->agency()->exists();
    }

    public function update(User $user, PackageAvailability $availability): bool
    {
        return $user->isAgencyOwner() && $availability->package && $availability->package->agency_id === $user->agency?->id;
    }

    public function delete(User $user, PackageAvailability $availability): bool
    {
        return $this->update($user, $availability);
    }
}
