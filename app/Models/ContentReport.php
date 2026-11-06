<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ContentReport extends Model
{
    protected $fillable = [
        'review_id',
        'reporter_id',
        'reason',
        'evidence',
        'status',
        'assigned_to',
        'outcome_reason',
    ];

    public function review(): BelongsTo
    {
        return $this->belongsTo(Review::class);
    }

    public function reporter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reporter_id');
    }

    public function assignee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }

    public function events(): HasMany
    {
        return $this->hasMany(TrustSafetyEvent::class, 'source_id')
            ->where('source_type', 'content_report')
            ->latest();
    }
}
