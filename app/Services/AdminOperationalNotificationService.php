<?php

namespace App\Services;

use App\Enums\UserRole;
use App\Models\Agency;
use App\Models\TravelRequest;
use App\Models\User;
use App\Notifications\AdminAgencyRegistered;
use App\Notifications\AdminCustomRequestReceived;
use Illuminate\Notifications\Notification as BaseNotification;
use Illuminate\Support\Facades\Notification;

class AdminOperationalNotificationService
{
    public function agencyRegistered(Agency $agency): void
    {
        $this->notifyAdmins(new AdminAgencyRegistered($agency));
    }

    public function customRequestReceived(TravelRequest $travelRequest): void
    {
        $this->notifyAdmins(new AdminCustomRequestReceived($travelRequest));
    }

    private function notifyAdmins(BaseNotification $notification): void
    {
        $admins = User::query()->where('role', UserRole::ADMIN)->get();

        if ($admins->isNotEmpty()) {
            Notification::send($admins, $notification);
        }
    }
}
