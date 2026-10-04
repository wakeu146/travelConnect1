<?php

namespace App\Http\Controllers;

use App\Enums\BookingStatus;
use App\Enums\ReviewStatus;
use App\Models\Agency;
use App\Models\Booking;
use App\Notifications\AgencyActivityNotification;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class ReviewController extends Controller
{
    public function store(Request $request, string $slug): RedirectResponse
    {
        abort_unless($request->user()->isTraveler(), 403);

        $agency = Agency::whereRaw('LOWER(REPLACE(company_name, " ", "-")) = ?', [mb_strtolower($slug)])
            ->firstOrFail();

        if ($agency->reviews()->where('user_id', $request->user()->id)->exists()) {
            return back()->withErrors(['review' => 'You have already submitted a review for this agency.']);
        }

        $data = $request->validate([
            'booking_id' => ['required', 'integer', Rule::exists('bookings', 'id')->where(fn ($query) => $query
                ->where('agency_id', $agency->id)
                ->where('user_id', $request->user()->id)
                ->where('status', BookingStatus::COMPLETED->value))],
            'rating' => ['required', 'integer', 'between:1,5'],
            'comment' => ['required', 'string', 'min:10', 'max:2000'],
        ]);

        $booking = Booking::query()->findOrFail($data['booking_id']);

        $agency->reviews()->create([
            'user_id' => $request->user()->id,
            'booking_id' => $booking->id,
            'rating' => $data['rating'],
            'comment' => $data['comment'],
            'status' => ReviewStatus::PENDING,
        ]);

        $agency->owner?->notify(new AgencyActivityNotification(
            __('messages.booking_notification_title'),
            __('messages.new_review_notification', ['name' => $request->user()->name]),
            'review',
            route('agency.reviews.index', ['lang' => app()->getLocale()])
        ));

        return back()->with('review_status', 'Your review was submitted for approval.');
    }
}
