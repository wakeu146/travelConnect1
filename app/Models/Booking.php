<?php

namespace App\Models;

use App\Enums\BookingStatus;
use App\Enums\PaymentStatus;
use App\Enums\PaymentTransactionStatus;
use App\Enums\PaymentTransactionType;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Booking extends Model
{
    use HasFactory;

    protected $fillable = [
        'agency_id',
        'package_id',
        'package_availability_id',
        'booking_type',
        'custom_request_id',
        'custom_proposal_id',
        'quote_snapshot',
        'user_id',
        'booking_reference',
        'status',
        'payment_status',
        'traveler_count',
        'unit_price',
        'total_amount',
        'currency',
        'notes',
        'expires_at',
        'confirmed_at',
        'rejected_at',
        'cancelled_at',
        'completed_at',
        'cancellation_reason',
    ];

    protected function casts(): array
    {
        return [
            'traveler_count' => 'integer',
            'unit_price' => 'decimal:2',
            'total_amount' => 'decimal:2',
            'status' => BookingStatus::class,
            'payment_status' => PaymentStatus::class,
            'quote_snapshot' => 'array',
            'expires_at' => 'datetime',
            'confirmed_at' => 'datetime',
            'rejected_at' => 'datetime',
            'cancelled_at' => 'datetime',
            'completed_at' => 'datetime',
        ];
    }

    public function agency(): BelongsTo
    {
        return $this->belongsTo(Agency::class);
    }

    public function package(): BelongsTo
    {
        return $this->belongsTo(Package::class);
    }

    public function availability(): BelongsTo
    {
        return $this->belongsTo(PackageAvailability::class, 'package_availability_id');
    }

    public function traveler(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function customRequest(): BelongsTo
    {
        return $this->belongsTo(TravelRequest::class, 'custom_request_id');
    }

    public function customProposal(): BelongsTo
    {
        return $this->belongsTo(TravelRequestProposal::class, 'custom_proposal_id');
    }

    public function paymentTransactions(): HasMany
    {
        return $this->hasMany(BookingPaymentTransaction::class);
    }

    public function statusHistory(): HasMany
    {
        return $this->hasMany(BookingStatusHistory::class)->latest('created_at')->latest('id');
    }

    public function travelerIssues(): HasMany
    {
        return $this->hasMany(TravelerIssue::class);
    }

    public function review(): HasOne
    {
        return $this->hasOne(Review::class);
    }

    public function scopeWithPaymentSummary(Builder $query): Builder
    {
        return $query
            ->withSum(['paymentTransactions as successful_charge_amount' => fn (Builder $transactionQuery) => $transactionQuery
                ->where('status', PaymentTransactionStatus::SUCCEEDED->value)
                ->where('type', PaymentTransactionType::CHARGE->value)], 'amount')
            ->withSum(['paymentTransactions as successful_refund_amount' => fn (Builder $transactionQuery) => $transactionQuery
                ->where('status', PaymentTransactionStatus::SUCCEEDED->value)
                ->where('type', PaymentTransactionType::REFUND->value)], 'amount');
    }

    public function effectivePaymentStatus(): PaymentStatus
    {
        $charges = array_key_exists('successful_charge_amount', $this->attributes)
            ? (float) $this->attributes['successful_charge_amount']
            : (float) $this->paymentTransactions()
                ->where('status', PaymentTransactionStatus::SUCCEEDED->value)
                ->where('type', PaymentTransactionType::CHARGE->value)
                ->sum('amount');
        $refunds = array_key_exists('successful_refund_amount', $this->attributes)
            ? (float) $this->attributes['successful_refund_amount']
            : (float) $this->paymentTransactions()
                ->where('status', PaymentTransactionStatus::SUCCEEDED->value)
                ->where('type', PaymentTransactionType::REFUND->value)
                ->sum('amount');

        $chargeCents = (int) round($charges * 100);
        $refundCents = (int) round($refunds * 100);
        $netPaidCents = max(0, $chargeCents - $refundCents);
        $bookingTotalCents = (int) round((float) $this->total_amount * 100);

        return match (true) {
            $chargeCents > 0 && $netPaidCents === 0 => PaymentStatus::REFUNDED,
            $netPaidCents > 0 && $netPaidCents >= $bookingTotalCents => PaymentStatus::PAID,
            $netPaidCents > 0 => PaymentStatus::PARTIAL,
            default => PaymentStatus::UNPAID,
        };
    }

    public function isActiveReservation(): bool
    {
        return in_array($this->status, [BookingStatus::PENDING, BookingStatus::CONFIRMED], true)
            && ! ($this->status === BookingStatus::PENDING && $this->expires_at && $this->expires_at->lte(now()));
    }

    public function scopeReservesCapacity(Builder $query): Builder
    {
        return $query->where(function (Builder $reservationQuery): void {
            $reservationQuery->where('status', BookingStatus::CONFIRMED->value)
                ->orWhere(function (Builder $pendingQuery): void {
                    $pendingQuery->where('status', BookingStatus::PENDING->value)
                        ->where(function (Builder $expiryQuery): void {
                            $expiryQuery->whereNull('expires_at')->orWhere('expires_at', '>', now());
                        });
                });
        });
    }
}
