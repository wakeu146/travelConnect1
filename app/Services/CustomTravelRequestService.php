<?php

namespace App\Services;

use App\Enums\BookingStatus;
use App\Enums\PaymentStatus;
use App\Enums\TravelRequestProposalStatus;
use App\Enums\TravelRequestStatus;
use App\Models\Agency;
use App\Models\Booking;
use App\Models\TravelRequest;
use App\Models\TravelRequestEvent;
use App\Models\TravelRequestProposal;
use App\Models\TravelRequestRecipient;
use App\Models\User;
use App\Notifications\AgencyActivityNotification;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class CustomTravelRequestService
{
    public function __construct(
        private readonly AdminOperationalNotificationService $adminNotifications,
    ) {}

    public function eligibleAgencies(): Builder
    {
        return Agency::verified()
            ->whereHas('owner', fn (Builder $query) => $query->where('role', 'agency_owner')->whereNull('deleted_at'));
    }

    /**
     * @param  array<string, mixed>  $data
     * @param  list<int>  $serviceIds
     * @param  list<int>  $agencyIds
     */
    public function publish(
        User $traveler,
        array $data,
        array $serviceIds,
        array $agencyIds,
        bool $shareWithAll,
    ): TravelRequest {
        return DB::transaction(function () use ($traveler, $data, $serviceIds, $agencyIds, $shareWithAll): TravelRequest {
            $request = $traveler->travelRequests()->create([
                ...$data,
                'reference' => $this->requestReference(),
                'status' => TravelRequestStatus::OPEN->value,
                'sharing_mode' => $shareWithAll ? 'all' : 'selected',
                'published_at' => now(),
                'budget' => $data['budget_max'] ?? null,
            ]);

            if ($serviceIds !== []) {
                $request->services()->sync($serviceIds);
            }

            $recipients = ($shareWithAll
                ? $this->eligibleAgencies()
                : $this->eligibleAgencies()->whereKey($agencyIds))
                ->with('owner')
                ->get();

            if ($recipients->isEmpty()) {
                throw new \RuntimeException('A request must have at least one eligible agency recipient.');
            }

            foreach ($recipients as $agency) {
                $request->recipients()->create([
                    'agency_id' => $agency->id,
                    'available_at' => now(),
                    'status' => 'active',
                ]);
            }

            $this->recordEvent($request, $traveler, 'published', [
                'sharing_mode' => $request->sharing_mode,
                'recipient_count' => $recipients->count(),
            ]);

            DB::afterCommit(function () use ($recipients, $request, $traveler): void {
                $this->adminNotifications->customRequestReceived($request);

                $traveler->notify(new AgencyActivityNotification(
                    __('messages.custom_requests.notification_published_title'),
                    __('messages.custom_requests.notification_published_traveler', ['reference' => $request->reference]),
                    'custom_request',
                    route('account.custom-requests.show', ['travelRequest' => $request->id, 'lang' => app()->getLocale()])
                ));

                foreach ($recipients as $agency) {
                    $agency->owner?->notify(new AgencyActivityNotification(
                        __('messages.custom_requests.notification_new_title'),
                        __('messages.custom_requests.notification_new_agency', ['reference' => $request->reference]),
                        'custom_request',
                        route('agency.custom-requests.show', ['travelRequest' => $request->id, 'lang' => app()->getLocale()])
                    ));
                }
            });

            return $request->refresh();
        });
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function submitProposal(TravelRequest $request, Agency $agency, User $owner, array $data): TravelRequestProposal
    {
        return DB::transaction(function () use ($request, $agency, $owner, $data): TravelRequestProposal {
            $lockedRequest = TravelRequest::query()->lockForUpdate()->findOrFail($request->id);
            abort_unless($lockedRequest->status === TravelRequestStatus::OPEN->value, 404);
            abort_unless(
                $lockedRequest->recipients()->where('agency_id', $agency->id)->where('status', 'active')->exists(),
                404
            );
            abort_unless($this->agencyIsEligible($agency), 403);

            $proposal = TravelRequestProposal::query()->firstOrNew([
                'travel_request_id' => $lockedRequest->id,
                'agency_id' => $agency->id,
            ]);
            abort_if(
                $proposal->exists && $proposal->status !== TravelRequestProposalStatus::WITHDRAWN->value,
                422,
                'This agency has already applied to the request.'
            );

            if ($proposal->exists) {
                $proposal->revisions()->create([
                    'actor_id' => $owner->id,
                    'snapshot' => $proposal->only([
                        'price', 'currency', 'price_breakdown', 'services_included',
                        'services_excluded', 'itinerary', 'estimated_timeline',
                        'message', 'terms', 'valid_until',
                    ]),
                ]);
            } else {
                $proposal->reference = $this->proposalReference();
            }

            $proposal->fill([
                ...$data,
                'owner_id' => $owner->id,
                'status' => TravelRequestProposalStatus::SUBMITTED->value,
                'submitted_at' => now(),
                'withdrawn_at' => null,
                'selected_at' => null,
            ]);
            $proposal->save();

            $recipient = $lockedRequest->recipients()->where('agency_id', $agency->id)->lockForUpdate()->firstOrFail();
            $recipient->forceFill([
                'first_viewed_at' => $recipient->first_viewed_at ?? now(),
                'last_viewed_at' => now(),
                'read_at' => $recipient->read_at ?? now(),
            ])->save();

            $this->recordEvent($lockedRequest, $owner, 'proposal_submitted', [
                'proposal_id' => $proposal->id,
                'agency_id' => $agency->id,
            ]);

            $traveler = $lockedRequest->traveler()->first();
            DB::afterCommit(function () use ($traveler, $agency, $lockedRequest): void {
                $traveler?->notify(new AgencyActivityNotification(
                    __('messages.custom_requests.notification_proposal_title'),
                    __('messages.custom_requests.notification_proposal_traveler', [
                        'agency' => $agency->company_name,
                        'reference' => $lockedRequest->reference,
                    ]),
                    'custom_request_proposal',
                    route('account.custom-requests.show', ['travelRequest' => $lockedRequest->id, 'lang' => app()->getLocale()])
                ));
            });

            return $proposal->refresh();
        });
    }

    public function markViewed(TravelRequestRecipient $recipient): void
    {
        $now = now();
        $recipient->forceFill([
            'first_viewed_at' => $recipient->first_viewed_at ?? $now,
            'last_viewed_at' => $now,
        ])->save();
    }

    public function markRead(TravelRequestRecipient $recipient): void
    {
        $now = now();
        $recipient->forceFill([
            'first_viewed_at' => $recipient->first_viewed_at ?? $now,
            'last_viewed_at' => $now,
            'read_at' => $recipient->read_at ?? $now,
        ])->save();
    }

    public function convertToBooking(TravelRequest $request, TravelRequestProposal $proposal, User $traveler): Booking
    {
        return DB::transaction(function () use ($request, $proposal, $traveler): Booking {
            $lockedRequest = TravelRequest::query()->lockForUpdate()->findOrFail($request->id);
            abort_unless($lockedRequest->user_id === $traveler->id, 404);
            abort_unless($lockedRequest->status === TravelRequestStatus::OPEN->value, 422, 'This request is not open.');
            abort_if(Booking::query()->where('custom_request_id', $lockedRequest->id)->exists(), 422, 'This request already has a booking.');

            $selected = TravelRequestProposal::query()
                ->whereKey($proposal->id)
                ->where('travel_request_id', $lockedRequest->id)
                ->lockForUpdate()
                ->firstOrFail();
            abort_unless($selected->status === TravelRequestProposalStatus::SUBMITTED->value, 422, 'This proposal is no longer available.');
            abort_if($selected->valid_until?->lt(today()), 422, 'This proposal has expired.');
            abort_unless(
                $lockedRequest->recipients()->where('agency_id', $selected->agency_id)->where('status', 'active')->exists(),
                422,
                'The selected agency is not an active recipient.'
            );

            $agency = Agency::query()->lockForUpdate()->findOrFail($selected->agency_id);
            abort_unless($this->agencyIsEligible($agency), 422, 'This agency is no longer eligible.');

            $snapshot = $selected->only([
                'reference', 'price', 'currency', 'price_breakdown', 'services_included',
                'services_excluded', 'itinerary', 'estimated_timeline', 'message',
                'terms', 'valid_until',
            ]);
            $competingProposals = $lockedRequest->proposals()
                ->whereKeyNot($selected->id)
                ->where('status', TravelRequestProposalStatus::SUBMITTED->value)
                ->with('agency.owner')
                ->get();

            $booking = Booking::query()->create([
                'agency_id' => $agency->id,
                'package_id' => null,
                'package_availability_id' => null,
                'booking_type' => 'custom',
                'custom_request_id' => $lockedRequest->id,
                'custom_proposal_id' => $selected->id,
                'quote_snapshot' => $snapshot,
                'user_id' => $traveler->id,
                'booking_reference' => app(BookingService::class)->generateReference(),
                'status' => BookingStatus::PENDING,
                'payment_status' => PaymentStatus::UNPAID,
                'traveler_count' => $lockedRequest->traveler_count,
                'unit_price' => $selected->price,
                'total_amount' => $selected->price,
                'currency' => $selected->currency,
                'expires_at' => now()->addMinutes((int) config('travelconnect.booking_pending_expiry_minutes', 60)),
            ]);

            $booking->statusHistory()->create([
                'actor_id' => $traveler->id,
                'event_type' => 'custom_request_conversion',
                'from_status' => null,
                'to_status' => BookingStatus::PENDING->value,
                'reason' => 'Created from selected custom travel proposal.',
            ]);

            $selected->update([
                'status' => TravelRequestProposalStatus::SELECTED->value,
                'selected_at' => now(),
            ]);
            $lockedRequest->proposals()
                ->whereKey($competingProposals->modelKeys())
                ->update(['status' => TravelRequestProposalStatus::NOT_SELECTED->value]);
            $lockedRequest->recipients()->where('status', 'active')->update(['status' => 'closed']);
            $lockedRequest->update(['status' => TravelRequestStatus::CONVERTED->value]);

            $this->recordEvent($lockedRequest, $traveler, 'booking_created', [
                'booking_id' => $booking->id,
                'proposal_id' => $selected->id,
                'agency_id' => $agency->id,
            ]);

            DB::afterCommit(function () use ($traveler, $agency, $booking, $lockedRequest, $competingProposals): void {
                $traveler->notify(new AgencyActivityNotification(
                    __('messages.custom_requests.notification_booking_title'),
                    __('messages.custom_requests.notification_booking_traveler', ['reference' => $booking->booking_reference]),
                    'custom_booking',
                    route('bookings.show', ['booking' => $booking->id, 'lang' => app()->getLocale()])
                ));

                $agency->owner?->notify(new AgencyActivityNotification(
                    __('messages.custom_requests.notification_selected_title'),
                    __('messages.custom_requests.notification_selected_agency', ['reference' => $lockedRequest->reference]),
                    'custom_booking',
                    route('agency.bookings.show', ['booking' => $booking->id, 'lang' => app()->getLocale()])
                ));

                foreach ($competingProposals as $competingProposal) {
                    $competingProposal->agency?->owner?->notify(new AgencyActivityNotification(
                        __('messages.custom_requests.notification_outcome_title'),
                        __('messages.custom_requests.notification_not_selected', ['reference' => $lockedRequest->reference]),
                        'custom_request',
                        route('agency.custom-requests.applications', ['lang' => app()->getLocale()])
                    ));
                }
            });

            return $booking;
        });
    }

    public function moderate(TravelRequest $request, User $admin, string $action, ?string $reason): TravelRequest
    {
        return DB::transaction(function () use ($request, $admin, $action, $reason): TravelRequest {
            $request = TravelRequest::query()->lockForUpdate()->findOrFail($request->id);
            abort_if($request->booking()->exists(), 422, 'A converted request cannot be moderated through this action.');

            $allowedStatuses = match ($action) {
                'hold' => [TravelRequestStatus::OPEN->value],
                'restore' => [TravelRequestStatus::ON_HOLD->value],
                'reject' => [TravelRequestStatus::OPEN->value, TravelRequestStatus::ON_HOLD->value],
                'close' => [TravelRequestStatus::OPEN->value, TravelRequestStatus::ON_HOLD->value],
                'reopen' => [TravelRequestStatus::CLOSED->value, TravelRequestStatus::REJECTED->value],
                default => [],
            };
            abort_unless(in_array($request->status, $allowedStatuses, true), 422, 'This request cannot be changed from its current status.');

            $newStatus = match ($action) {
                'hold' => TravelRequestStatus::ON_HOLD,
                'restore', 'reopen' => TravelRequestStatus::OPEN,
                'reject' => TravelRequestStatus::REJECTED,
                'close' => TravelRequestStatus::CLOSED,
            };
            $request->update([
                'status' => $newStatus->value,
                'hold_reason' => $newStatus === TravelRequestStatus::ON_HOLD ? $reason : null,
            ]);

            $recipients = $request->recipients()->with('agency.owner')->get();
            if ($action === 'hold') {
                $request->recipients()->where('status', 'active')->update(['status' => 'on_hold']);
            } elseif (in_array($action, ['restore', 'reopen'], true)) {
                foreach ($recipients as $recipient) {
                    $recipient->update([
                        'status' => $recipient->agency && $this->agencyIsEligible($recipient->agency) ? 'active' : 'closed',
                    ]);
                }
            }

            if (in_array($newStatus, [TravelRequestStatus::REJECTED, TravelRequestStatus::CLOSED], true)) {
                $request->proposals()->where('status', TravelRequestProposalStatus::SUBMITTED->value)
                    ->update(['status' => TravelRequestProposalStatus::CLOSED->value]);
                $request->recipients()->whereIn('status', ['active', 'on_hold'])->update(['status' => 'closed']);
            }

            $this->recordEvent($request, $admin, 'admin_'.$action, ['reason' => $reason]);
            DB::afterCommit(function () use ($request, $recipients, $newStatus): void {
                $request->traveler?->notify(new AgencyActivityNotification(
                    __('messages.custom_requests.notification_admin_title'),
                    __('messages.custom_requests.notification_admin_traveler', [
                        'reference' => $request->reference,
                        'status' => $newStatus->value,
                    ]),
                    'custom_request',
                    route('account.custom-requests.show', ['travelRequest' => $request->id, 'lang' => app()->getLocale()])
                ));

                foreach ($recipients as $recipient) {
                    $recipient->agency?->owner?->notify(new AgencyActivityNotification(
                        __('messages.custom_requests.notification_admin_title'),
                        __('messages.custom_requests.notification_admin_agency', [
                            'reference' => $request->reference,
                            'status' => $newStatus->value,
                        ]),
                        'custom_request',
                        route('agency.custom-requests.index', ['lang' => app()->getLocale()])
                    ));
                }
            });

            return $request->refresh();
        });
    }

    public function agencyIsEligible(Agency $agency): bool
    {
        return $this->eligibleAgencies()->whereKey($agency->id)->exists();
    }

    public function withdrawProposal(TravelRequestProposal $proposal, User $owner): void
    {
        DB::transaction(function () use ($proposal, $owner): void {
            $request = TravelRequest::query()->lockForUpdate()->findOrFail($proposal->travel_request_id);
            $proposal = TravelRequestProposal::query()->lockForUpdate()->findOrFail($proposal->id);
            abort_unless($proposal->owner_id === $owner->id, 404);
            abort_unless(
                $request->status === TravelRequestStatus::OPEN->value
                && $proposal->status === TravelRequestProposalStatus::SUBMITTED->value,
                422,
                'This proposal can no longer be withdrawn.'
            );

            $proposal->update([
                'status' => TravelRequestProposalStatus::WITHDRAWN->value,
                'withdrawn_at' => now(),
            ]);
            $this->recordEvent($request, $owner, 'proposal_withdrawn', [
                'proposal_id' => $proposal->id,
                'agency_id' => $proposal->agency_id,
            ]);
        });
    }

    public function recordEvent(TravelRequest $request, ?User $actor, string $type, array $details = []): TravelRequestEvent
    {
        return $request->events()->create([
            'actor_id' => $actor?->id,
            'event_type' => $type,
            'details' => $details,
            'created_at' => now(),
        ]);
    }

    private function requestReference(): string
    {
        do {
            $reference = 'TC-REQ-'.now()->format('Y').'-'.Str::upper(Str::random(8));
        } while (TravelRequest::query()->where('reference', $reference)->exists());

        return $reference;
    }

    private function proposalReference(): string
    {
        do {
            $reference = 'TC-QUO-'.now()->format('Y').'-'.Str::upper(Str::random(8));
        } while (TravelRequestProposal::query()->where('reference', $reference)->exists());

        return $reference;
    }
}
