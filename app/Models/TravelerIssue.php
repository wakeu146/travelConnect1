<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class TravelerIssue extends Model
{
    use HasFactory;

    protected $fillable = [
        'booking_id',
        'opened_by_admin_id',
        'assigned_admin_id',
        'type',
        'category',
        'subject',
        'description',
        'status',
        'priority',
        'resolution_reason',
        'resolved_at',
    ];

    protected function casts(): array
    {
        return [
            'resolved_at' => 'datetime',
        ];
    }

    public function traveler(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function booking(): BelongsTo
    {
        return $this->belongsTo(Booking::class);
    }

    public function openedByAdmin(): BelongsTo
    {
        return $this->belongsTo(User::class, 'opened_by_admin_id');
    }

    public function assignedAdmin(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_admin_id');
    }

    public function activities(): HasMany
    {
        return $this->hasMany(BookingDisputeActivity::class)->latest('created_at')->latest('id');
    }
}
