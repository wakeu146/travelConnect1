<?php

namespace App\Http\Controllers;

use App\Models\Agency;
use App\Models\Inquiry;
use App\Notifications\AgencyActivityNotification;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class InquiryController extends Controller
{
    public function store(Request $request, string $slug): RedirectResponse
    {
        abort_unless($request->user()?->isTraveler(), 403);

        $agency = Agency::whereRaw('LOWER(REPLACE(company_name, " ", "-")) = ?', [mb_strtolower($slug)])
            ->firstOrFail();

        $data = $request->validate([
            'subject' => ['required', 'string', 'max:255'],
            'message' => ['required', 'string', 'min:10', 'max:2000'],
        ]);

        $inquiry = Inquiry::create([
            'agency_id' => $agency->id,
            'user_id' => $request->user()->id,
            'subject' => $data['subject'],
            'message' => $data['message'],
            'status' => 'open',
        ]);

        $agency->owner?->notify(new AgencyActivityNotification(
            'New inquiry received',
            $request->user()->name.' sent a message about '.$inquiry->subject.'.',
            'inquiry',
            route('agency.dashboard', ['lang' => app()->getLocale()])
        ));

        return back()->with('inquiry_status', 'Your inquiry was sent. The agency will reply soon.');
    }
}
