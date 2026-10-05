<?php

namespace App\Http\Controllers;

use App\Enums\BookingStatus;
use App\Enums\PaymentStatus;
use App\Models\Booking;
use App\Notifications\AgencyActivityNotification;
use App\Services\BookingService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class AgencyBookingController extends Controller
{
    public function index(Request $request): View
    {
        $agency = $request->user()->agency;
        $agencyBookings = $agency->bookings();
        $statusCounts = (clone $agencyBookings)
            ->selectRaw('status, COUNT(*) as aggregate')
            ->groupBy('status')
            ->pluck('aggregate', 'status');

        $upcomingReservations = (clone $agencyBookings)
            ->reservesCapacity()
            ->whereHas('availability', fn ($query) => $query->whereDate('departure_date', '>', today()->toDateString()));
        $upcomingConfirmedCount = (clone $upcomingReservations)
            ->where('status', BookingStatus::CONFIRMED->value)
            ->count();
        $upcomingTravelerCount = (clone $upcomingReservations)->sum('traveler_count');

        $sorts = [
            'newest' => ['created_at', 'desc'],
            'oldest' => ['created_at', 'asc'],
            'amount_high' => ['total_amount', 'desc'],
            'amount_low' => ['total_amount', 'asc'],
            'travelers_high' => ['traveler_count', 'desc'],
        ];

        $filters = $request->validate([
            'search' => ['nullable', 'string', 'max:120'],
            'status' => ['nullable', Rule::in(array_map(fn (BookingStatus $status): string => $status->value, BookingStatus::cases()))],
            'payment_status' => ['nullable', Rule::in(array_map(fn (PaymentStatus $status): string => $status->value, PaymentStatus::cases()))],
            'package_id' => ['nullable', 'integer'],
            'availability_id' => ['nullable', 'integer'],
            'traveler_id' => ['nullable', 'integer'],
            'date' => ['nullable', 'date_format:Y-m-d'],
            'created_from' => ['nullable', 'date_format:Y-m-d'],
            'created_to' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:created_from'],
            'departure_from' => ['nullable', 'date_format:Y-m-d'],
            'departure_to' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:departure_from'],
            'upcoming' => ['nullable', 'boolean'],
            'sort' => ['nullable', Rule::in(array_keys($sorts))],
        ]);

        $search = trim($filters['search'] ?? '');
        $status = $filters['status'] ?? '';
        $paymentStatus = $filters['payment_status'] ?? '';
        $packageId = $filters['package_id'] ?? '';
        $availabilityId = $filters['availability_id'] ?? '';
        $travelerId = $filters['traveler_id'] ?? '';
        $date = $filters['date'] ?? '';
        $createdFrom = $filters['created_from'] ?? '';
        $createdTo = $filters['created_to'] ?? '';
        $departureFrom = $filters['departure_from'] ?? '';
        $departureTo = $filters['departure_to'] ?? '';
        $upcoming = $request->boolean('upcoming');
        $sort = $filters['sort'] ?? 'newest';

        $query = (clone $agencyBookings)->with([
            'package:id,agency_id,title,destination,duration_days',
            'traveler:id,name,email,phone,profile_photo_path',
            'availability:id,package_id,departure_date,capacity,status',
        ]);

        if ($search !== '') {
            $query->where(function ($bookingQuery) use ($search): void {
                $bookingQuery->where('booking_reference', 'like', "%{$search}%")
                    ->orWhereHas('traveler', fn ($travelerQuery) => $travelerQuery
                        ->where('name', 'like', "%{$search}%")
                        ->orWhere('email', 'like', "%{$search}%")
                        ->orWhere('phone', 'like', "%{$search}%"))
                    ->orWhereHas('package', fn ($packageQuery) => $packageQuery
                        ->where('title', 'like', "%{$search}%")
                        ->orWhere('destination', 'like', "%{$search}%"));
            });
        }

        if ($status !== '') {
            $query->where('status', $status);
        }

        if ($paymentStatus !== '') {
            $query->where('payment_status', $paymentStatus);
        }

        if ($packageId !== '') {
            $query->where('package_id', $packageId);
        }

        if ($availabilityId !== '') {
            $query->where('package_availability_id', $availabilityId);
        }

        if ($travelerId !== '') {
            $query->where('user_id', $travelerId);
        }

        if ($date !== '') {
            $query->whereDate('created_at', $date);
        }

        if ($createdFrom !== '') {
            $query->whereDate('created_at', '>=', $createdFrom);
        }

        if ($createdTo !== '') {
            $query->whereDate('created_at', '<=', $createdTo);
        }

        if ($departureFrom !== '') {
            $query->whereHas('availability', fn ($availabilityQuery) => $availabilityQuery->whereDate('departure_date', '>=', $departureFrom));
        }

        if ($departureTo !== '') {
            $query->whereHas('availability', fn ($availabilityQuery) => $availabilityQuery->whereDate('departure_date', '<=', $departureTo));
        }

        if ($upcoming) {
            $query->reservesCapacity()
                ->whereHas('availability', fn ($availabilityQuery) => $availabilityQuery->whereDate('departure_date', '>', today()->toDateString()));
        }

        [$sortColumn, $sortDirection] = $sorts[$sort];
        $bookings = $query->orderBy($sortColumn, $sortDirection)->orderByDesc('id')->paginate(20)->withQueryString();

        return view('agencies.bookings.index', [
            'agency' => $agency,
            'bookings' => $bookings,
            'packages' => $agency->packages()->orderBy('title')->get(['id', 'title']),
            'search' => $search,
            'status' => $status,
            'paymentStatus' => $paymentStatus,
            'packageId' => $packageId,
            'availabilityId' => $availabilityId,
            'travelerId' => $travelerId,
            'date' => $date,
            'createdFrom' => $createdFrom,
            'createdTo' => $createdTo,
            'departureFrom' => $departureFrom,
            'departureTo' => $departureTo,
            'upcoming' => $upcoming,
            'sort' => $sort,
            'statusCounts' => $statusCounts,
            'totalBookingCount' => $statusCounts->sum(),
            'upcomingConfirmedCount' => $upcomingConfirmedCount,
            'upcomingTravelerCount' => (int) $upcomingTravelerCount,
            'cancelledRejectedCount' => (int) ($statusCounts[BookingStatus::CANCELLED->value] ?? 0) + (int) ($statusCounts[BookingStatus::REJECTED->value] ?? 0),
        ]);
    }

    public function show(Booking $booking, BookingService $bookingService): View
    {
        $this->authorize('viewAgency', $booking);
        $agency = request()->user()->agency;
        $booking->load(['package', 'traveler', 'availability']);
        $travelerPhotoPath = $booking->traveler?->profile_photo_path;
        $travelerPhotoUrl = $travelerPhotoPath && Storage::disk('public')->exists($travelerPhotoPath)
            ? asset('storage/'.$travelerPhotoPath)
            : null;
        $capacitySnapshot = $booking->availability
            ? $bookingService->capacitySnapshots([$booking->availability])->get($booking->availability->id)
            : null;
        $otherTravelerBookings = $booking->traveler
            ? $agency->bookings()
                ->where('user_id', $booking->traveler->id)
                ->whereKeyNot($booking->id)
                ->with([
                    'package:id,agency_id,title,destination',
                    'availability:id,package_id,departure_date',
                ])
                ->latest('created_at')
                ->limit(5)
                ->get()
            : collect();

        return view('agencies.bookings.show', [
            'agency' => $agency,
            'booking' => $booking,
            'travelerPhotoUrl' => $travelerPhotoUrl,
            'capacitySnapshot' => $capacitySnapshot,
            'otherTravelerBookings' => $otherTravelerBookings,
        ]);
    }

    public function confirm(Booking $booking, BookingService $bookingService): RedirectResponse
    {
        $this->authorize('update', $booking);

        if ($this->expirePendingBookingIfOverdue($booking, $bookingService)) {
            return redirect()->route('agency.bookings.show', ['booking' => $booking->id])->withErrors(['status' => __('messages.pending_booking_expired')]);
        }

        if ($booking->status !== BookingStatus::PENDING) {
            return back()->withErrors(['status' => __('messages.only_pending_confirm')]);
        }

        $bookingService->transitionStatus($booking, BookingStatus::CONFIRMED);
        $this->notifyTraveler($booking, 'booking_confirmed_notification');

        return redirect()->route('agency.bookings.show', ['booking' => $booking->id])->with('status', __('messages.booking_confirmed'));
    }

    public function reject(Booking $booking, BookingService $bookingService): RedirectResponse
    {
        $this->authorize('update', $booking);

        if ($this->expirePendingBookingIfOverdue($booking, $bookingService)) {
            return redirect()->route('agency.bookings.show', ['booking' => $booking->id])->withErrors(['status' => __('messages.pending_booking_expired')]);
        }

        if ($booking->status !== BookingStatus::PENDING) {
            return back()->withErrors(['status' => __('messages.only_pending_reject')]);
        }

        $bookingService->transitionStatus($booking, BookingStatus::REJECTED);
        $this->notifyTraveler($booking, 'booking_rejected_notification');

        return redirect()->route('agency.bookings.show', ['booking' => $booking->id])->with('status', __('messages.booking_rejected'));
    }

    public function cancel(Booking $booking, BookingService $bookingService): RedirectResponse
    {
        $this->authorize('update', $booking);

        if ($this->expirePendingBookingIfOverdue($booking, $bookingService)) {
            return redirect()->route('agency.bookings.show', ['booking' => $booking->id])->withErrors(['status' => __('messages.pending_booking_expired')]);
        }

        if (! in_array($booking->status, [BookingStatus::PENDING, BookingStatus::CONFIRMED], true)) {
            return back()->withErrors(['status' => __('messages.booking_cannot_cancel')]);
        }

        $bookingService->transitionStatus($booking, BookingStatus::CANCELLED, __('messages.cancelled_by_agency'));
        $this->notifyTraveler($booking, 'booking_cancelled_notification');

        return redirect()->route('agency.bookings.show', ['booking' => $booking->id])->with('status', __('messages.booking_cancelled'));
    }

    public function complete(Booking $booking, BookingService $bookingService): RedirectResponse
    {
        $this->authorize('update', $booking);

        if ($booking->status !== BookingStatus::CONFIRMED) {
            return back()->withErrors(['status' => __('messages.only_confirmed_complete')]);
        }

        $bookingService->transitionStatus($booking, BookingStatus::COMPLETED);
        $this->notifyTraveler($booking, 'booking_completed_notification');

        return redirect()->route('agency.bookings.show', ['booking' => $booking->id])->with('status', __('messages.booking_completed'));
    }

    private function notifyTraveler(Booking $booking, string $messageKey): void
    {
        $booking->loadMissing('traveler');
        $booking->traveler?->notify(new AgencyActivityNotification(
            __('messages.booking_notification_title'),
            __('messages.'.$messageKey, ['reference' => $booking->booking_reference]),
            'booking',
            route('bookings.show', ['booking' => $booking->id, 'lang' => app()->getLocale()])
        ));
    }

    private function expirePendingBookingIfOverdue(Booking $booking, BookingService $bookingService): bool
    {
        if ($booking->status !== BookingStatus::PENDING || ! $booking->expires_at || ! $booking->expires_at->isPast()) {
            return false;
        }

        $bookingService->transitionStatus($booking, BookingStatus::EXPIRED);
        $this->notifyTraveler($booking, 'booking_expired_notification');

        return true;
    }
}
