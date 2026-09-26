<?php

namespace App\Http\Controllers;

use App\Enums\BookingStatus;
use App\Models\Booking;
use App\Services\BookingService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class AgencyBookingController extends Controller
{
    public function index(Request $request): View
    {
        $agency = auth()->user()->agency;
        $agencyBookings = Booking::query()->where('agency_id', $agency->id);
        $statusCounts = (clone $agencyBookings)
            ->selectRaw('status, COUNT(*) as aggregate')
            ->groupBy('status')
            ->pluck('aggregate', 'status');

        $query = Booking::query()
            ->with(['package', 'traveler', 'availability'])
            ->where('agency_id', $agency->id);

        if ($search = trim((string) $request->query('search', ''))) {
            $query->where(function ($bookingQuery) use ($search): void {
                $bookingQuery->where('booking_reference', 'like', "%{$search}%")
                    ->orWhereHas('traveler', fn ($travelerQuery) => $travelerQuery->where('name', 'like', "%{$search}%"))
                    ->orWhereHas('package', fn ($packageQuery) => $packageQuery->where('title', 'like', "%{$search}%"));
            });
        }

        if ($status = $request->query('status')) {
            $query->where('status', $status);
        }

        if ($packageId = $request->query('package_id')) {
            $query->where('package_id', $packageId);
        }

        if ($availabilityId = $request->query('availability_id')) {
            $query->where('package_availability_id', $availabilityId);
        }

        if ($date = $request->query('date')) {
            $query->whereDate('created_at', $date);
        }

        $sort = $request->query('sort', 'newest');
        $sorts = [
            'newest' => ['created_at', 'desc'],
            'oldest' => ['created_at', 'asc'],
            'amount_high' => ['total_amount', 'desc'],
            'amount_low' => ['total_amount', 'asc'],
            'travelers_high' => ['traveler_count', 'desc'],
        ];
        [$sortColumn, $sortDirection] = $sorts[$sort] ?? $sorts['newest'];
        $bookings = $query->orderBy($sortColumn, $sortDirection)->paginate(20)->appends($request->query());

        return view('agencies.bookings.index', [
            'agency' => $agency,
            'bookings' => $bookings,
            'packages' => $agency->packages()->orderBy('title')->get(),
            'search' => $search ?? '',
            'status' => $status ?? '',
            'packageId' => $packageId ?? '',
            'availabilityId' => $availabilityId ?? '',
            'date' => $date ?? '',
            'sort' => isset($sorts[$sort]) ? $sort : 'newest',
            'statusCounts' => $statusCounts,
        ]);
    }

    public function show(Booking $booking): View
    {
        $this->authorize('viewAgency', $booking);

        return view('agencies.bookings.show', [
            'agency' => auth()->user()->agency,
            'booking' => $booking->load(['package', 'traveler', 'availability']),
        ]);
    }

    public function confirm(Booking $booking, BookingService $bookingService): RedirectResponse
    {
        $this->authorize('update', $booking);

        if ($booking->status !== BookingStatus::PENDING) {
            return back()->withErrors(['status' => __('messages.only_pending_confirm')]);
        }

        $bookingService->transitionStatus($booking, BookingStatus::CONFIRMED);

        return redirect()->route('agency.bookings.show', ['booking' => $booking->id])->with('status', __('messages.booking_confirmed'));
    }

    public function reject(Booking $booking, BookingService $bookingService): RedirectResponse
    {
        $this->authorize('update', $booking);

        if ($booking->status !== BookingStatus::PENDING) {
            return back()->withErrors(['status' => __('messages.only_pending_reject')]);
        }

        $bookingService->transitionStatus($booking, BookingStatus::REJECTED);

        return redirect()->route('agency.bookings.show', ['booking' => $booking->id])->with('status', __('messages.booking_rejected'));
    }

    public function cancel(Booking $booking, BookingService $bookingService): RedirectResponse
    {
        $this->authorize('update', $booking);

        if (! in_array($booking->status, [BookingStatus::PENDING, BookingStatus::CONFIRMED], true)) {
            return back()->withErrors(['status' => __('messages.booking_cannot_cancel')]);
        }

        $bookingService->transitionStatus($booking, BookingStatus::CANCELLED, __('messages.cancelled_by_agency'));

        return redirect()->route('agency.bookings.show', ['booking' => $booking->id])->with('status', __('messages.booking_cancelled'));
    }

    public function complete(Booking $booking, BookingService $bookingService): RedirectResponse
    {
        $this->authorize('update', $booking);

        if ($booking->status !== BookingStatus::CONFIRMED) {
            return back()->withErrors(['status' => __('messages.only_confirmed_complete')]);
        }

        $bookingService->transitionStatus($booking, BookingStatus::COMPLETED);

        return redirect()->route('agency.bookings.show', ['booking' => $booking->id])->with('status', __('messages.booking_completed'));
    }
}
