<?php

namespace App\Http\Controllers;

use App\Enums\UserRole;
use App\Models\Booking;
use App\Models\BookingDisputeActivity;
use App\Models\User;
use App\Notifications\AdminTravelerIssueReceived;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Notification;
use Illuminate\View\View;

class TravelerIssueController extends Controller
{
    public function index(Request $request): View
    {
        $this->authorizeTraveler($request);

        $issues = $request->user()
            ->travelerIssues()
            ->with(['booking:id,booking_reference'])
            ->latest()
            ->paginate(15);

        return view('account.issues.index', ['issues' => $issues]);
    }

    public function create(Request $request): View
    {
        $this->authorizeTraveler($request);

        $bookings = $request->user()
            ->bookings()
            ->select(['id', 'booking_reference', 'created_at'])
            ->latest()
            ->get();

        return view('account.issues.create', ['bookings' => $bookings]);
    }

    public function store(Request $request): RedirectResponse
    {
        $traveler = $this->authorizeTraveler($request);
        $data = $request->validate([
            'type' => ['required', 'in:complaint,booking_dispute'],
            'booking_id' => ['nullable', 'required_if:type,booking_dispute', 'integer', 'exists:bookings,id'],
            'subject' => ['required', 'string', 'min:5', 'max:160'],
            'description' => ['required', 'string', 'min:10', 'max:5000'],
        ]);

        if (isset($data['booking_id'])) {
            $bookingBelongsToTraveler = Booking::query()
                ->whereKey($data['booking_id'])
                ->where('user_id', $traveler->id)
                ->exists();

            if (! $bookingBelongsToTraveler) {
                return back()
                    ->withErrors(['booking_id' => __('messages.traveler_issues.booking_not_owned')])
                    ->withInput();
            }
        }

        $issue = $traveler->travelerIssues()->create([
            'booking_id' => $data['booking_id'] ?? null,
            'type' => $data['type'],
            'category' => 'general',
            'subject' => $data['subject'],
            'description' => $data['description'],
        ]);
        if ($issue->type === 'booking_dispute' && $issue->booking_id !== null) {
            BookingDisputeActivity::query()->create([
                'traveler_issue_id' => $issue->id,
                'actor_id' => $traveler->id,
                'event_type' => 'opened',
                'note' => $issue->description,
                'metadata' => ['source' => 'traveler'],
            ]);
        }

        $admins = User::query()->where('role', UserRole::ADMIN)->get();
        Notification::send($admins, new AdminTravelerIssueReceived($issue));

        return redirect()
            ->route('account.issues.show', ['issue' => $issue->id, 'lang' => app()->getLocale()])
            ->with('status', __('messages.traveler_issues.submitted'));
    }

    public function show(Request $request, int $issue): View
    {
        $traveler = $this->authorizeTraveler($request);
        $issueRecord = $traveler->travelerIssues()
            ->with(['booking:id,booking_reference'])
            ->findOrFail($issue);

        return view('account.issues.show', ['issue' => $issueRecord]);
    }

    private function authorizeTraveler(Request $request): User
    {
        $traveler = $request->user();
        abort_unless($traveler?->role === UserRole::TRAVELER, 403);

        return $traveler;
    }
}
