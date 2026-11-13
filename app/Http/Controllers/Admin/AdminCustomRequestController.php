<?php

namespace App\Http\Controllers\Admin;

use App\Enums\TravelRequestProposalStatus;
use App\Http\Controllers\Controller;
use App\Models\TravelRequest;
use App\Models\TravelRequestAdminNote;
use App\Services\CustomTravelRequestService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AdminCustomRequestController extends Controller
{
    private const QUEUES = [
        'all_requests',
        'new_requests',
        'in_progress_requests',
        'quoted_requests',
        'accepted_converted_requests',
        'closed_requests',
    ];

    public function index(Request $request, AdminDashboardController $dashboard): View
    {
        $filters = $request->validate([
            'search' => ['nullable', 'string', 'max:120'],
            'status' => ['nullable', Rule::in(['draft', 'open', 'on_hold', 'converted', 'closed', 'withdrawn', 'rejected', 'expired'])],
            'sharing_mode' => ['nullable', Rule::in(['selected', 'all'])],
            'queue' => ['nullable', Rule::in(self::QUEUES)],
        ]);
        $queue = $filters['queue'] ?? 'all_requests';
        $query = TravelRequest::query()
            ->with(['traveler:id,name,email', 'booking:id,custom_request_id,booking_reference,status,payment_status'])
            ->withCount(['recipients', 'proposals']);

        $this->applyQueue($query, $queue);

        if (isset($filters['status'])) {
            $query->where('status', $filters['status']);
        }
        if (isset($filters['sharing_mode'])) {
            $query->where('sharing_mode', $filters['sharing_mode']);
        }
        if (($filters['search'] ?? '') !== '') {
            $term = '%'.addcslashes($filters['search'], '\\%_').'%';
            $query->where(fn ($search) => $search
                ->where('reference', 'like', $term)
                ->orWhere('title', 'like', $term)
                ->orWhere('origin', 'like', $term)
                ->orWhere('destination', 'like', $term)
                ->orWhereHas('traveler', fn ($traveler) => $traveler->where('name', 'like', $term)->orWhere('email', 'like', $term)));
        }

        $requests = $query->latest('created_at')->latest('id')->paginate(25)->withQueryString();
        $summary = [
            'total' => TravelRequest::query()->count(),
            'new' => $this->queryForQueue('new_requests')->count(),
            'in_progress' => $this->queryForQueue('in_progress_requests')->count(),
            'quoted' => $this->queryForQueue('quoted_requests')->count(),
            'converted' => TravelRequest::query()->where('status', 'converted')->count(),
            'closed' => $this->queryForQueue('closed_requests')->count(),
        ];

        return $dashboard->renderCustomRequests($request, $queue, [
            'view' => 'index',
            'data' => compact('requests', 'filters', 'summary', 'queue'),
        ]);
    }

    public function show(Request $request, TravelRequest $travelRequest, AdminDashboardController $dashboard): View
    {
        $queue = $request->validate([
            'queue' => ['nullable', Rule::in(self::QUEUES)],
        ])['queue'] ?? 'all_requests';
        $travelRequest->load([
            'traveler:id,name,email,phone',
            'services',
            'recipients.agency.owner:id,name,email',
            'proposals.agency.owner:id,name,email',
            'proposals.owner:id,name,email',
            'proposals.revisions.actor:id,name',
            'booking',
            'events.actor:id,name',
            'adminNotes.admin:id,name',
        ]);

        $moderationActions = match ($travelRequest->status) {
            'open' => ['hold', 'reject', 'close'],
            'on_hold' => ['restore', 'reject', 'close'],
            'closed', 'rejected' => ['reopen'],
            default => [],
        };

        return $dashboard->renderCustomRequests($request, $queue, [
            'view' => 'show',
            'data' => compact('travelRequest', 'moderationActions', 'queue'),
        ]);
    }

    public function moderate(
        Request $request,
        TravelRequest $travelRequest,
        CustomTravelRequestService $customRequests,
        ?string $action = null,
    ): RedirectResponse {
        if ($action !== null) {
            abort_unless(in_array($action, ['hold', 'restore', 'reject', 'close', 'reopen'], true), 404);
        }
        $data = $request->validate([
            'action' => [$action === null ? 'required' : 'nullable', Rule::in(['hold', 'restore', 'reject', 'close', 'reopen'])],
            'reason' => ['nullable', 'string', 'min:5', 'max:2000'],
        ]);
        $action = $data['action'] ?? $action;
        abort_unless(is_string($action), 422);
        $customRequests->moderate($travelRequest, $request->user(), $action, $data['reason'] ?? null);

        return back()->with('status', __('messages.custom_requests.admin_action_saved'));
    }

    public function note(Request $request, TravelRequest $travelRequest, CustomTravelRequestService $customRequests): RedirectResponse
    {
        $data = $request->validate(['content' => ['required', 'string', 'min:3', 'max:5000']]);
        TravelRequestAdminNote::query()->create([
            'travel_request_id' => $travelRequest->id,
            'admin_id' => $request->user()->id,
            'content' => $data['content'],
        ]);

        $customRequests->recordEvent($travelRequest, $request->user(), 'admin_note_added');

        return back()->with('status', __('messages.custom_requests.note_saved'));
    }

    public function export(Request $request): StreamedResponse
    {
        $filters = $request->validate([
            'search' => ['nullable', 'string', 'max:120'],
            'status' => ['nullable', Rule::in(['draft', 'open', 'on_hold', 'converted', 'closed', 'withdrawn', 'rejected', 'expired'])],
            'sharing_mode' => ['nullable', Rule::in(['selected', 'all'])],
            'queue' => ['nullable', Rule::in(self::QUEUES)],
        ]);
        $query = TravelRequest::query()->with('traveler:id,name,email')->withCount(['recipients', 'proposals']);
        $this->applyQueue($query, $filters['queue'] ?? 'all_requests');
        if (isset($filters['status'])) {
            $query->where('status', $filters['status']);
        }
        if (isset($filters['sharing_mode'])) {
            $query->where('sharing_mode', $filters['sharing_mode']);
        }
        if (($filters['search'] ?? '') !== '') {
            $term = '%'.addcslashes($filters['search'], '\\%_').'%';
            $query->where(fn ($search) => $search
                ->where('reference', 'like', $term)
                ->orWhere('title', 'like', $term)
                ->orWhere('destination', 'like', $term)
                ->orWhereHas('traveler', fn ($traveler) => $traveler->where('name', 'like', $term)->orWhere('email', 'like', $term)));
        }

        return response()->streamDownload(function () use ($query): void {
            $handle = fopen('php://output', 'w');
            fputcsv($handle, ['Reference', 'Traveler', 'Email', 'Title', 'Origin', 'Destination', 'Status', 'Sharing mode', 'Recipients', 'Proposals', 'Published']);
            $query->orderBy('id')->chunk(200, function ($requests) use ($handle): void {
                foreach ($requests as $travelRequest) {
                    fputcsv($handle, [
                        $travelRequest->reference,
                        $travelRequest->traveler?->name,
                        $travelRequest->traveler?->email,
                        $travelRequest->title,
                        $travelRequest->origin,
                        $travelRequest->destination,
                        $travelRequest->status,
                        $travelRequest->sharing_mode,
                        $travelRequest->recipients_count,
                        $travelRequest->proposals_count,
                        $travelRequest->published_at?->toIso8601String(),
                    ]);
                }
            });
            fclose($handle);
        }, 'custom-travel-requests.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    private function queryForQueue(string $queue): Builder
    {
        $query = TravelRequest::query();
        $this->applyQueue($query, $queue);

        return $query;
    }

    private function applyQueue(Builder $query, string $queue): void
    {
        $submittedProposal = fn (Builder $proposals): Builder => $proposals
            ->where('status', TravelRequestProposalStatus::SUBMITTED->value);

        match ($queue) {
            'new_requests' => $query
                ->where('status', 'open')
                ->whereDoesntHave('proposals', $submittedProposal)
                ->whereDoesntHave('recipients', fn (Builder $recipients): Builder => $recipients->whereNotNull('first_viewed_at')),
            'in_progress_requests' => $query->where(function (Builder $requests) use ($submittedProposal): void {
                $requests->where('status', 'on_hold')
                    ->orWhere(function (Builder $openRequests) use ($submittedProposal): void {
                        $openRequests->where('status', 'open')
                            ->whereHas('recipients', fn (Builder $recipients): Builder => $recipients->whereNotNull('first_viewed_at'))
                            ->whereDoesntHave('proposals', $submittedProposal);
                    });
            }),
            'quoted_requests' => $query
                ->where('status', 'open')
                ->whereHas('proposals', $submittedProposal),
            'accepted_converted_requests' => $query->where('status', 'converted'),
            'closed_requests' => $query->whereIn('status', ['closed', 'rejected', 'withdrawn', 'expired']),
            default => null,
        };
    }
}
