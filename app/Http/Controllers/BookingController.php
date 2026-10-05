<?php

namespace App\Http\Controllers;

use App\Enums\BookingStatus;
use App\Enums\PackageAvailabilityStatus;
use App\Enums\PackageStatus;
use App\Enums\PaymentTransactionStatus;
use App\Enums\PaymentTransactionType;
use App\Models\Agency;
use App\Models\Booking;
use App\Models\Country;
use App\Models\Package;
use App\Models\PackageAvailability;
use App\Models\User;
use App\Notifications\AgencyActivityNotification;
use App\Services\BookingService;
use App\Services\PaymentSettlementService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\View\View;

class BookingController extends Controller
{
    public function index(Request $request): View
    {
        $this->authorize('viewAny', Booking::class);

        /** @var User $traveler */
        $traveler = $request->user();
        $bookings = $traveler->bookings()
            ->with([
                'agency:id,company_name',
                'package:id,title,destination,duration_days',
                'availability:id,departure_date',
            ])
            ->latest()
            ->paginate(10);
        $bookings->withQueryString();

        $filters = $request->validate([
            'country_id' => ['nullable', 'integer', 'exists:countries,id'],
            'agency_id' => ['nullable', 'integer'],
            'package_id' => ['nullable', 'integer'],
        ]);
        $selectedCountryId = $filters['country_id'] ?? null;
        $selectedAgencyId = $filters['agency_id'] ?? null;
        $selectedPackageId = $filters['package_id'] ?? null;
        $countries = Country::query()->orderBy('name')->get(['id', 'name']);
        $selectedCountry = $selectedCountryId !== null
            ? $countries->firstWhere('id', (int) $selectedCountryId)
            : null;
        $bookableAgencies = collect();

        if ($selectedCountry) {
            $availableDepartureQuery = static function (Builder|Relation $query): void {
                $query->where('status', PackageAvailabilityStatus::ACTIVE->value)
                    ->whereDate('departure_date', '>', today()->toDateString());
            };
            $bookablePackageQuery = static function (Builder|Relation $query) use ($selectedCountry, $availableDepartureQuery): void {
                $query->where('status', PackageStatus::PUBLISHED->value)
                    ->where('destination', $selectedCountry->name)
                    ->whereHas('availabilities', $availableDepartureQuery);
            };

            $bookableAgencies = Agency::verified()
                ->whereHas('countries', fn (Builder $query): Builder => $query->whereKey($selectedCountry->id))
                ->whereHas('packages', $bookablePackageQuery)
                ->with(['packages' => function (Relation $query) use ($bookablePackageQuery, $availableDepartureQuery): void {
                    $bookablePackageQuery($query);
                    $query->with(['images', 'availabilities' => $availableDepartureQuery]);
                }])
                ->orderBy('company_name')
                ->get();
        }

        $selectedAgency = $selectedAgencyId ? $bookableAgencies->firstWhere('id', (int) $selectedAgencyId) : null;
        $selectedPackage = $selectedAgency && $selectedPackageId
            ? $selectedAgency->packages->firstWhere('id', (int) $selectedPackageId)
            : null;

        return view('bookings.index', [
            'bookings' => $bookings,
            'countries' => $countries,
            'bookableAgencies' => $bookableAgencies,
            'selectedCountry' => $selectedCountry,
            'selectedAgency' => $selectedAgency,
            'selectedPackage' => $selectedPackage,
            'selectedAgencyId' => $selectedAgencyId,
            'selectedPackageId' => $selectedPackageId,
        ]);
    }

