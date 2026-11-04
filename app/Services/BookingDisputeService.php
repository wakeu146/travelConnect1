<?php

namespace App\Services;

use App\Enums\UserRole;
use App\Models\Booking;
use App\Models\BookingDisputeActivity;
use App\Models\TravelerIssue;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class BookingDisputeService
{
    /**
     * @param  array<string, mixed>  $filters
     */
    public function listing(array $filters): LengthAwarePaginator
    {
        $query = $this->query($filters);

        return $query->latest('created_at')->latest('id')->paginate(25)->withQueryString();
    }

    /**
     * @param  array<string, mixed>  $filters
     */
    public function query(array $filters): Builder
    {
        $query = TravelerIssue::query()
            ->where('type', 'booking_dispute')
            ->whereNotNull('booking_id')
            ->with([
                'booking:id,booking_reference,user_id,agency_id,package_id,package_availability_id,status,payment_status,total_amount,currency,created_at',
                'booking.traveler:id,name,email',
                'booking.agency:id,company_name',
                'booking.package:id,title',
                'assignedAdmin:id,name,email',
                'openedByAdmin:id,name',
            ]);

        if (isset($filters['search']) && $filters['search'] !== '') {
            $term = '%'.addcslashes($filters['search'], '\\%_').'%';
            $query->where(function (Builder $searchQuery) use ($term): void {
                $searchQuery->where('subject', 'like', $term)
                    ->orWhereHas('booking', fn (Builder $bookingQuery) => $bookingQuery
                        ->where('booking_reference', 'like', $term)
                        ->orWhereHas('traveler', fn (Builder $travelerQuery) => $travelerQuery
                            ->where('name', 'like', $term)
                            ->orWhere('email', 'like', $term))
                        ->orWhereHas('agency', fn (Builder $agencyQuery) => $agencyQuery
                            ->where('company_name', 'like', $term)));
            });
        }

        foreach (['status', 'priority', 'category', 'assigned_admin_id'] as $filter) {
            if (isset($filters[$filter]) && $filters[$filter] !== '') {
                $query->where($filter, $filters[$filter]);
            }
        }

        if (isset($filters['created_from'])) {
            $query->whereDate('created_at', '>=', $filters['created_from']);
        }

        if (isset($filters['created_to'])) {
            $query->whereDate('created_at', '<=', $filters['created_to']);
        }

        return $query;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function openForBooking(Booking $booking, User $actor, array $data): TravelerIssue
    {
        abort_unless($actor->isAdmin(), 403);

        $traveler = $booking->traveler;
        if ($traveler === null) {
            throw ValidationException::withMessages([
                'booking' => __('messages.admin_disputes.booking_traveler_unavailable'),
            ]);
        }

        return DB::transaction(function () use ($booking, $actor, $traveler, $data): TravelerIssue {
            $issue = $traveler->travelerIssues()->create([
                'booking_id' => $booking->id,
                'opened_by_admin_id' => $actor->id,
                'type' => 'booking_dispute',
                'category' => $data['category'],
                'subject' => $data['subject'],
                'description' => $data['reason'],
                'status' => 'open',
                'priority' => $data['priority'],
            ]);

            $this->recordActivity($issue, $actor, 'opened', note: $data['reason'], metadata: [
                'source' => 'administrator',
            ]);

            return $issue;
        });
    }

    public function assign(TravelerIssue $issue, User $actor, ?User $assignee): TravelerIssue
    {
        abort_unless($actor->isAdmin(), 403);
        if ($assignee !== null && ! $assignee->isAdmin()) {
            throw ValidationException::withMessages([
                'assigned_admin_id' => __('messages.admin_disputes.invalid_assignee'),
            ]);
        }

        return DB::transaction(function () use ($issue, $actor, $assignee): TravelerIssue {
            $issue = TravelerIssue::query()->lockForUpdate()->findOrFail($issue->id);
            $previousAssigneeId = $issue->assigned_admin_id;
            $issue->update(['assigned_admin_id' => $assignee?->id]);
            $this->recordActivity($issue, $actor, 'assigned', metadata: [
                'previous_assignee_id' => $previousAssigneeId,
                'assigned_admin_id' => $assignee?->id,
            ]);

            return $issue->refresh();
        });
    }

    public function transition(TravelerIssue $issue, User $actor, string $status, string $reason): TravelerIssue
    {
        abort_unless($actor->isAdmin(), 403);

        return DB::transaction(function () use ($issue, $actor, $status, $reason): TravelerIssue {
            $issue = TravelerIssue::query()->lockForUpdate()->findOrFail($issue->id);
            $currentStatus = $issue->status;
            $allowedTransitions = match ($currentStatus) {
                'open' => ['in_review', 'dismissed'],
                'in_review' => ['awaiting_information', 'resolved', 'dismissed'],
                'awaiting_information' => ['in_review', 'resolved', 'dismissed'],
                'resolved' => ['in_review'],
                default => [],
            };

            if (! in_array($status, $allowedTransitions, true)) {
                throw ValidationException::withMessages([
                    'status' => __('messages.admin_disputes.invalid_transition'),
                ]);
            }

            $attributes = ['status' => $status];
            if (in_array($status, ['resolved', 'dismissed'], true)) {
                $attributes['resolution_reason'] = $reason;
                $attributes['resolved_at'] = now();
            } elseif ($currentStatus === 'resolved') {
                $attributes['resolution_reason'] = null;
                $attributes['resolved_at'] = null;
            }

            $issue->update($attributes);
            $this->recordActivity($issue, $actor, 'status_changed', $currentStatus, $status, $reason);

            return $issue->refresh();
        });
    }

    public function addInternalNote(TravelerIssue $issue, User $actor, string $note): BookingDisputeActivity
    {
        abort_unless($actor->isAdmin(), 403);

        return $this->recordActivity($issue, $actor, 'internal_note', note: $note);
    }

    /**
     * @param  array<string, mixed>|null  $metadata
     */
    private function recordActivity(
        TravelerIssue $issue,
        User $actor,
        string $eventType,
        ?string $fromStatus = null,
        ?string $toStatus = null,
        ?string $note = null,
        ?array $metadata = null,
    ): BookingDisputeActivity {
        return $issue->activities()->create([
            'actor_id' => $actor->id,
            'event_type' => $eventType,
            'from_status' => $fromStatus,
            'to_status' => $toStatus,
            'note' => $note,
            'metadata' => $metadata,
        ]);
    }

    /**
     * @return Collection<int, User>
     */
    public function administrators(): Collection
    {
        return User::query()
            ->where('role', UserRole::ADMIN)
            ->orderBy('name')
            ->get(['id', 'name', 'email']);
    }
}
