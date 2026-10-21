<?php

namespace App\Services;

use App\Enums\ReviewStatus;
use App\Enums\VerificationStatus;
use App\Models\AdminAlert;
use App\Models\Agency;
use App\Models\IntegrationFailure;
use App\Models\Review;
use App\Models\TravelerIssue;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Query\Builder;
use Illuminate\Database\Query\JoinClause;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class AdminAlertQueue
{
    private const STATUSES = ['open', 'acknowledged', 'resolved'];

    private const CATEGORIES = [
        'agency_verification',
        'reported_content',
        'complaint',
        'booking_dispute',
        'failed_job',
        'suspicious_activity',
        'integration_error',
    ];

    private const SEVERITIES = ['low', 'medium', 'high', 'critical'];

    /**
     * @param  array<string, string>  $filters
     * @return LengthAwarePaginator<int, object>
     */
    public function listing(array $filters): LengthAwarePaginator
    {
        $union = $this->sourcesUnion();

        $query = DB::query()
            ->fromSub($union, 'alert_sources')
            ->leftJoin('admin_alerts', function (JoinClause $join): void {
                $join->on('admin_alerts.source_type', '=', 'alert_sources.source_type')
                    ->on('admin_alerts.source_id', '=', 'alert_sources.source_id');
            })
            ->select([
                'alert_sources.source_type',
                'alert_sources.source_id',
                'alert_sources.category',
                'alert_sources.severity',
                'alert_sources.title',
                'alert_sources.related_label',
                'alert_sources.created_at',
                DB::raw("COALESCE(admin_alerts.status, 'open') as status"),
            ]);

        if (($filters['status'] ?? 'all') !== 'all') {
            $query->whereRaw("COALESCE(admin_alerts.status, 'open') = ?", [$filters['status']]);
        }

        if (($filters['severity'] ?? 'all') !== 'all') {
            $query->where('alert_sources.severity', $filters['severity']);
        }

        if (($filters['category'] ?? 'all') !== 'all') {
            $query->where('alert_sources.category', $filters['category']);
        }

        if (($filters['search'] ?? '') !== '') {
            $search = '%'.$filters['search'].'%';
            $query->where(function (Builder $searchQuery) use ($search): void {
                $searchQuery->where('alert_sources.title', 'like', $search)
                    ->orWhere('alert_sources.related_label', 'like', $search)
                    ->orWhere('alert_sources.source_type', 'like', $search);
            });
        }

        return $query
            ->orderByDesc('alert_sources.created_at')
            ->orderBy('alert_sources.source_type')
            ->orderByDesc('alert_sources.source_id')
            ->paginate(20)
            ->withQueryString();
    }

    /**
     * @return array<string, mixed>
     */
    public function detail(string $sourceType, int $sourceId): array
    {
        abort_unless(array_key_exists($sourceType, $this->sourceDefinitions()), 404);

        $definition = $this->sourceDefinitions()[$sourceType];
        $source = match ($sourceType) {
            'agency' => Agency::query()->with('owner:id,name,email')->findOrFail($sourceId),
            'review' => Review::query()->with(['agency:id,company_name', 'user:id,name,email'])->findOrFail($sourceId),
            'traveler_issue' => TravelerIssue::query()
                ->with(['traveler:id,name,email', 'booking:id,booking_reference,agency_id', 'booking.agency:id,company_name'])
                ->findOrFail($sourceId),
            'integration_failure' => IntegrationFailure::query()->findOrFail($sourceId),
            'failed_job' => DB::table('failed_jobs')
                ->select(['id', 'uuid', 'connection', 'queue', 'exception', 'failed_at'])
                ->where('id', $sourceId)
                ->first() ?? abort(404),
            'login_burst' => $this->loginBurstSource($sourceId),
        };

        $state = AdminAlert::query()
            ->with(['actions.actor:id,name'])
            ->where('source_type', $sourceType)
            ->where('source_id', $sourceId)
            ->first();

        return [
            'source_type' => $sourceType,
            'source_id' => $sourceId,
            'category' => $sourceType === 'traveler_issue' ? $source->type : $definition['category'],
            'severity' => $definition['severity'],
            'title' => $this->titleFor($sourceType, $source),
            'related_label' => $this->relatedLabelFor($sourceType, $source),
            'created_at' => $this->createdAtFor($sourceType, $source),
            'reason' => $this->reasonFor($sourceType, $source),
            'status' => $state?->status ?? 'open',
            'details' => $this->detailsFor($sourceType, $source),
            'actions' => $state?->actions ?? collect(),
            'management_page' => $definition['management_page'],
        ];
    }

    public function transition(string $sourceType, int $sourceId, string $action, User $actor): void
    {
        abort_unless(array_key_exists($sourceType, $this->sourceDefinitions()), 404);
        abort_unless(in_array($action, ['acknowledge', 'resolve'], true), 404);

        DB::transaction(function () use ($sourceType, $sourceId, $action, $actor): void {
            $this->assertSourceExists($sourceType, $sourceId);
            $now = now();

            DB::table('admin_alerts')->insertOrIgnore([
                'source_type' => $sourceType,
                'source_id' => $sourceId,
                'status' => 'open',
                'created_at' => $now,
                'updated_at' => $now,
            ]);

            $alert = AdminAlert::query()
                ->where('source_type', $sourceType)
                ->where('source_id', $sourceId)
                ->lockForUpdate()
                ->firstOrFail();

            $nextStatus = match (true) {
                $action === 'acknowledge' && $alert->status === 'open' => 'acknowledged',
                $action === 'resolve' && in_array($alert->status, ['open', 'acknowledged'], true) => 'resolved',
                default => null,
            };

            if ($nextStatus === null) {
                throw ValidationException::withMessages([
                    'status' => __('messages.admin_alerts.invalid_transition'),
                ]);
            }

            $before = $alert->status;
            $timestampField = $nextStatus === 'acknowledged' ? 'acknowledged_at' : 'resolved_at';
            $actorField = $nextStatus === 'acknowledged' ? 'acknowledged_by' : 'resolved_by';

            $alert->forceFill([
                'status' => $nextStatus,
                $timestampField => $now,
                $actorField => $actor->id,
            ])->save();

            $alert->actions()->create([
                'actor_id' => $actor->id,
                'action' => $action,
                'status_before' => $before,
                'status_after' => $nextStatus,
                'created_at' => $now,
            ]);

            if ($sourceType === 'traveler_issue') {
                TravelerIssue::query()
                    ->whereKey($sourceId)
                    ->lockForUpdate()
                    ->firstOrFail()
                    ->update(['status' => $nextStatus === 'resolved' ? 'resolved' : 'in_review']);
            }
        });
    }

    /**
     * @return array<string, array{category: string, severity: string, management_page: string}>
     */
    public function sourceDefinitions(): array
    {
        return [
            'agency' => [
                'category' => 'agency_verification',
                'severity' => 'medium',
                'management_page' => 'pending_verification',
            ],
            'review' => [
                'category' => 'reported_content',
                'severity' => 'high',
                'management_page' => 'reported_content',
            ],
            'traveler_issue' => [
                'category' => 'complaint',
                'severity' => 'high',
                'management_page' => 'alerts',
            ],
            'integration_failure' => [
                'category' => 'integration_error',
                'severity' => 'high',
                'management_page' => 'integrations',
            ],
            'failed_job' => [
                'category' => 'failed_job',
                'severity' => 'high',
                'management_page' => 'system_health',
            ],
            'login_burst' => [
                'category' => 'suspicious_activity',
                'severity' => 'high',
                'management_page' => 'security_events',
            ],
        ];
    }

    /**
     * @return array{statuses: array<int, string>, categories: array<int, string>, severities: array<int, string>}
     */
    public function filterOptions(): array
    {
        return [
            'statuses' => self::STATUSES,
            'categories' => self::CATEGORIES,
            'severities' => self::SEVERITIES,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function sourcesUnion(): Builder
    {
        $agencyQuery = DB::table('agencies')
            ->join('users', 'users.id', '=', 'agencies.user_id')
            ->where('agencies.verification_status', VerificationStatus::PENDING->value)
            ->whereNull('agencies.deleted_at')
            ->where('agencies.created_at', '<=', now()->subHours(24))
            ->selectRaw("'agency' as source_type, agencies.id as source_id, 'agency_verification' as category, 'medium' as severity, agencies.company_name as title, users.name as related_label, agencies.created_at as created_at");

        $reviewQuery = DB::table('reviews')
            ->join('agencies', 'agencies.id', '=', 'reviews.agency_id')
            ->where('reviews.status', ReviewStatus::FLAGGED->value)
            ->selectRaw("'review' as source_type, reviews.id as source_id, 'reported_content' as category, 'high' as severity, reviews.comment as title, agencies.company_name as related_label, reviews.created_at as created_at");

        $issueQuery = DB::table('traveler_issues')
            ->join('users', 'users.id', '=', 'traveler_issues.user_id')
            ->leftJoin('bookings', 'bookings.id', '=', 'traveler_issues.booking_id')
            ->whereIn('traveler_issues.type', ['complaint', 'booking_dispute'])
            ->selectRaw("'traveler_issue' as source_type, traveler_issues.id as source_id, traveler_issues.type as category, 'high' as severity, traveler_issues.subject as title, COALESCE(bookings.booking_reference, users.name) as related_label, traveler_issues.created_at as created_at");

        $integrationQuery = DB::table('integration_failures')
            ->selectRaw("'integration_failure' as source_type, integration_failures.id as source_id, 'integration_error' as category, 'high' as severity, integration_failures.integration as title, integration_failures.operation as related_label, integration_failures.created_at as created_at");

        $failedJobQuery = DB::table('failed_jobs')
            ->selectRaw("'failed_job' as source_type, failed_jobs.id as source_id, 'failed_job' as category, 'high' as severity, failed_jobs.uuid as title, failed_jobs.queue as related_label, failed_jobs.failed_at as created_at");

        $loginBurstQuery = DB::table('admin_login_events')
            ->selectRaw("'login_burst' as source_type, MAX(id) as source_id, 'suspicious_activity' as category, 'high' as severity, email as title, COALESCE(ip_address, ?) as related_label, MAX(created_at) as created_at", [__('messages.admin_alerts.unknown_ip')])
            ->where(function (Builder $query): void {
                $query->where('outcome', 'password_failed')
                    ->orWhere(function (Builder $blockedQuery): void {
                        $blockedQuery->where('outcome', 'blocked')
                            ->where('failed_step', 'password_lockout');
                    });
            })
            ->where('created_at', '>=', now()->subMinutes(10))
            ->groupBy('email', 'ip_address')
            ->havingRaw('COUNT(*) >= 5');

        return $agencyQuery
            ->unionAll($reviewQuery)
            ->unionAll($issueQuery)
            ->unionAll($integrationQuery)
            ->unionAll($failedJobQuery)
            ->unionAll($loginBurstQuery);
    }

    private function assertSourceExists(string $sourceType, int $sourceId): void
    {
        match ($sourceType) {
            'agency' => Agency::query()
                ->whereKey($sourceId)
                ->where('verification_status', VerificationStatus::PENDING)
                ->where('created_at', '<=', now()->subHours(24))
                ->lockForUpdate()
                ->firstOrFail(),
            'review' => Review::query()
                ->whereKey($sourceId)
                ->where('status', ReviewStatus::FLAGGED)
                ->lockForUpdate()
                ->firstOrFail(),
            'traveler_issue' => TravelerIssue::query()->whereKey($sourceId)->lockForUpdate()->firstOrFail(),
            'integration_failure' => IntegrationFailure::query()->whereKey($sourceId)->lockForUpdate()->firstOrFail(),
            'failed_job' => DB::table('failed_jobs')->where('id', $sourceId)->lockForUpdate()->first() ?? abort(404),
            'login_burst' => $this->loginBurstSource($sourceId),
            default => abort(404),
        };
    }

    private function loginBurstSource(int $sourceId): object
    {
        $source = DB::table('admin_login_events')
            ->select(['id', 'email', 'ip_address', 'user_agent', 'outcome', 'failed_step', 'created_at'])
            ->where('id', $sourceId)
            ->where(function (Builder $query): void {
                $query->where('outcome', 'password_failed')
                    ->orWhere(function (Builder $blockedQuery): void {
                        $blockedQuery->where('outcome', 'blocked')
                            ->where('failed_step', 'password_lockout');
                    });
            })
            ->first() ?? abort(404);

        $burstQuery = DB::table('admin_login_events')
            ->where(function (Builder $query): void {
                $query->where('outcome', 'password_failed')
                    ->orWhere(function (Builder $blockedQuery): void {
                        $blockedQuery->where('outcome', 'blocked')
                            ->where('failed_step', 'password_lockout');
                    });
            })
            ->where('created_at', '>=', Carbon::parse($source->created_at)->subMinutes(10))
            ->where('created_at', '<=', $source->created_at)
            ->when($source->email === null, fn (Builder $query) => $query->whereNull('email'), fn (Builder $query) => $query->where('email', $source->email))
            ->when($source->ip_address === null, fn (Builder $query) => $query->whereNull('ip_address'), fn (Builder $query) => $query->where('ip_address', $source->ip_address));

        $source->failed_attempt_count = $burstQuery->count();
        $latestEventId = (clone $burstQuery)->max('id');

        abort_unless($source->failed_attempt_count >= 5 && (int) $latestEventId === $sourceId, 404);

        return $source;
    }

    private function reasonFor(string $sourceType, mixed $source): string
    {
        return match ($sourceType) {
            'agency' => __('messages.admin_alerts.reasons.agency_verification'),
            'review' => __('messages.admin_alerts.reasons.reported_review'),
            'traveler_issue' => $source->description,
            'integration_failure' => $source->safe_message,
            'failed_job' => $this->failedJobReason((string) $source->exception),
            'login_burst' => __('messages.admin_alerts.reasons.login_burst', [
                'count' => $source->failed_attempt_count,
            ]),
        };
    }

    private function failedJobReason(string $exception): string
    {
        $summary = trim(Str::before($exception, "\n"));

        return $summary !== ''
            ? Str::limit($summary, 240)
            : __('messages.admin_alerts.reasons.job_failure_unknown');
    }

    private function titleFor(string $sourceType, mixed $source): string
    {
        return match ($sourceType) {
            'agency' => $source->company_name,
            'review' => Str::limit($source->comment, 120),
            'traveler_issue' => $source->subject,
            'integration_failure' => $source->safe_message,
            'failed_job' => $source->uuid,
            'login_burst' => $source->email,
        };
    }

    private function relatedLabelFor(string $sourceType, mixed $source): string
    {
        return match ($sourceType) {
            'agency' => $source->owner?->name ?? $source->owner?->email ?? __('messages.admin_alerts.unknown_account'),
            'review' => $source->agency->company_name,
            'traveler_issue' => $source->booking?->booking_reference ?? $source->traveler->name,
            'integration_failure' => $source->operation,
            'failed_job' => $source->queue,
            'login_burst' => $source->ip_address ?? __('messages.admin_alerts.unknown_ip'),
        };
    }

    private function createdAtFor(string $sourceType, mixed $source): mixed
    {
        return match ($sourceType) {
            'failed_job' => $source->failed_at,
            default => $source->created_at,
        };
    }

    /**
     * @return array<int, array{label: string, value: string}>
     */
    private function detailsFor(string $sourceType, mixed $source): array
    {
        return match ($sourceType) {
            'agency' => [
                ['label' => __('messages.admin_alerts.fields.owner'), 'value' => $source->owner?->name ?? $source->owner?->email ?? __('messages.admin_alerts.unknown_account')],
                ['label' => __('messages.admin_alerts.fields.email'), 'value' => $source->owner?->email ?? '—'],
                ['label' => __('messages.admin_alerts.fields.verification_status'), 'value' => $source->verification_status->value],
            ],
            'review' => [
                ['label' => __('messages.admin_alerts.fields.reviewer'), 'value' => $source->user->name],
                ['label' => __('messages.admin_alerts.fields.agency'), 'value' => $source->agency->company_name],
                ['label' => __('messages.admin_alerts.fields.rating'), 'value' => (string) $source->rating],
                ['label' => __('messages.admin_alerts.fields.comment'), 'value' => $source->comment],
            ],
            'traveler_issue' => [
                ['label' => __('messages.admin_alerts.fields.issue_type'), 'value' => __('messages.admin_alerts.categories.'.$source->type)],
                ['label' => __('messages.admin_alerts.fields.traveler'), 'value' => $source->traveler->name],
                ['label' => __('messages.admin_alerts.fields.email'), 'value' => $source->traveler->email],
                ['label' => __('messages.admin_alerts.fields.booking'), 'value' => $source->booking?->booking_reference ?? __('messages.admin_alerts.not_linked')],
                ['label' => __('messages.admin_alerts.fields.agency'), 'value' => $source->booking?->agency?->company_name ?? __('messages.admin_alerts.not_linked')],
                ['label' => __('messages.admin_alerts.fields.case_status'), 'value' => __('messages.admin_alerts.case_statuses.'.$source->status)],
            ],
            'integration_failure' => [
                ['label' => __('messages.admin_alerts.fields.integration'), 'value' => $source->integration],
                ['label' => __('messages.admin_alerts.fields.operation'), 'value' => $source->operation],
                ['label' => __('messages.admin_alerts.fields.error_type'), 'value' => $source->exception_class ?? '—'],
                ['label' => __('messages.admin_alerts.fields.error_time'), 'value' => $source->created_at->toDateTimeString()],
            ],
            'failed_job' => [
                ['label' => __('messages.admin_alerts.fields.job_uuid'), 'value' => $source->uuid],
                ['label' => __('messages.admin_alerts.fields.queue'), 'value' => $source->queue],
                ['label' => __('messages.admin_alerts.fields.connection'), 'value' => $source->connection],
                ['label' => __('messages.admin_alerts.fields.failed_at'), 'value' => (string) $source->failed_at],
            ],
            'login_burst' => [
                ['label' => __('messages.admin_alerts.fields.email'), 'value' => $source->email],
                ['label' => __('messages.admin_alerts.fields.ip_address'), 'value' => $source->ip_address ?? __('messages.admin_alerts.unknown_ip')],
                ['label' => __('messages.admin_alerts.fields.failed_attempts'), 'value' => (string) $source->failed_attempt_count],
                ['label' => __('messages.admin_alerts.fields.window'), 'value' => __('messages.admin_alerts.ten_minutes')],
                ['label' => __('messages.admin_alerts.fields.user_agent'), 'value' => Str::limit((string) $source->user_agent, 240) ?: '—'],
            ],
        };
    }
}
