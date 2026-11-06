<?php

namespace App\Services;

use App\Enums\UserRole;
use App\Models\ContentReport;
use App\Models\Review;
use App\Models\TravelerIssue;
use App\Models\TrustSafetyEvent;
use App\Models\User;
use App\Notifications\AgencyActivityNotification;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class AdminReviewReportManagementService
{
    /**
     * @param  array{search?: string, status?: string}  $filters
     * @return array{reviews?: LengthAwarePaginator, reports?: LengthAwarePaginator, complaints?: LengthAwarePaginator, administrators?: Collection<int, User>}
     */
    public function pageData(string $page, array $filters): array
    {
        return match ($page) {
            'reviews_ratings' => ['reviews' => $this->reviews($filters)],
            'reported_content' => [
                'reports' => $this->reports($filters),
                'administrators' => User::query()
                    ->where('role', UserRole::ADMIN)
                    ->orderBy('name')
                    ->get(['id', 'name']),
            ],
            'user_agency_complaints' => [
                'complaints' => $this->complaints($filters),
                'administrators' => User::query()
                    ->where('role', UserRole::ADMIN)
                    ->orderBy('name')
                    ->get(['id', 'name']),
            ],
        };
    }

    /**
     * @param  array{search?: string, status?: string}  $filters
     */
    private function reviews(array $filters): LengthAwarePaginator
    {
        $query = Review::query()
            ->with([
                'user:id,name',
                'agency:id,company_name',
                'booking.package:id,title',
                'events.actor:id,name',
            ])
            ->withCount('reports')
            ->latest();

        if (($filters['status'] ?? 'all') !== 'all') {
            $query->where('status', $filters['status']);
        }

        if (filled($filters['search'] ?? null)) {
            $search = $filters['search'];
            $query->where(function (Builder $reviews) use ($search): void {
                $reviews->where('comment', 'like', "%{$search}%")
                    ->orWhereHas('user', fn (Builder $users) => $users->where('name', 'like', "%{$search}%"))
                    ->orWhereHas('agency', fn (Builder $agencies) => $agencies->where('company_name', 'like', "%{$search}%"))
                    ->orWhereHas('booking.package', fn (Builder $packages) => $packages->where('title', 'like', "%{$search}%"));
            });
        }

        return $query->paginate(15)->withQueryString();
    }

    /**
     * @param  array{search?: string, status?: string}  $filters
     */
    private function reports(array $filters): LengthAwarePaginator
    {
        $query = ContentReport::query()
            ->with([
                'review.user:id,name',
                'review.agency:id,company_name',
                'review.booking.package:id,title',
                'reporter:id,name',
                'assignee:id,name',
                'events.actor:id,name',
            ])
            ->latest();

        if (($filters['status'] ?? 'all') !== 'all') {
            $query->where('status', $filters['status']);
        }

        if (filled($filters['search'] ?? null)) {
            $search = $filters['search'];
            $query->where(function (Builder $reports) use ($search): void {
                $reports->where('reason', 'like', "%{$search}%")
                    ->orWhere('evidence', 'like', "%{$search}%")
                    ->orWhereHas('reporter', fn (Builder $users) => $users->where('name', 'like', "%{$search}%"))
                    ->orWhereHas('review', fn (Builder $reviews) => $reviews
                        ->where('comment', 'like', "%{$search}%")
                        ->orWhereHas('agency', fn (Builder $agencies) => $agencies->where('company_name', 'like', "%{$search}%")));
            });
        }

        return $query->paginate(15)->withQueryString();
    }

    /**
     * @param  array{search?: string, status?: string}  $filters
     */
    private function complaints(array $filters): LengthAwarePaginator
    {
        $query = TravelerIssue::query()
            ->with([
                'traveler:id,name,email',
                'booking.agency:id,company_name',
                'booking.package:id,title',
                'assignee:id,name',
                'events.actor:id,name',
            ])
            ->latest();

        if (($filters['status'] ?? 'all') !== 'all') {
            $query->where('status', $filters['status']);
        }

        if (filled($filters['search'] ?? null)) {
            $search = $filters['search'];
            $query->where(function (Builder $issues) use ($search): void {
                $issues->where('subject', 'like', "%{$search}%")
                    ->orWhere('description', 'like', "%{$search}%")
                    ->orWhereHas('traveler', fn (Builder $users) => $users->where('name', 'like', "%{$search}%"))
                    ->orWhereHas('booking', fn (Builder $bookings) => $bookings->where('booking_reference', 'like', "%{$search}%"))
                    ->orWhereHas('booking.agency', fn (Builder $agencies) => $agencies->where('company_name', 'like', "%{$search}%"))
                    ->orWhereHas('booking.package', fn (Builder $packages) => $packages->where('title', 'like', "%{$search}%"));
            });
        }

        return $query->paginate(15)->withQueryString();
    }

    public function moderateReview(Review $review, User $actor, string $decision, ?string $reason): void
    {
        DB::transaction(function () use ($review, $actor, $decision, $reason): void {
            $lockedReview = Review::query()->lockForUpdate()->findOrFail($review->id);
            $lockedReview->loadMissing(['user', 'agency.owner']);
            $shouldHide = $decision === 'hide';
            if (($shouldHide && $lockedReview->is_hidden) || (! $shouldHide && ! $lockedReview->is_hidden)) {
                throw ValidationException::withMessages([
                    'decision' => __('messages.trust_safety.invalid_transition'),
                ]);
            }

            $lockedReview->update(['is_hidden' => $shouldHide]);
            TrustSafetyEvent::query()->create([
                'source_type' => 'review',
                'source_id' => $lockedReview->id,
                'actor_id' => $actor->id,
                'action' => $decision,
                'status_before' => $lockedReview->status->value,
                'status_after' => $lockedReview->status->value,
                'reason' => $reason,
            ]);

            $agencyName = $lockedReview->agency->company_name;
            $recipients = collect([$lockedReview->user, $lockedReview->agency->owner])
                ->filter()
                ->unique('id');

            foreach ($recipients as $recipient) {
                $isReviewer = $recipient->id === $lockedReview->user_id;
                $audience = $isReviewer ? 'traveler' : 'agency';

                $recipient->notify(new AgencyActivityNotification(
                    __('messages.trust_safety.review_moderation_notification_title'),
                    __('messages.trust_safety.review_moderation_notifications.'.$audience.'.'.$decision, [
                        'agency' => $agencyName,
                    ]),
                    'review_moderation',
                    route($isReviewer ? 'account.activity' : 'agency.notifications.index', [
                        'lang' => app()->getLocale(),
                    ]),
                ));
            }

            $lockedReview->agency->recalculateTrustScoreFromPublishedReviews();
        });
    }

    public function actOnReport(ContentReport $report, User $actor, string $action, ?int $assigneeId, string $reason): void
    {
        DB::transaction(function () use ($report, $actor, $action, $assigneeId, $reason): void {
            $lockedReport = ContentReport::query()->lockForUpdate()->findOrFail($report->id);
            if (in_array($lockedReport->status, ['dismissed', 'restricted', 'removed'], true)) {
                throw ValidationException::withMessages([
                    'action' => __('messages.trust_safety.invalid_transition'),
                ]);
            }

            $before = $lockedReport->status;
            $after = match ($action) {
                'investigate' => 'investigating',
                'dismiss' => 'dismissed',
                'restrict' => 'restricted',
                'remove' => 'removed',
            };
            $lockedReport->update([
                'status' => $after,
                'assigned_to' => $assigneeId ?? $lockedReport->assigned_to,
                'outcome_reason' => $after === 'investigating' ? null : $reason,
            ]);

            if (in_array($action, ['restrict', 'remove'], true)) {
                $review = $lockedReport->review()->lockForUpdate()->firstOrFail();
                if (! $review->is_hidden) {
                    $review->update(['is_hidden' => true]);
                    TrustSafetyEvent::query()->create([
                        'source_type' => 'review',
                        'source_id' => $review->id,
                        'actor_id' => $actor->id,
                        'action' => $action,
                        'status_before' => $review->status->value,
                        'status_after' => $review->status->value,
                        'reason' => $reason,
                    ]);
                    $review->agency->recalculateTrustScoreFromPublishedReviews();
                }
            }

            TrustSafetyEvent::query()->create([
                'source_type' => 'content_report',
                'source_id' => $lockedReport->id,
                'actor_id' => $actor->id,
                'action' => $action,
                'status_before' => $before,
                'status_after' => $after,
                'reason' => $reason,
            ]);
        });
    }

    public function actOnComplaint(
        TravelerIssue $issue,
        User $actor,
        string $action,
        ?int $assigneeId,
        string $reason,
    ): void {
        DB::transaction(function () use ($issue, $actor, $action, $assigneeId, $reason): void {
            $lockedIssue = TravelerIssue::query()->lockForUpdate()->findOrFail($issue->id);
            if ($lockedIssue->status === 'resolved' && $action !== 'reopen') {
                throw ValidationException::withMessages([
                    'action' => __('messages.trust_safety.invalid_transition'),
                ]);
            }

            $before = $lockedIssue->status;
            $after = match ($action) {
                'investigate', 'reopen' => 'in_review',
                'resolve' => 'resolved',
                'assign', 'note' => $before,
            };

            if ($action === 'assign') {
                $lockedIssue->assigned_to = $assigneeId;
                $lockedIssue->save();
            } elseif ($action !== 'note') {
                $lockedIssue->update(['status' => $after]);
            }

            TrustSafetyEvent::query()->create([
                'source_type' => 'traveler_issue',
                'source_id' => $lockedIssue->id,
                'actor_id' => $actor->id,
                'action' => $action,
                'status_before' => $before,
                'status_after' => $after,
                'reason' => $reason,
            ]);
        });
    }
}
