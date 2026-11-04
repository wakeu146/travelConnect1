<?php

namespace App\Models;

use Database\Factories\TravelRequestProposalFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class TravelRequestProposal extends Model
{
    /** @use HasFactory<TravelRequestProposalFactory> */
    use HasFactory;

    protected $fillable = [
        'travel_request_id',
        'agency_id',
        'owner_id',
        'reference',
        'status',
        'price',
        'currency',
        'price_breakdown',
        'services_included',
        'services_excluded',
        'itinerary',
        'estimated_timeline',
        'message',
        'terms',
        'valid_until',
        'submitted_at',
        'withdrawn_at',
        'selected_at',
    ];

    protected function casts(): array
    {
        return [
            'price' => 'decimal:2',
            'price_breakdown' => 'array',
            'services_included' => 'array',
            'services_excluded' => 'array',
            'valid_until' => 'date',
            'submitted_at' => 'datetime',
            'withdrawn_at' => 'datetime',
            'selected_at' => 'datetime',
        ];
    }

    public function travelRequest(): BelongsTo
    {
        return $this->belongsTo(TravelRequest::class);
    }

    public function agency(): BelongsTo
    {
        return $this->belongsTo(Agency::class);
    }

    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_id');
    }

    public function revisions(): HasMany
    {
        return $this->hasMany(TravelRequestRevision::class)->latest('created_at')->latest('id');
    }

    public function booking(): HasOne
    {
        return $this->hasOne(Booking::class, 'custom_proposal_id');
    }
}
