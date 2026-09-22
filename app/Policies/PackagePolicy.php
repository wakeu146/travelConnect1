<?php

namespace App\Policies;

use App\Models\Package;
use App\Models\User;

class PackagePolicy
{
    public function view(User $user, Package $package): bool
    {
        return $user->isAgencyOwner() && $package->agency_id === $user->agency?->id;
    }

    public function create(User $user): bool
    {
        return $user->isAgencyOwner() && $user->agency()->exists();
    }

    public function update(User $user, Package $package): bool
    {
        return $user->isAgencyOwner() && $package->agency_id === $user->agency?->id;
    }

    public function delete(User $user, Package $package): bool
    {
        return $this->update($user, $package);
    }
}
