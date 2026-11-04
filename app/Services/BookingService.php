<?php

namespace App\Services;

use App\Enums\BookingStatus;
use App\Enums\PackageAvailabilityStatus;
use App\Enums\PackageStatus;
use App\Enums\PaymentStatus;
use App\Models\Agency;
use App\Models\Booking;
use App\Models\BookingStatusHistory;
use App\Models\Package;
use App\Models\PackageAvailability;
use App\Models\User;
use Illuminate\Support\Collection as SupportCollection;
use Illuminate\Support\Facades\DB;

class BookingService
{
    public function createBooking(User $traveler, Package $package, PackageAvailability $availability, int $travelerCount): Booking
    {
        if (! $traveler->isTraveler()) {
            throw new \InvalidArgumentException('Only travelers can create bookings.');
        }

        if ($package->status !== PackageStatus::PUBLISHED) {
            throw new \RuntimeException('This package is not available for booking.');
        }

        if ($availability->package_id !== $package->id) {
            throw new \RuntimeException('The selected departure does not belong to the package.');
        }

        if ($travelerCount <= 0) {
            throw new \RuntimeException('Traveler count must be greater than zero.');
        }

        if ($availability->status !== PackageAvailabilityStatus::ACTIVE) {
            throw new \RuntimeException('This departure is not active.');
        }

        return DB::transaction(function () use ($traveler, $package, $availability, $travelerCount): Booking {
            $agencyIsActive = Agency::query()
                ->whereKey($package->agency_id)
                ->where('operating_status', 'active')
                ->lockForUpdate()
                ->exists();

            if (! $agencyIsActive) {
                throw new \RuntimeException(__('messages.agency_suspended_cannot_book'));
            }

            $availability->refresh();

            if ($availability->departure_date->lte(today())) {
                throw new \RuntimeException('This departure date has passed.');
            }

            $available = $this->lockAndGetAvailableCapacity($availability);

            if ($travelerCount > $available) {
                throw new \RuntimeException('There are not enough seats remaining for this booking.');
            }

            $unitPrice = (float) $package->price_from;
            $total = $unitPrice * $travelerCount;

            $booking = Booking::create([
                'agency_id' => $package->agency_id,
                'package_id' => $package->id,
                'package_availability_id' => $availability->id,
                'user_id' => $traveler->id,
                'booking_reference' => $this->generateReference(),
                'status' => BookingStatus::PENDING,
                'payment_status' => PaymentStatus::UNPAID,
                'traveler_count' => $travelerCount,
                'unit_price' => $unitPrice,
                'total_amount' => $total,
                'currency' => config('travelconnect.currency'),
                'expires_at' => now()->addMinutes((int) config('travelconnect.booking_pending_expiry_minutes', 60)),
            ]);

            return $booking;
        });
    }

    public function getAvailableCapacity(PackageAvailability $availability): int
    {
        return $this->capacitySnapshots([$availability])->get($availability->id)['remaining_capacity'];
    }

    public function capacitySnapshots(iterable $availabilities): SupportCollection
    {
        $availabilities = collect($availabilities);
        $ids = $availabilities->pluck('id')->unique()->values();

        $reservations = $ids->isEmpty()
            ? collect()
            : Booking::query()
                ->selectRaw('package_availability_id, COUNT(*) as active_booking_count, SUM(traveler_count) as reserved_travelers')
                ->selectRaw('SUM(CASE WHEN status = ? THEN traveler_count ELSE 0 END) as pending_travelers', [BookingStatus::PENDING->value])
                ->selectRaw('SUM(CASE WHEN status = ? THEN traveler_count ELSE 0 END) as confirmed_travelers', [BookingStatus::CONFIRMED->value])
                ->whereIn('package_availability_id', $ids)
                ->reservesCapacity()
                ->groupBy('package_availability_id')
                ->get()
                ->keyBy('package_availability_id');
        $completedReservations = $ids->isEmpty()
            ? collect()
            : Booking::query()
                ->selectRaw('package_availability_id, SUM(traveler_count) as completed_travelers')
                ->whereIn('package_availability_id', $ids)
                ->where('status', BookingStatus::COMPLETED->value)
                ->groupBy('package_availability_id')
                ->get()
                ->keyBy('package_availability_id');

        return $availabilities->mapWithKeys(function (PackageAvailability $availability) use ($reservations, $completedReservations): array {
            $capacity = max(0, (int) $availability->capacity);
            $reservation = $reservations->get($availability->id);
            $completedReservation = $completedReservations->get($availability->id);
            $reserved = min($capacity, (int) ($reservation->reserved_travelers ?? 0));
            $remaining = max(0, $capacity - $reserved);

            return [$availability->id => [
                'capacity' => $capacity,
                'booking_count' => (int) ($reservation->active_booking_count ?? 0),
                'reserved_travelers' => $reserved,
                'completed_travelers' => (int) ($completedReservation->completed_travelers ?? 0),
                'pending_travelers' => (int) ($reservation->pending_travelers ?? 0),
                'confirmed_travelers' => (int) ($reservation->confirmed_travelers ?? 0),
                'remaining_capacity' => $remaining,
                'occupancy_percent' => $capacity > 0 ? (int) round(($reserved / $capacity) * 100) : 0,
                'sold_out' => $capacity > 0 && $remaining === 0,
                'near_capacity' => $remaining > 0 && $remaining <= 5,
            ]];
        });
    }

