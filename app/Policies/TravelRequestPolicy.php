<?php

namespace App\Policies;

use App\Models\TravelRequest;
use App\Models\User;

class TravelRequestPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isTraveler() || $user->isAdmin();
    }

    public function view(User $user, TravelRequest $travelRequest): bool
    {
        return $user->isAdmin()
            || ($user->isTraveler() && $travelRequest->user_id === $user->id)
            || ($user->isAgencyOwner() && $user->agency !== null
                && (($travelRequest->status === 'open' && $travelRequest->recipients()
                    ->where('agency_id', $user->agency->id)
                    ->where('status', 'active')
                    ->exists())
                    || $travelRequest->proposals()->where('agency_id', $user->agency->id)->exists()));
    }

    public function create(User $user): bool
    {
        return $user->isTraveler();
    }

    public function update(User $user, TravelRequest $travelRequest): bool
    {
        return $user->isTraveler()
            && $travelRequest->user_id === $user->id
            && $travelRequest->status === 'draft';
    }

    public function delete(User $user, TravelRequest $travelRequest): bool
    {
        return false;
    }

    public function restore(User $user, TravelRequest $travelRequest): bool
    {
        return false;
    }

    public function forceDelete(User $user, TravelRequest $travelRequest): bool
    {
        return false;
    }
}
