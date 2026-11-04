<?php

namespace App\Policies;

use App\Models\TravelRequestProposal;
use App\Models\User;

class TravelRequestProposalPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isTraveler() || $user->isAgencyOwner() || $user->isAdmin();
    }

    public function view(User $user, TravelRequestProposal $travelRequestProposal): bool
    {
        return $user->isAdmin()
            || ($user->isTraveler() && $travelRequestProposal->travelRequest?->user_id === $user->id)
            || ($user->isAgencyOwner() && $travelRequestProposal->agency_id === $user->agency?->id);
    }

    public function create(User $user): bool
    {
        return $user->isAgencyOwner() && $user->agency !== null;
    }

    public function update(User $user, TravelRequestProposal $travelRequestProposal): bool
    {
        return $user->isAgencyOwner()
            && $travelRequestProposal->agency_id === $user->agency?->id
            && $travelRequestProposal->status === 'submitted'
            && $travelRequestProposal->travelRequest?->status === 'open';
    }

    public function delete(User $user, TravelRequestProposal $travelRequestProposal): bool
    {
        return false;
    }

    public function restore(User $user, TravelRequestProposal $travelRequestProposal): bool
    {
        return false;
    }

    /**
     * Determine whether the user can permanently delete the model.
     */
    public function forceDelete(User $user, TravelRequestProposal $travelRequestProposal): bool
    {
        return false;
    }
}
