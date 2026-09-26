<?php

namespace App\Policies;

use App\Models\Booking;
use App\Models\User;

class BookingPolicy
{
    public function viewAgency(User $user, Booking $booking): bool
    {
        return $user->isAgencyOwner() && $booking->agency_id === $user->agency?->id;
    }

    public function view(User $user, Booking $booking): bool
    {
        return $booking->user_id === $user->id;
    }

    public function create(User $user): bool
    {
        return $user->isTraveler();
    }

    public function update(User $user, Booking $booking): bool
    {
        return $user->isAgencyOwner() && $booking->agency_id === $user->agency?->id;
    }

    public function cancel(User $user, Booking $booking): bool
    {
        return $user->isAgencyOwner() && $booking->agency_id === $user->agency?->id
            || $booking->user_id === $user->id;
    }
}
