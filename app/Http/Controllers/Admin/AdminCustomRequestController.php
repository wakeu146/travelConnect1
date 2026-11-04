<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\TravelRequest;
use App\Models\TravelRequestAdminNote;
use App\Services\CustomTravelRequestService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AdminCustomRequestController extends Controller
{
    public function index(Request $request, AdminDashboardController $dashboard): View
    {
        $filters = $request->validate([
            'search' => ['nullable', 'string', 'max:120'],
            'status' => ['nullable', Rule::in(['draft', 'open', 'on_hold', 'converted', 'closed', 'withdrawn', 'rejected', 'expired'])],
            'sharing_mode' => ['nullable', Rule::in(['selected', 'all'])],
        ]);
        $query = TravelRequest::query()
            ->with(['traveler:id,name,email', 'booking:id,custom_request_id,booking_reference,status,payment_status'])
            ->withCount(['recipients', 'proposals']);

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
            'open' => TravelRequest::query()->where('status', 'open')->count(),
            'with_proposals' => TravelRequest::query()->whereHas('proposals')->count(),
            'converted' => TravelRequest::query()->where('status', 'converted')->count(),
            'attention' => TravelRequest::query()->where('status', 'on_hold')->count(),
        ];

        return $dashboard->renderCustomRequests($request, 'all_requests', [
            'view' => 'index',
            'data' => compact('requests', 'filters', 'summary'),
        ]);
    }

    public function show(Request $request, TravelRequest $travelRequest, AdminDashboardController $dashboard): View
    {
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

        return $dashboard->renderCustomRequests($request, 'all_requests', [
            'view' => 'show',
            'data' => compact('travelRequest'),
        ]);
    }

    public function moderate(
        Request $request,
        TravelRequest $travelRequest,
        CustomTravelRequestService $customRequests,
        string $action,
    ): RedirectResponse {
        abort_unless(in_array($action, ['hold', 'restore', 'reject', 'close', 'reopen'], true), 404);
        $data = $request->validate([
            'reason' => ['required', 'string', 'min:5', 'max:2000'],
        ]);
        $customRequests->moderate($travelRequest, $request->user(), $action, $data['reason']);

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
        ]);
        $query = TravelRequest::query()->with('traveler:id,name,email')->withCount(['recipients', 'proposals']);
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
}
