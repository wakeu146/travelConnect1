<?php

namespace App\Http\Controllers;

use App\Enums\TravelRequestProposalStatus;
use App\Models\Agency;
use App\Models\TravelRequest;
use App\Models\TravelRequestProposal;
use App\Models\TravelRequestRecipient;
use App\Services\CustomTravelRequestService;
use Illuminate\Database\QueryException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class AgencyCustomRequestController extends Controller
{
    public function index(Request $request): View
    {
        $agency = $this->agency($request);
        $filters = $request->validate([
            'search' => ['nullable', 'string', 'max:120'],
            'read' => ['nullable', Rule::in(['unread', 'read'])],
            'applied' => ['nullable', Rule::in(['yes', 'no'])],
            'destination' => ['nullable', 'string', 'max:191'],
        ]);
        $query = TravelRequest::query()
            ->whereIn('status', ['open', 'converted', 'closed', 'rejected', 'withdrawn', 'expired'])
            ->where(function ($visibility) use ($agency): void {
                $visibility->where(function ($open) use ($agency): void {
                    $open->where('status', 'open')->whereHas('recipients', fn ($recipientQuery) => $recipientQuery
                        ->where('agency_id', $agency->id)->where('status', 'active'));
                })->orWhereHas('proposals', fn ($proposalQuery) => $proposalQuery->where('agency_id', $agency->id));
            })
            ->with([
                'services',
                'recipients' => fn ($recipientQuery) => $recipientQuery->where('agency_id', $agency->id),
            ])
            ->withCount(['proposals as agency_proposals_count' => fn ($proposalQuery) => $proposalQuery
                ->where('agency_id', $agency->id)]);

        if (($filters['read'] ?? null) === 'unread') {
            $query->whereHas('recipients', fn ($recipientQuery) => $recipientQuery
                ->where('agency_id', $agency->id)->whereNull('read_at'));
        } elseif (($filters['read'] ?? null) === 'read') {
            $query->whereHas('recipients', fn ($recipientQuery) => $recipientQuery
                ->where('agency_id', $agency->id)->whereNotNull('read_at'));
        }

        if (($filters['applied'] ?? null) === 'yes') {
            $query->whereHas('proposals', fn ($proposalQuery) => $proposalQuery->where('agency_id', $agency->id));
        } elseif (($filters['applied'] ?? null) === 'no') {
            $query->whereDoesntHave('proposals', fn ($proposalQuery) => $proposalQuery->where('agency_id', $agency->id));
        }

        if (isset($filters['destination'])) {
            $query->where('destination', 'like', '%'.addcslashes($filters['destination'], '\\%_').'%');
        }

        if (($filters['search'] ?? '') !== '') {
            $term = '%'.addcslashes($filters['search'], '\\%_').'%';
            $query->where(fn ($search) => $search
                ->where('reference', 'like', $term)
                ->orWhere('title', 'like', $term)
                ->orWhere('destination', 'like', $term)
                ->orWhere('origin', 'like', $term));
        }

        $requests = $query->latest('published_at')->latest('id')->paginate(15)->withQueryString();

        return view('agencies.custom-requests.index', compact('requests', 'filters'));
    }

    public function show(TravelRequest $travelRequest, Request $request, CustomTravelRequestService $customRequests): View
    {
        $agency = $this->agency($request);
        $recipient = $this->recipient($travelRequest, $agency);
        $this->authorize('view', $travelRequest);
        if ($recipient->status === 'active' && $travelRequest->status === 'open') {
            $customRequests->markViewed($recipient);
        }
        $travelRequest->load(['services', 'traveler:id,name', 'booking'])->loadCount('recipients');
        $proposal = $travelRequest->proposals()->where('agency_id', $agency->id)->first();

        return view('agencies.custom-requests.show', compact('travelRequest', 'recipient', 'proposal'));
    }

    public function markRead(TravelRequest $travelRequest, Request $request, CustomTravelRequestService $customRequests): RedirectResponse
    {
        $agency = $this->agency($request);
        $recipient = $this->recipient($travelRequest, $agency, requireActive: true);
        $this->authorize('view', $travelRequest);
        $customRequests->markRead($recipient);

        return back()->with('status', __('messages.custom_requests.read_marked'));
    }

    public function createProposal(TravelRequest $travelRequest, Request $request): View
    {
        $agency = $this->agency($request);
        $this->recipient($travelRequest, $agency, requireActive: true);
        $this->authorize('view', $travelRequest);
        abort_unless($travelRequest->status === 'open', 404);
        $proposal = $travelRequest->proposals()->where('agency_id', $agency->id)->first();
        abort_if($proposal && $proposal->status !== TravelRequestProposalStatus::WITHDRAWN->value, 404);

        return view('agencies.custom-requests.proposal', compact('travelRequest', 'proposal'));
    }

    public function storeProposal(
        TravelRequest $travelRequest,
        Request $request,
        CustomTravelRequestService $customRequests,
    ): RedirectResponse {
        $agency = $this->agency($request);
        $this->recipient($travelRequest, $agency, requireActive: true);
        $this->authorize('view', $travelRequest);

        $data = $request->validate([
            'price' => ['required', 'numeric', 'gt:0', 'max:9999999999.99'],
            'currency' => ['required', Rule::in([config('travelconnect.currency')])],
            'price_breakdown_text' => ['nullable', 'string', 'max:5000'],
            'services_included_text' => ['required', 'string', 'max:5000'],
            'services_excluded_text' => ['nullable', 'string', 'max:5000'],
            'itinerary' => ['nullable', 'string', 'max:10000'],
            'estimated_timeline' => ['nullable', 'string', 'max:255'],
            'message' => ['required', 'string', 'max:5000'],
            'terms' => ['nullable', 'string', 'max:5000'],
            'valid_until' => ['nullable', 'date', 'after_or_equal:today'],
        ]);
        $payload = [
            ...collect($data)->except(['price_breakdown_text', 'services_included_text', 'services_excluded_text'])->all(),
            'price_breakdown' => $this->toList($data['price_breakdown_text'] ?? ''),
            'services_included' => $this->toList($data['services_included_text']),
            'services_excluded' => $this->toList($data['services_excluded_text'] ?? ''),
        ];

        try {
            $customRequests->submitProposal($travelRequest, $agency, $request->user(), $payload);
        } catch (QueryException $exception) {
            if ($travelRequest->proposals()->where('agency_id', $agency->id)->exists()) {
                return back()->withErrors(['proposal' => __('messages.custom_requests.already_applied')])->withInput();
            }

            throw $exception;
        }

        return redirect()->route('agency.custom-requests.index', ['lang' => app()->getLocale()])
            ->with('status', __('messages.custom_requests.proposal_submitted'));
    }

    public function applications(Request $request): View
    {
        $agency = $this->agency($request);
        $proposals = $agency->travelRequestProposals()
            ->with(['travelRequest', 'booking'])
            ->latest('submitted_at')
            ->paginate(15)
            ->withQueryString();

        return view('agencies.custom-requests.applications', compact('proposals'));
    }

    public function withdrawProposal(
        TravelRequestProposal $proposal,
        Request $request,
        CustomTravelRequestService $customRequests,
    ): RedirectResponse {
        $this->authorize('update', $proposal);
        abort_unless($proposal->agency_id === $this->agency($request)->id, 404);
        $customRequests->withdrawProposal($proposal, $request->user());

        return back()->with('status', __('messages.custom_requests.proposal_withdrawn'));
    }

    private function agency(Request $request): Agency
    {
        $agency = $request->user()->agency;
        abort_unless($agency && $agency->operating_status === 'active', 403);

        return $agency;
    }

    private function recipient(TravelRequest $travelRequest, Agency $agency, bool $requireActive = false): TravelRequestRecipient
    {
        $query = $travelRequest->recipients()->where('agency_id', $agency->id);

        if ($requireActive) {
            $query->where('status', 'active');
        }

        return $query->firstOrFail();
    }

    /**
     * @return list<string>
     */
    private function toList(string $value): array
    {
        return collect(preg_split('/\r\n|\r|\n/', $value) ?: [])
            ->map(fn (string $line): string => trim($line))
            ->filter()
            ->values()
            ->all();
    }
}
