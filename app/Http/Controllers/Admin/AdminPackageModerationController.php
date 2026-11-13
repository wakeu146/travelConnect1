<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Package;
use App\Services\AdminTravelManagementService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class AdminPackageModerationController extends Controller
{
    public function show(
        Request $request,
        Package $package,
        AdminTravelManagementService $travelManagement,
        AdminDashboardController $dashboard,
    ): View {
        return $dashboard->renderTravelManagementPage(
            $request,
            'all_travel_packages',
            $travelManagement->package($package),
            $package->title,
        );
    }

    public function moderate(
        Request $request,
        Package $package,
        AdminTravelManagementService $travelManagement,
    ): RedirectResponse {
        $data = $request->validate([
            'decision' => ['required', Rule::in(['restrict', 'restore'])],
            'reason' => ['nullable', 'string', 'min:10', 'max:2000'],
        ]);

        $travelManagement->moderatePackage(
            $package,
            (int) $request->user()->id,
            $data['decision'],
            $data['reason'] ?? null,
        );

        return redirect()->route('admin.packages.show', [
            'package' => $package->id,
            'lang' => app()->getLocale(),
        ])->with('status', __('messages.admin_travel.moderation_saved'));
    }
}
