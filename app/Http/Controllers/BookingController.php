<?php

namespace App\Http\Controllers;

use App\Models\Booking;
use App\Models\Package;
use App\Models\PackageAvailability;
use App\Services\BookingService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class BookingController extends Controller
{
    public function packageShow(Package $package): View
    {
        $package->load(['agency', 'images', 'availabilities' => fn ($query) => $query->where('status', 'active')->orderBy('departure_date')]);

        return view('packages.show', [
            'package' => $package,
            'activeAvailabilities' => $package->availabilities->filter(fn ($availability) => $availability->status->value === 'active'),
        ]);
    }

    public function create(Package $package): View
    {
        $this->authorize('create', Booking::class);

        $package->load(['agency', 'images', 'availabilities' => fn ($query) => $query->where('status', 'active')->orderBy('departure_date')]);

        return view('bookings.create', [
            'package' => $package,
            'availabilities' => $package->availabilities,
            'unitPrice' => (float) $package->price_from,
        ]);
    }

    public function store(Request $request, Package $package, BookingService $bookingService): RedirectResponse
    {
        $this->authorize('create', Booking::class);

        $data = $request->validate([
            'package_availability_id' => ['required', 'exists:package_availability,id'],
            'traveler_count' => ['required', 'integer', 'min:1'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ]);

        $availability = PackageAvailability::with('package')->findOrFail($data['package_availability_id']);

        if ($availability->package_id !== $package->id) {
            return back()->withErrors(['package_availability_id' => 'The selected departure does not belong to this package.']);
        }

        try {
            $booking = $bookingService->createBooking(auth()->user(), $package, $availability, $data['traveler_count']);
            $booking->update(['notes' => $data['notes'] ?? null]);

            return redirect()->route('bookings.confirmation', ['booking' => $booking->id])->with('status', 'Booking created. Your reference is '.$booking->booking_reference);
        } catch (\RuntimeException|\InvalidArgumentException $e) {
            return back()->withErrors(['traveler_count' => $e->getMessage()])->withInput();
        }
    }

    public function confirmation(Booking $booking): View
    {
        $this->authorize('view', $booking);

        return view('bookings.confirmation', [
            'booking' => $booking->load(['package', 'availability', 'traveler', 'agency']),
        ]);
    }

    public function cancel(Request $request, Booking $booking): RedirectResponse
    {
        $this->authorize('cancel', $booking);

        $request->validate([
            'reason' => ['nullable', 'string', 'max:255'],
        ]);

        if (! in_array($booking->status, [\App\Enums\BookingStatus::PENDING, \App\Enums\BookingStatus::CONFIRMED], true)) {
            return back()->withErrors(['status' => 'This booking cannot be cancelled.']);
        }

        $bookingService = app(BookingService::class);
        $bookingService->transitionStatus($booking, \App\Enums\BookingStatus::CANCELLED);
        $booking->update(['cancellation_reason' => $request->input('reason', 'Cancelled by traveler')]);

        return redirect()->route('bookings.show', ['booking' => $booking->id])->with('status', 'Booking cancelled.');
    }

    public function show(Booking $booking): View
    {
        $this->authorize('view', $booking);

        return view('bookings.show', [
            'booking' => $booking->load(['package', 'availability', 'traveler', 'agency']),
        ]);
    }
}
