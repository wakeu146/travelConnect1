<?php

namespace App\Services;

use App\Enums\PaymentTransactionStatus;
use App\Enums\ReviewStatus;
use App\Enums\VerificationStatus;
use App\Models\AdminAlert;
use App\Models\Agency;
use App\Models\BookingPaymentTransaction;
use App\Models\Review;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Query\Builder;
use Illuminate\Database\Query\JoinClause;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class AdminAlertQueue
{
    private const STATUSES = ['open', 'acknowledged', 'resolved'];

    private const CATEGORIES = [
        'agency_verification',
        'reported_content',
        'failed_payment',
        'failed_job',
        'suspicious_activity',
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
            'failed_payment' => BookingPaymentTransaction::query()->with('booking:id,booking_reference')->findOrFail($sourceId),
            'failed_job' => DB::table('failed_jobs')
                ->select(['id', 'uuid', 'connection', 'queue', 'exception', 'failed_at'])
                ->where('id', $sourceId)
                ->first() ?? abort(404),
            'admin_login_event' => DB::table('admin_login_events')
                ->select(['id', 'user_id', 'email', 'ip_address', 'user_agent', 'outcome', 'failed_step', 'created_at'])
                ->where('id', $sourceId)
                ->whereIn('outcome', ['password_failed', 'blocked'])
                ->first() ?? abort(404),
        };

        $state = AdminAlert::query()
            ->with(['actions.actor:id,name'])
            ->where('source_type', $sourceType)
            ->where('source_id', $sourceId)
            ->first();

        return [
            'source_type' => $sourceType,
            'source_id' => $sourceId,
            'category' => $definition['category'],
            'severity' => $sourceType === 'admin_login_event' && $source->outcome === 'blocked'
                ? 'high'
                : $definition['severity'],
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
            'failed_payment' => [
                'category' => 'failed_payment',
                'severity' => 'high',
                'management_page' => 'pending_failed_payments',
            ],
            'failed_job' => [
                'category' => 'failed_job',
                'severity' => 'high',
                'management_page' => 'system_health',
            ],
            'admin_login_event' => [
                'category' => 'suspicious_activity',
                'severity' => 'medium',
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
            ->selectRaw("'agency' as source_type, agencies.id as source_id, 'agency_verification' as category, 'medium' as severity, agencies.company_name as title, users.name as related_label, agencies.created_at as created_at");

        $reviewQuery = DB::table('reviews')
            ->join('agencies', 'agencies.id', '=', 'reviews.agency_id')
            ->where('reviews.status', ReviewStatus::FLAGGED->value)
            ->selectRaw("'review' as source_type, reviews.id as source_id, 'reported_content' as category, 'high' as severity, reviews.comment as title, agencies.company_name as related_label, reviews.created_at as created_at");

        $paymentQuery = DB::table('booking_payment_transactions')
            ->join('bookings', 'bookings.id', '=', 'booking_payment_transactions.booking_id')
            ->where('booking_payment_transactions.status', PaymentTransactionStatus::FAILED->value)
            ->selectRaw("'failed_payment' as source_type, booking_payment_transactions.id as source_id, 'failed_payment' as category, 'high' as severity, booking_payment_transactions.payment_reference as title, bookings.booking_reference as related_label, booking_payment_transactions.created_at as created_at");

        $failedJobQuery = DB::table('failed_jobs')
            ->selectRaw("'failed_job' as source_type, failed_jobs.id as source_id, 'failed_job' as category, 'high' as severity, failed_jobs.uuid as title, failed_jobs.queue as related_label, failed_jobs.failed_at as created_at");

        $loginEventQuery = DB::table('admin_login_events')
            ->whereIn('outcome', ['password_failed', 'blocked'])
            ->selectRaw("'admin_login_event' as source_type, admin_login_events.id as source_id, 'suspicious_activity' as category, CASE WHEN admin_login_events.outcome = 'blocked' THEN 'high' ELSE 'medium' END as severity, admin_login_events.email as title, admin_login_events.ip_address as related_label, admin_login_events.created_at as created_at");

        return $agencyQuery
            ->unionAll($reviewQuery)
            ->unionAll($paymentQuery)
            ->unionAll($failedJobQuery)
            ->unionAll($loginEventQuery);
    }

    private function assertSourceExists(string $sourceType, int $sourceId): void
    {
        match ($sourceType) {
            'agency' => Agency::query()
                ->whereKey($sourceId)
                ->where('verification_status', VerificationStatus::PENDING)
                ->lockForUpdate()
                ->firstOrFail(),
            'review' => Review::query()
                ->whereKey($sourceId)
                ->where('status', ReviewStatus::FLAGGED)
                ->lockForUpdate()
                ->firstOrFail(),
            'failed_payment' => BookingPaymentTransaction::query()
                ->whereKey($sourceId)
                ->where('status', PaymentTransactionStatus::FAILED)
                ->lockForUpdate()
                ->firstOrFail(),
            'failed_job' => DB::table('failed_jobs')->where('id', $sourceId)->lockForUpdate()->first() ?? abort(404),
            'admin_login_event' => DB::table('admin_login_events')
                ->where('id', $sourceId)
                ->whereIn('outcome', ['password_failed', 'blocked'])
                ->lockForUpdate()
                ->first() ?? abort(404),
            default => abort(404),
        };
    }

    private function reasonFor(string $sourceType, mixed $source): string
    {
        return match ($sourceType) {
            'agency' => __('messages.admin_alerts.reasons.agency_verification'),
            'review' => __('messages.admin_alerts.reasons.reported_review'),
            'failed_payment' => $source->failure_reason
                ?: __('messages.admin_alerts.reasons.payment_failure_unknown'),
            'failed_job' => $this->failedJobReason((string) $source->exception),
            'admin_login_event' => __('messages.admin_alerts.reasons.suspicious_activity', [
                'outcome' => __('messages.admin_alerts.login_outcomes.'.$source->outcome),
                'step' => $source->failed_step
                    ? __('messages.admin_alerts.login_steps.'.$source->failed_step)
                    : __('messages.admin_alerts.login_steps.unknown'),
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
            'failed_payment' => $source->payment_reference,
            'failed_job' => $source->uuid,
            'admin_login_event' => $source->email,
        };
    }

    private function relatedLabelFor(string $sourceType, mixed $source): string
    {
        return match ($sourceType) {
            'agency' => $source->owner?->name ?? $source->owner?->email ?? __('messages.admin_alerts.unknown_account'),
            'review' => $source->agency->company_name,
            'failed_payment' => $source->booking?->booking_reference ?? __('messages.admin_alerts.unknown_record'),
            'failed_job' => $source->queue,
            'admin_login_event' => $source->ip_address ?? __('messages.admin_alerts.unknown_ip'),
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
            'failed_payment' => [
                ['label' => __('messages.admin_alerts.fields.payment_reference'), 'value' => $source->payment_reference],
                ['label' => __('messages.admin_alerts.fields.booking'), 'value' => $source->booking?->booking_reference ?? __('messages.admin_alerts.unknown_record')],
                ['label' => __('messages.admin_alerts.fields.amount'), 'value' => $source->currency.' '.number_format((float) $source->amount, 2)],
                ['label' => __('messages.admin_alerts.fields.failure_reason'), 'value' => $source->failure_reason ?: '—'],
            ],
            'failed_job' => [
                ['label' => __('messages.admin_alerts.fields.job_uuid'), 'value' => $source->uuid],
                ['label' => __('messages.admin_alerts.fields.queue'), 'value' => $source->queue],
                ['label' => __('messages.admin_alerts.fields.connection'), 'value' => $source->connection],
                ['label' => __('messages.admin_alerts.fields.failed_at'), 'value' => (string) $source->failed_at],
            ],
            'admin_login_event' => [
                ['label' => __('messages.admin_alerts.fields.email'), 'value' => $source->email],
                ['label' => __('messages.admin_alerts.fields.ip_address'), 'value' => $source->ip_address ?? __('messages.admin_alerts.unknown_ip')],
                ['label' => __('messages.admin_alerts.fields.outcome'), 'value' => $source->outcome],
                ['label' => __('messages.admin_alerts.fields.failed_step'), 'value' => $source->failed_step ?? '—'],
                ['label' => __('messages.admin_alerts.fields.user_agent'), 'value' => Str::limit((string) $source->user_agent, 240) ?: '—'],
            ],
        };
    }
}
