<?php

namespace App\Models;

use App\Enums\BookingStatus;
use App\Enums\PaymentStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Booking extends Model
{
    use HasFactory;

    protected $fillable = [
        'agency_id',
        'package_id',
        'package_availability_id',
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

    public function isActiveReservation(): bool
    {
        return in_array($this->status, [BookingStatus::PENDING, BookingStatus::CONFIRMED], true)
            && ! ($this->status === BookingStatus::PENDING && $this->expires_at && $this->expires_at->isPast());
    }
}
