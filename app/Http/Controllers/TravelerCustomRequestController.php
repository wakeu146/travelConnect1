<?php

namespace App\Http\Controllers;

use App\Models\Service;
use App\Models\TravelRequest;
use App\Models\TravelRequestProposal;
use App\Services\CustomTravelRequestService;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class TravelerCustomRequestController extends Controller
{
    public function index(Request $request): View
    {
        $this->authorize('viewAny', TravelRequest::class);
        $requests = $request->user()->travelRequests()
            ->withCount(['recipients', 'proposals'])
            ->with('booking:id,custom_request_id,booking_reference,status,payment_status')
            ->latest('updated_at')
            ->paginate(12)
            ->withQueryString();

        return view('account.custom-requests.index', compact('requests'));
    }

    public function create(CustomTravelRequestService $customRequests): View
    {
        $this->authorize('create', TravelRequest::class);

        return view('account.custom-requests.create', [
            'agencies' => $customRequests->eligibleAgencies()
                ->with(['services:id,name'])
                ->withCount(['reviews as published_reviews_count' => fn ($query) => $query->where('status', 'published')])
                ->orderBy('company_name')
                ->paginate(20)
                ->withQueryString(),
            'services' => Service::query()->orderBy('name')->get(),
            'eligibleAgencyCount' => $customRequests->eligibleAgencies()->count(),
        ]);
    }

    public function store(Request $request, CustomTravelRequestService $customRequests): RedirectResponse
    {
        $this->authorize('create', TravelRequest::class);

        $data = $request->validate([
            'title' => ['required', 'string', 'max:191'],
            'origin' => ['required', 'string', 'max:191'],
            'destination' => ['required', 'string', 'max:191'],
            'destination_country' => ['nullable', 'string', 'max:191'],
            'travel_date' => ['required', 'date', 'after_or_equal:today'],
            'return_date' => ['nullable', 'date', 'after_or_equal:travel_date'],
            'flexible_dates' => ['sometimes', 'boolean'],
            'traveler_count' => ['required', 'integer', 'min:1', 'max:100'],
            'purpose' => ['nullable', 'string', 'max:120'],
            'budget_min' => ['nullable', 'numeric', 'min:0', 'max:9999999999.99'],
            'budget_max' => ['nullable', 'numeric', 'min:0', 'max:9999999999.99'],
            'budget_currency' => ['required', Rule::in([config('travelconnect.currency')])],
            'budget_flexible' => ['sometimes', 'boolean'],
            'description' => ['required', 'string', 'max:10000'],
            'special_requirements' => ['nullable', 'string', 'max:5000'],
            'service_ids' => ['required', 'array', 'min:1', 'max:30'],
            'service_ids.*' => ['required', 'integer', 'distinct', 'exists:services,id'],
            'sharing_mode' => ['required', Rule::in(['selected', 'all'])],
            'agency_ids' => [
                'required_if:sharing_mode,selected',
                'array',
                Rule::when($request->input('sharing_mode') === 'selected', ['min:1']),
                'max:100',
            ],
            'agency_ids.*' => ['required', 'integer', 'distinct'],
        ]);

        if (isset($data['budget_min'], $data['budget_max']) && (float) $data['budget_min'] > (float) $data['budget_max']) {
            return back()->withErrors(['budget_max' => __('messages.custom_requests.budget_range_error')])->withInput();
        }

        $agencyIds = array_map('intval', $data['agency_ids'] ?? []);
        $eligibleSelectedCount = $agencyIds === []
            ? 0
            : $customRequests->eligibleAgencies()->whereKey($agencyIds)->count();

        if (($data['sharing_mode'] === 'selected' && $eligibleSelectedCount !== count($agencyIds))
            || ($data['sharing_mode'] === 'all' && $agencyIds !== [])) {
            return back()->withErrors(['agency_ids' => __('messages.custom_requests.invalid_agency_selection')])->withInput();
        }

        $travelRequest = $customRequests->publish(
            $request->user(),
            collect($data)->except(['service_ids', 'agency_ids', 'sharing_mode'])->all(),
            array_map('intval', $data['service_ids']),
            $agencyIds,
            $data['sharing_mode'] === 'all',
        );

        return redirect()->route('account.custom-requests.show', [
            'travelRequest' => $travelRequest->id,
            'lang' => app()->getLocale(),
        ])->with('status', __('messages.custom_requests.publish_success'));
    }

    public function show(TravelRequest $travelRequest): View
    {
        abort_unless($travelRequest->user_id === request()->user()->id, 404);
        $this->authorize('view', $travelRequest);
        $travelRequest->proposals()
            ->where('status', 'submitted')
            ->whereNotNull('valid_until')
            ->whereDate('valid_until', '<', today())
            ->update(['status' => 'expired']);
        $travelRequest->load([
            'services',
            'recipients.agency',
            'proposals' => fn ($query) => $query->with(['agency.services', 'agency.reviews' => fn ($reviews) => $reviews->where('status', 'published')])->latest('submitted_at'),
            'booking.package:id,title,destination',
            'booking.availability:id,departure_date',
            'booking.agency:id,company_name',
            'booking.review',
            'events.actor',
        ]);

        return view('account.custom-requests.show', compact('travelRequest'));
    }

    public function select(
        Request $request,
        TravelRequest $travelRequest,
        TravelRequestProposal $proposal,
        CustomTravelRequestService $customRequests,
    ): RedirectResponse {
        abort_unless($travelRequest->user_id === $request->user()->id, 404);
        $this->authorize('view', $travelRequest);
        abort_unless($proposal->travel_request_id === $travelRequest->id, 404);
        $request->validate(['confirm' => ['required', 'accepted']]);

        try {
            $booking = $customRequests->convertToBooking($travelRequest, $proposal, $request->user());
        } catch (\RuntimeException|ModelNotFoundException $exception) {
            return back()->withErrors(['proposal' => $exception->getMessage()]);
        }

        return redirect()->route('account.custom-requests.show', [
            'travelRequest' => $travelRequest->id,
            'lang' => app()->getLocale(),
        ])->with([
            'status' => __('messages.custom_requests.booking_created'),
            'booking_created' => true,
        ]);
    }
}
