<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\AdminAlertQueue;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class AdminAlertsController extends Controller
{
    public function acknowledge(
        Request $request,
        string $source,
        int $sourceId,
        AdminAlertQueue $alertQueue,
    ): RedirectResponse {
        return $this->transition($request, $source, $sourceId, 'acknowledge', $alertQueue);
    }

    public function resolve(
        Request $request,
        string $source,
        int $sourceId,
        AdminAlertQueue $alertQueue,
    ): RedirectResponse {
        return $this->transition($request, $source, $sourceId, 'resolve', $alertQueue);
    }

    private function transition(
        Request $request,
        string $source,
        int $sourceId,
        string $action,
        AdminAlertQueue $alertQueue,
    ): RedirectResponse {
        $actor = $request->user();
        abort_unless($actor instanceof User, 403);

        $alertQueue->transition($source, $sourceId, $action, $actor);

        return redirect()
            ->route('admin.dashboard.page', [
                'page' => 'alerts',
                'source' => $source,
                'source_id' => $sourceId,
                'lang' => app()->getLocale(),
            ])
            ->with('status', __('messages.admin_alerts.action_saved'));
    }
}