    public function expirePendingBookings(): int
    {
        $expiredBookings = Booking::query()
            ->where('status', BookingStatus::PENDING)
            ->whereNotNull('expires_at')
            ->where('expires_at', '<=', now())
            ->get();

        foreach ($expiredBookings as $booking) {
            $this->transitionStatus($booking, BookingStatus::EXPIRED);
        }

        return $expiredBookings->count();
    }

    public function expirePastDepartures(): int
    {
        return PackageAvailability::query()
            ->where('status', PackageAvailabilityStatus::ACTIVE->value)
            ->whereDate('departure_date', '<=', today()->toDateString())
            ->update(['status' => PackageAvailabilityStatus::EXPIRED->value]);
    }

    public function transitionStatus(
        Booking $booking,
        BookingStatus $newStatus,
        ?string $reason = null,
        ?User $actor = null,
    ): Booking {
        return DB::transaction(function () use ($booking, $newStatus, $reason, $actor): Booking {
            $booking = Booking::query()->lockForUpdate()->findOrFail($booking->id);
            $currentStatus = $booking->status;
            $allowedTransitions = match ($currentStatus) {
                BookingStatus::PENDING => [
                    BookingStatus::CONFIRMED,
                    BookingStatus::REJECTED,
                    BookingStatus::CANCELLED,
                    BookingStatus::EXPIRED,
                ],
                BookingStatus::CONFIRMED => [
                    BookingStatus::CANCELLED,
                    BookingStatus::COMPLETED,
                ],
                default => [],
            };

            if (! in_array($newStatus, $allowedTransitions, true)) {
                throw new \RuntimeException(sprintf(
                    'Invalid booking status transition from %s to %s.',
                    $currentStatus->value,
                    $newStatus->value,
                ));
            }

            $payload = ['status' => $newStatus->value];

            switch ($newStatus) {
                case BookingStatus::CONFIRMED:
                    $this->assertCanConfirm($booking);
                    $payload['confirmed_at'] = now();
                    break;

                case BookingStatus::REJECTED:
                    $payload['rejected_at'] = now();
                    break;

                case BookingStatus::CANCELLED:
                    $payload['cancelled_at'] = now();
                    $payload['cancellation_reason'] = $reason ?? 'Cancelled by booking lifecycle';
                    break;

                case BookingStatus::COMPLETED:
                    $payload['completed_at'] = now();
                    break;

                case BookingStatus::EXPIRED:
                    $payload['expires_at'] = $booking->expires_at ?? now();
                    break;
            }

            $booking->update($payload);
            BookingStatusHistory::query()->create([
                'booking_id' => $booking->id,
                'actor_id' => $actor?->id,
                'event_type' => 'status_transition',
                'from_status' => $currentStatus->value,
                'to_status' => $newStatus->value,
                'reason' => $reason,
            ]);

            return $booking->refresh();
        });
    }

    private function assertCanConfirm(Booking $booking): void
    {
        if ($booking->expires_at?->isPast()) {
            throw new \RuntimeException('This pending booking has expired.');
        }

        $agencyIsActive = Agency::query()
            ->whereKey($booking->agency_id)
            ->where('operating_status', 'active')
            ->lockForUpdate()
            ->exists();

        if ($booking->booking_type === 'custom') {
            if (! $agencyIsActive || ! $booking->customRequest || ! $booking->customProposal) {
                throw new \RuntimeException('This custom booking is no longer available for confirmation.');
            }

            return;
        }

        $package = Package::query()->findOrFail($booking->package_id);

        if ($package->status !== PackageStatus::PUBLISHED || ! $agencyIsActive) {
            throw new \RuntimeException('This package is no longer available for confirmation.');
        }

        $availability = PackageAvailability::query()
            ->whereKey($booking->package_availability_id)
            ->lockForUpdate()
            ->firstOrFail();

        if (
            $availability->status !== PackageAvailabilityStatus::ACTIVE
            || $availability->departure_date->lte(today())
        ) {
            throw new \RuntimeException('This departure is no longer available.');
        }

        $reservedTravelers = (int) Booking::query()
            ->where('package_availability_id', $availability->id)
            ->whereKeyNot($booking->id)
            ->reservesCapacity()
            ->sum('traveler_count');

        if ($reservedTravelers + $booking->traveler_count > $availability->capacity) {
            throw new \RuntimeException('There are not enough seats remaining for this booking.');
        }
    }

    protected function lockAndGetAvailableCapacity(PackageAvailability $availability): int
    {
        $availability = PackageAvailability::query()->whereKey($availability->id)->lockForUpdate()->firstOrFail();

        return $this->getAvailableCapacity($availability);
    }

    public function generateReference(): string
    {
        $prefix = config('travelconnect.booking_reference_prefix', 'TC');
        $year = now()->format('Y');

        do {
            $sequence = random_int(100000, 999999);
            $reference = sprintf('%s-%s-%06d', $prefix, $year, $sequence);
        } while (Booking::query()->where('booking_reference', $reference)->exists());

        return $reference;
    }

    public function releaseInventoryForBooking(Booking $booking): void
    {
        if ($booking->status !== BookingStatus::CANCELLED && $booking->status !== BookingStatus::REJECTED) {
            return;
        }

        $booking->update(['cancelled_at' => now(), 'rejected_at' => now()]);
    }
}
