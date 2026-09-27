<?php

namespace App\Http\Controllers;

use App\Enums\ReviewStatus;
use App\Models\Agency;
use App\Notifications\AgencyActivityNotification;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

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
            'rating' => ['required', 'integer', 'between:1,5'],
            'comment' => ['required', 'string', 'min:10', 'max:2000'],
        ]);

        $agency->reviews()->create([
            'user_id' => $request->user()->id,
            'rating' => $data['rating'],
            'comment' => $data['comment'],
            'status' => ReviewStatus::PENDING,
        ]);

        $agency->owner?->notify(new AgencyActivityNotification(
            'New traveler review',
            $request->user()->name.' sent a review for '.$agency->company_name.'.',
            'review',
            route('agency.reviews.index', ['lang' => app()->getLocale()])
        ));

        return back()->with('review_status', 'Your review was submitted for approval.');
    }
}