    public function storeFromAccount(Request $request, BookingService $bookingService, PaymentSettlementService $paymentSettlementService): RedirectResponse
    {
        $this->authorize('create', Booking::class);

        $data = $request->validate([
            'country_id' => ['required', 'integer', 'exists:countries,id'],
            'agency_id' => ['required', 'exists:agencies,id'],
            'package_id' => ['required', 'exists:packages,id'],
            'package_availability_id' => ['required', 'exists:package_availability,id'],
            'traveler_count' => ['required', 'integer', 'min:1'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ]);

        $country = Country::query()->findOrFail($data['country_id']);
        $agency = Agency::verified()
            ->whereKey($data['agency_id'])
            ->whereHas('countries', fn (Builder $query): Builder => $query->whereKey($country->id))
            ->firstOrFail();
        $package = $agency->packages()
            ->whereKey($data['package_id'])
            ->where('status', PackageStatus::PUBLISHED->value)
            ->where('destination', $country->name)
            ->firstOrFail();
        $package->loadMissing(['availabilities']);
        $availability = $package->availabilities()
            ->whereKey($data['package_availability_id'])
            ->where('status', PackageAvailabilityStatus::ACTIVE->value)
            ->whereDate('departure_date', '>', today()->toDateString())
            ->firstOrFail();

        try {
            $booking = $bookingService->createBooking($request->user(), $package, $availability, $data['traveler_count']);
            $booking->update(['notes' => $data['notes'] ?? null]);

            $paymentSettlementService->record(
                $booking,
                'travelconnect-manual-checkout',
                PaymentTransactionType::CHARGE,
                PaymentTransactionStatus::SUCCEEDED,
                (string) $booking->total_amount,
                $booking->currency,
                'manual-'.Str::slug($booking->booking_reference).'-'.time(),
                'manual_checkout'
            );

            $booking->loadMissing(['agency.owner']);
            $booking->agency?->owner?->notify(new AgencyActivityNotification(
                __('messages.booking_notification_title'),
                __('messages.new_booking_notification', ['name' => $request->user()->name, 'reference' => $booking->booking_reference]),
                'booking',
                route('agency.bookings.show', ['booking' => $booking->id, 'lang' => app()->getLocale()])
            ));

            return redirect()->route('account.bookings', ['lang' => app()->getLocale()])
                ->with('status', __('messages.booking_request_received'));
        } catch (\RuntimeException|\InvalidArgumentException $exception) {
            return back()->withErrors(['traveler_count' => $exception->getMessage()])->withInput();
        }
    }

    public function packageShow(Package $package): View
    {
        $package->load(['agency', 'images', 'availabilities' => fn ($query) => $query->where('status', 'active')->whereDate('departure_date', '>', today()->toDateString())->orderBy('departure_date')]);

        return view('packages.show', [
            'package' => $package,
            'activeAvailabilities' => $package->availabilities->filter(fn ($availability) => $availability->status->value === 'active'),
        ]);
    }

    public function create(Package $package): View
    {
        $this->authorize('create', Booking::class);

        $package->load(['agency', 'images', 'availabilities' => fn ($query) => $query->where('status', 'active')->whereDate('departure_date', '>', today()->toDateString())->orderBy('departure_date')]);

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
            'booking_modal_package' => ['nullable', 'integer', 'in:'.$package->id],
        ]);

        $availability = PackageAvailability::with('package')->findOrFail($data['package_availability_id']);

        if ($availability->package_id !== $package->id) {
            return back()->withErrors(['package_availability_id' => 'The selected departure does not belong to this package.']);
        }

        try {
            $booking = $bookingService->createBooking($request->user(), $package, $availability, $data['traveler_count']);
            $booking->update(['notes' => $data['notes'] ?? null]);
            $booking->loadMissing(['agency.owner']);
            $booking->agency?->owner?->notify(new AgencyActivityNotification(
                __('messages.booking_notification_title'),
                __('messages.new_booking_notification', ['name' => $request->user()->name, 'reference' => $booking->booking_reference]),
                'booking',
                route('agency.bookings.show', ['booking' => $booking->id, 'lang' => app()->getLocale()])
            ));

            if (isset($data['booking_modal_package'])) {
                $package->loadMissing('agency');

                return redirect()->route('agency.show', [
                    'slug' => Str::slug($package->agency->company_name),
                    'lang' => app()->getLocale(),
                ])->with('booking_success', [
                    'package_id' => $package->id,
                    'package_title' => $package->title,
                    'booking_reference' => $booking->booking_reference,
                ]);
            }

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

        if (! in_array($booking->status, [BookingStatus::PENDING, BookingStatus::CONFIRMED], true)) {
            return back()->withErrors(['status' => 'This booking cannot be cancelled.']);
        }

        $bookingService = app(BookingService::class);
        $bookingService->transitionStatus($booking, BookingStatus::CANCELLED);
        $booking->update(['cancellation_reason' => $request->input('reason', 'Cancelled by traveler')]);
        $booking->loadMissing(['agency.owner']);
        $booking->agency?->owner?->notify(new AgencyActivityNotification(
            __('messages.booking_notification_title'),
            __('messages.traveler_cancelled_booking_notification', ['name' => $request->user()->name, 'reference' => $booking->booking_reference]),
            'booking',
            route('agency.bookings.show', ['booking' => $booking->id, 'lang' => app()->getLocale()])
        ));

        return redirect()->route('bookings.show', ['booking' => $booking->id])->with('status', 'Booking cancelled.');
    }

    public function show(Booking $booking): View
    {
        $this->authorize('view', $booking);

        return view('bookings.show', [
            'booking' => $booking->load(['package', 'availability', 'traveler', 'agency', 'review']),
        ]);
    }
}
