<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Notifications\AdminTravelerIssueReceived;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class AdminNotificationController extends Controller
{
    public function open(Request $request, string $notification): RedirectResponse
    {
        $adminNotification = $request->user()
            ->notifications()
            ->where('type', AdminTravelerIssueReceived::class)
            ->whereKey($notification)
            ->firstOrFail();

        $data = $adminNotification->data;
        abort_unless(
            is_array($data)
                && isset($data['issue_id'])
                && is_int($data['issue_id'])
                && $data['issue_id'] > 0,
            404,
        );

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
            ->where('type', AdminTravelerIssueReceived::class)
            ->update(['read_at' => now()]);

        return back();
    }
}
