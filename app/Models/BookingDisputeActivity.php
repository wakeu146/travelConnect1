<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BookingDisputeActivity extends Model
{
    protected $fillable = [
        'traveler_issue_id',
        'actor_id',
        'event_type',
        'from_status',
        'to_status',
        'note',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'metadata' => 'array',
        ];
    }

    public function issue(): BelongsTo
    {
        return $this->belongsTo(TravelerIssue::class, 'traveler_issue_id');
    }

    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_id');
    }
}
