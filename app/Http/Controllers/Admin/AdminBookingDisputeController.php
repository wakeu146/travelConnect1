<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\TravelerIssue;
use App\Models\User;
use App\Services\BookingDisputeService;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class AdminBookingDisputeController extends Controller
{
    public function index(
        Request $request,
        AdminDashboardController $dashboard,
        BookingDisputeService $disputes,
    ): View {
        $filters = $request->validate([
            'search' => ['nullable', 'string', 'max:120'],
            'status' => ['nullable', Rule::in(['open', 'in_review', 'awaiting_information', 'resolved', 'dismissed'])],
            'priority' => ['nullable', Rule::in(['low', 'normal', 'high', 'urgent'])],
            'category' => ['nullable', Rule::in(['general', 'booking', 'payment', 'departure', 'service', 'other'])],
            'assigned_admin_id' => [
                'nullable',
                'integer',
                Rule::exists('users', 'id')->where(fn (QueryBuilder $query) => $query->where('role', 'admin')),
            ],
            'created_from' => ['nullable', 'date_format:Y-m-d'],
            'created_to' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:created_from'],
        ]);
        $issues = $disputes->listing($filters);
        $statusCounts = TravelerIssue::query()
            ->where('type', 'booking_dispute')
            ->whereNotNull('booking_id')
            ->select('status')
            ->selectRaw('COUNT(*) as aggregate')
            ->groupBy('status')
            ->pluck('aggregate', 'status');

        return $dashboard->renderBookingsModule($request, 'booking_disputes', 'disputes', [
            'issues' => $issues,
            'filters' => $filters,
            'admins' => $disputes->administrators(),
            'statusCounts' => $statusCounts,
        ]);
    }

    public function show(
        Request $request,
        TravelerIssue $issue,
        AdminDashboardController $dashboard,
        BookingDisputeService $disputes,
    ): View {
        abort_unless($request->user()?->isAdmin(), 403);
        abort_unless($issue->type === 'booking_dispute' && $issue->booking_id !== null, 404);
        $issue->load([
            'booking.traveler:id,name,email,created_at',
            'booking.agency:id,company_name',
            'booking.package:id,title,destination',
            'booking.availability:id,package_id,departure_date,capacity,status',
            'assignedAdmin:id,name,email',
            'openedByAdmin:id,name',
            'activities.actor:id,name',
        ]);

        return $dashboard->renderBookingsModule(
            $request,
            'booking_disputes',
            'dispute',
            [
                'issue' => $issue,
                'admins' => $disputes->administrators(),
            ],
            __('messages.admin_disputes.details_title'),
        );
    }

    public function assign(
        Request $request,
        TravelerIssue $issue,
        BookingDisputeService $disputes,
    ): RedirectResponse {
        $issue = $this->bookingDisputeOrFail($issue);
        $data = $request->validate([
            'assigned_admin_id' => [
                'nullable',
                'integer',
                Rule::exists('users', 'id')->where(fn (QueryBuilder $query) => $query->where('role', 'admin')),
            ],
        ]);
        $assignee = isset($data['assigned_admin_id'])
            ? User::query()->findOrFail($data['assigned_admin_id'])
            : null;

        $disputes->assign($issue, $request->user(), $assignee);

        return $this->returnToDispute($issue)->with('status', __('messages.admin_disputes.assignment_saved'));
    }

    public function transition(
        Request $request,
        TravelerIssue $issue,
        BookingDisputeService $disputes,
    ): RedirectResponse {
        $issue = $this->bookingDisputeOrFail($issue);
        $data = $request->validate([
            'status' => ['required', Rule::in(['in_review', 'awaiting_information', 'resolved', 'dismissed'])],
            'reason' => ['required', 'string', 'min:10', 'max:3000'],
        ]);

        $disputes->transition($issue, $request->user(), $data['status'], $data['reason']);

        return $this->returnToDispute($issue)->with('status', __('messages.admin_disputes.status_saved'));
    }

    public function note(
        Request $request,
        TravelerIssue $issue,
        BookingDisputeService $disputes,
    ): RedirectResponse {
        $issue = $this->bookingDisputeOrFail($issue);
        $data = $request->validate([
            'note' => ['required', 'string', 'min:3', 'max:3000'],
        ]);

        $disputes->addInternalNote($issue, $request->user(), $data['note']);

        return $this->returnToDispute($issue)->with('status', __('messages.admin_disputes.note_saved'));
    }

    private function bookingDisputeOrFail(TravelerIssue $issue): TravelerIssue
    {
        abort_unless($issue->type === 'booking_dispute' && $issue->booking_id !== null, 404);

        return $issue;
    }

    private function returnToDispute(TravelerIssue $issue): RedirectResponse
    {
        return redirect()->route('admin.booking-disputes.show', [
            'issue' => $issue->id,
            'lang' => app()->getLocale(),
        ]);
    }
}
