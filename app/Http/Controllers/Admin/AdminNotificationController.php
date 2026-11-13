<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Agency;
use App\Models\TravelRequest;
use App\Notifications\AdminAgencyDocumentReceived;
use App\Notifications\AdminAgencyRegistered;
use App\Notifications\AdminCustomRequestReceived;
use App\Notifications\AdminSecurityAlertReceived;
use App\Notifications\AdminTravelerIssueReceived;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class AdminNotificationController extends Controller
{
    public function open(Request $request, string $notification): RedirectResponse
    {
        $adminNotification = $request->user()
            ->notifications()
            ->whereIn('type', [
                AdminSecurityAlertReceived::class,
                AdminTravelerIssueReceived::class,
                AdminAgencyDocumentReceived::class,
                AdminAgencyRegistered::class,
                AdminCustomRequestReceived::class,
            ])
            ->whereKey($notification)
            ->firstOrFail();

        $data = $adminNotification->data;
        abort_unless(is_array($data), 404);

        if ($adminNotification->type === AdminSecurityAlertReceived::class) {
            abort_unless(
                ($data['source_type'] ?? null) === 'login_burst'
                    && isset($data['event_id'])
                    && is_int($data['event_id'])
                    && $data['event_id'] > 0,
                404,
            );

            $adminNotification->markAsRead();

            return redirect()->route('admin.dashboard.page', [
                'page' => 'alerts',
                'source' => 'login_burst',
                'source_id' => $data['event_id'],
                'lang' => app()->getLocale(),
            ]);
        }

        if ($adminNotification->type === AdminAgencyDocumentReceived::class) {
            abort_unless(isset($data['agency_id']) && is_int($data['agency_id']) && $data['agency_id'] > 0, 404);
            abort_unless(Agency::query()->whereKey($data['agency_id'])->exists(), 404);

            $adminNotification->markAsRead();

            return redirect()->route('admin.agencies.show', [
                'agency' => $data['agency_id'],
                'lang' => app()->getLocale(),
            ]);
        }

        if ($adminNotification->type === AdminAgencyRegistered::class) {
            abort_unless(isset($data['agency_id']) && is_int($data['agency_id']) && $data['agency_id'] > 0, 404);
            abort_unless(Agency::query()->whereKey($data['agency_id'])->exists(), 404);

            $adminNotification->markAsRead();

            return redirect()->route('admin.agencies.show', [
                'agency' => $data['agency_id'],
                'lang' => app()->getLocale(),
            ]);
        }

        if ($adminNotification->type === AdminCustomRequestReceived::class) {
            abort_unless(
                isset($data['travel_request_id'])
                    && is_int($data['travel_request_id'])
                    && $data['travel_request_id'] > 0,
                404,
            );
            abort_unless(TravelRequest::query()->whereKey($data['travel_request_id'])->exists(), 404);

            $adminNotification->markAsRead();

            return redirect()->route('admin.custom-requests.show', [
                'travelRequest' => $data['travel_request_id'],
                'lang' => app()->getLocale(),
            ]);
        }

        abort_unless(isset($data['issue_id']) && is_int($data['issue_id']) && $data['issue_id'] > 0, 404);

        $adminNotification->markAsRead();

        return redirect()->route('admin.dashboard.page', [
            'page' => 'alerts',
            'source' => 'traveler_issue',
            'source_id' => $data['issue_id'],
            'lang' => app()->getLocale(),
        ]);
    }

    public function markAllRead(Request $request): RedirectResponse
    {
        $request->user()
            ->unreadNotifications()
            ->whereIn('type', [
                AdminSecurityAlertReceived::class,
                AdminTravelerIssueReceived::class,
                AdminAgencyDocumentReceived::class,
                AdminAgencyRegistered::class,
                AdminCustomRequestReceived::class,
            ])
            ->update(['read_at' => now()]);

        return back();
    }
}
