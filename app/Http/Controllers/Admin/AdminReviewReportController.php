<?php

namespace App\Http\Controllers\Admin;

use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Models\ContentReport;
use App\Models\Review;
use App\Models\TravelerIssue;
use App\Models\User;
use App\Services\AdminReviewReportManagementService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class AdminReviewReportController extends Controller
{
    public function showReview(
        Request $request,
        Review $review,
        AdminDashboardController $dashboard,
    ): View {
        $listFilters = $request->validate([
            'search' => ['nullable', 'string', 'max:120'],
            'status' => ['nullable', Rule::in(['all', 'pending', 'published', 'flagged'])],
        ]);
        $review->load([
            'user:id,name,email',
            'agency:id,company_name',
            'booking.package:id,title',
            'reports.reporter:id,name',
            'events.actor:id,name',
        ]);

        return $dashboard->renderReviewReportDetail(
            $request,
            'reviews_ratings',
            'review-detail',
            ['review' => $review, 'listFilters' => array_filter($listFilters)],
            __('messages.trust_safety.review_detail_title', ['id' => $review->id]),
        );
    }

    public function showReport(
        Request $request,
        ContentReport $report,
        AdminDashboardController $dashboard,
    ): View {
        $listFilters = $request->validate([
            'search' => ['nullable', 'string', 'max:120'],
            'status' => ['nullable', Rule::in(['all', 'open', 'investigating', 'dismissed', 'restricted', 'removed'])],
        ]);
        $report->load([
            'review.user:id,name,email',
            'review.agency:id,company_name',
            'review.booking.package:id,title',
            'reporter:id,name,email',
            'assignee:id,name',
            'events.actor:id,name',
        ]);

        return $dashboard->renderReviewReportDetail(
            $request,
            'reported_content',
            'report-detail',
            [
                'report' => $report,
                'listFilters' => array_filter($listFilters),
                'administrators' => User::query()
                    ->where('role', UserRole::ADMIN)
                    ->whereNull('deleted_at')
                    ->orderBy('name')
                    ->get(['id', 'name']),
            ],
            __('messages.trust_safety.report_detail_title', ['id' => $report->id]),
        );
    }

    public function moderateReview(
        Request $request,
        Review $review,
        AdminReviewReportManagementService $management,
    ): RedirectResponse {
        $data = $request->validate([
            'decision' => ['required', Rule::in(['hide', 'restore'])],
            'reason' => ['nullable', 'string', 'min:10', 'max:2000'],
            'search' => ['nullable', 'string', 'max:120'],
            'status' => ['nullable', Rule::in(['all', 'pending', 'published', 'flagged'])],
        ]);

        $management->moderateReview($review, $request->user(), $data['decision'], $data['reason'] ?? null);

        return redirect()->route('admin.reviews.show', [
            'review' => $review->id,
            'lang' => app()->getLocale(),
            ...array_filter([
                'search' => $data['search'] ?? null,
                'status' => $data['status'] ?? null,
            ]),
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
            'reason' => ['nullable', 'string', 'min:10', 'max:2000'],
            'search' => ['nullable', 'string', 'max:120'],
            'status' => ['nullable', Rule::in(['all', 'open', 'investigating', 'dismissed', 'restricted', 'removed'])],
        ]);

        $management->actOnReport(
            $report,
            $request->user(),
            $data['action'],
            isset($data['assignee_id']) ? (int) $data['assignee_id'] : null,
            $data['reason'] ?? null,
        );

        return redirect()->route('admin.reports.show', [
            'report' => $report->id,
            'lang' => app()->getLocale(),
            ...array_filter([
                'search' => $data['search'] ?? null,
                'status' => $data['status'] ?? null,
            ]),
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
            'reason' => ['nullable', 'string', 'min:3', 'max:2000'],
        ]);

        $management->actOnComplaint(
            $issue,
            $request->user(),
            $data['action'],
            isset($data['assignee_id']) ? (int) $data['assignee_id'] : null,
            $data['reason'] ?? null,
        );

        return redirect()->route('admin.dashboard.page', [
            'page' => 'user_agency_complaints',
            'lang' => app()->getLocale(),
        ])->with('status', __('messages.trust_safety.complaint_action_saved'));
    }
}
