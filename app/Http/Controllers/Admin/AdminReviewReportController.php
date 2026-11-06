<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ContentReport;
use App\Models\Review;
use App\Models\TravelerIssue;
use App\Services\AdminReviewReportManagementService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class AdminReviewReportController extends Controller
{
    public function moderateReview(
        Request $request,
        Review $review,
        AdminReviewReportManagementService $management,
    ): RedirectResponse {
        $data = $request->validate([
            'decision' => ['required', Rule::in(['hide', 'restore'])],
            'reason' => ['nullable', 'string', 'min:10', 'max:2000'],
        ]);

        $management->moderateReview($review, $request->user(), $data['decision'], $data['reason'] ?? null);

        return redirect()->route('admin.dashboard.page', [
            'page' => 'reviews_ratings',
            'lang' => app()->getLocale(),
        ])->with('status', __('messages.trust_safety.review_moderation_saved'));
    }

    public function actOnReport(
        Request $request,
        ContentReport $report,
        AdminReviewReportManagementService $management,
    ): RedirectResponse {
        $data = $request->validate([
            'action' => ['required', Rule::in(['investigate', 'dismiss', 'restrict', 'remove'])],
            'assignee_id' => [
                'nullable',
                Rule::exists('users', 'id')->where('role', 'admin')->whereNull('deleted_at'),
            ],
            'reason' => ['required', 'string', 'min:10', 'max:2000'],
        ]);

        $management->actOnReport(
            $report,
            $request->user(),
            $data['action'],
            isset($data['assignee_id']) ? (int) $data['assignee_id'] : null,
            $data['reason'],
        );

        return redirect()->route('admin.dashboard.page', [
            'page' => 'reported_content',
            'lang' => app()->getLocale(),
        ])->with('status', __('messages.trust_safety.report_action_saved'));
    }

    public function actOnComplaint(
        Request $request,
        TravelerIssue $issue,
        AdminReviewReportManagementService $management,
    ): RedirectResponse {
        $data = $request->validate([
            'action' => ['required', Rule::in(['assign', 'investigate', 'note', 'resolve', 'reopen'])],
            'assignee_id' => [
                Rule::requiredIf($request->input('action') === 'assign'),
                'nullable',
                Rule::exists('users', 'id')->where('role', 'admin')->whereNull('deleted_at'),
            ],
            'reason' => ['required', 'string', 'min:3', 'max:2000'],
        ]);

        $management->actOnComplaint(
            $issue,
            $request->user(),
            $data['action'],
            isset($data['assignee_id']) ? (int) $data['assignee_id'] : null,
            $data['reason'],
        );

        return redirect()->route('admin.dashboard.page', [
            'page' => 'user_agency_complaints',
            'lang' => app()->getLocale(),
        ])->with('status', __('messages.trust_safety.complaint_action_saved'));
    }
}
