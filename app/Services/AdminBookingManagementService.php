<?php

namespace App\Services;

use App\Enums\BookingStatus;
use App\Models\Booking;
use App\Models\BookingStatusHistory;
use App\Models\PackageAvailability;
use App\Models\TravelerIssue;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Validation\ValidationException;
use RuntimeException;

class AdminBookingManagementService
{
    public function __construct(private readonly BookingService $bookingService) {}

    /**
     * @param  array<string, mixed>  $filters
     */
    public function listing(string $page, array $filters): LengthAwarePaginator
    {
        $query = $this->query($page, $filters);
        $sort = $filters['sort'] ?? 'newest';
        $direction = $filters['direction'] ?? 'desc';

        match ($sort) {
            'departure' => $query->orderBy(
                PackageAvailability::query()
                    ->select('departure_date')
                    ->whereColumn('package_availability.id', 'bookings.package_availability_id'),
                $direction,
            ),
            'amount' => $query->orderBy('total_amount', $direction),
            'status' => $query->orderBy('status', $direction),
            default => $query->orderBy('created_at', $direction),
        };

        return $query->orderByDesc('id')->paginate(25)->withQueryString();
    }

    /**
     * @param  array<string, mixed>  $filters
     */
    public function query(string $page, array $filters): Builder
    {
        $query = Booking::query()
            ->with([
                'traveler:id,name,email',
                'agency:id,company_name',
                'package:id,title,destination,duration_days',
                'customRequest:id,title,destination,travel_date',
                'customProposal:id,reference,price,currency',
                'availability:id,package_id,departure_date,capacity,status',
            ])
            ->withCount([
                'travelerIssues as booking_disputes_count' => fn (Builder $issueQuery) => $issueQuery
                    ->where('type', 'booking_dispute'),
            ]);

        $lockedStatuses = match ($page) {
            'pending_bookings' => [BookingStatus::PENDING],
            'confirmed_bookings' => [BookingStatus::CONFIRMED],
            'completed_bookings' => [BookingStatus::COMPLETED],
            'cancelled_rejected_bookings' => [BookingStatus::CANCELLED, BookingStatus::REJECTED],
            'expired_bookings' => [BookingStatus::EXPIRED],
            default => [],
        };

        if ($lockedStatuses !== []) {
            $query->whereIn('status', array_map(
                fn (BookingStatus $status): string => $status->value,
                $lockedStatuses,
            ));

            if ($page === 'cancelled_rejected_bookings' && isset($filters['status'])) {
                $query->where('status', $filters['status']);
            }
        } elseif (isset($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        if (isset($filters['payment_status'])) {
            $query->where('payment_status', $filters['payment_status']);
        }

        if (isset($filters['search']) && $filters['search'] !== '') {
            $term = '%'.addcslashes($filters['search'], '\\%_').'%';
            $query->where(function (Builder $searchQuery) use ($term): void {
                $searchQuery->where('booking_reference', 'like', $term)
                    ->orWhereHas('traveler', fn (Builder $travelerQuery) => $travelerQuery
                        ->where('name', 'like', $term)
                        ->orWhere('email', 'like', $term))
                    ->orWhereHas('agency', fn (Builder $agencyQuery) => $agencyQuery
                        ->where('company_name', 'like', $term))
                    ->orWhereHas('package', fn (Builder $packageQuery) => $packageQuery
                        ->where('title', 'like', $term))
                    ->orWhereHas('customRequest', fn (Builder $customRequestQuery) => $customRequestQuery
                        ->where('title', 'like', $term)
                        ->orWhere('destination', 'like', $term));
            });
        }

        foreach ([
            'created_from' => ['created_at', '>='],
            'created_to' => ['created_at', '<='],
        ] as $filter => [$column, $operator]) {
            if (isset($filters[$filter])) {
                $query->whereDate($column, $operator, $filters[$filter]);
            }
        }

        if (isset($filters['departure_from'])) {
            $query->whereHas('availability', fn (Builder $availabilityQuery) => $availabilityQuery
                ->whereDate('departure_date', '>=', $filters['departure_from']));
        }

        if (isset($filters['departure_to'])) {
            $query->whereHas('availability', fn (Builder $availabilityQuery) => $availabilityQuery
                ->whereDate('departure_date', '<=', $filters['departure_to']));
        }

        if (isset($filters['agency'])) {
            $query->whereHas('agency', fn (Builder $agencyQuery) => $agencyQuery
                ->where('company_name', 'like', '%'.addcslashes($filters['agency'], '\\%_').'%'));
        }

        if (isset($filters['package'])) {
            $query->whereHas('package', fn (Builder $packageQuery) => $packageQuery
                ->where('title', 'like', '%'.addcslashes($filters['package'], '\\%_').'%'));
        }

        if (isset($filters['currency'])) {
            $query->where('currency', $filters['currency']);
        }

        return $query;
    }

    /**
     * @return array<string, int>
     */
    public function summary(): array
    {
        $counts = Booking::query()
            ->select('status')
            ->selectRaw('COUNT(*) as aggregate')
            ->groupBy('status')
            ->pluck('aggregate', 'status');

        $summary = ['total' => (int) $counts->sum()];
        foreach (BookingStatus::cases() as $status) {
            $summary[$status->value] = (int) ($counts[$status->value] ?? 0);
        }

        $summary['open_disputes'] = TravelerIssue::query()
            ->where('type', 'booking_dispute')
            ->whereNotIn('status', ['resolved', 'dismissed'])
            ->count();

        return $summary;
    }

    public function transition(Booking $booking, User $actor, BookingStatus $status, ?string $reason): Booking
    {
        abort_unless($actor->isAdmin(), 403);

        if ($status === BookingStatus::COMPLETED) {
            $departureDate = $booking->booking_type === 'custom'
                ? $booking->customRequest?->travel_date
                : $booking->availability?->departure_date;
            if ($departureDate === null || $departureDate->isFuture()) {
                throw ValidationException::withMessages([
                    'status' => __('messages.admin_bookings.completion_requires_departure'),
                ]);
            }
        }

        try {
            return $this->bookingService->transitionStatus($booking, $status, $reason, $actor);
        } catch (RuntimeException $exception) {
            throw ValidationException::withMessages(['status' => $exception->getMessage()]);
        }
    }

    public function addNote(Booking $booking, User $actor, string $note): BookingStatusHistory
    {
        abort_unless($actor->isAdmin(), 403);

        return BookingStatusHistory::query()->create([
            'booking_id' => $booking->id,
            'actor_id' => $actor->id,
            'event_type' => 'admin_note',
            'from_status' => $booking->status->value,
            'to_status' => $booking->status->value,
            'reason' => $note,
        ]);
    }
}
