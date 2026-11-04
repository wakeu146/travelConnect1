<?php

namespace App\Models;

use Database\Factories\TravelRequestFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class TravelRequest extends Model
{
    /** @use HasFactory<TravelRequestFactory> */
    use HasFactory;

    protected $fillable = [
        'user_id',
        'reference',
        'title',
        'origin',
        'destination',
        'destination_country',
        'travel_date',
        'return_date',
        'flexible_dates',
        'traveler_count',
        'purpose',
        'budget',
        'budget_min',
        'budget_max',
        'budget_currency',
        'budget_flexible',
        'description',
        'special_requirements',
        'sharing_mode',
        'status',
        'published_at',
        'expires_at',
        'hold_reason',
        'reviewed_at',
    ];

    protected function casts(): array
    {
        return [
            'travel_date' => 'date',
            'return_date' => 'date',
            'budget' => 'decimal:2',
            'budget_min' => 'decimal:2',
            'budget_max' => 'decimal:2',
            'flexible_dates' => 'boolean',
            'budget_flexible' => 'boolean',
            'published_at' => 'datetime',
            'expires_at' => 'datetime',
            'reviewed_at' => 'datetime',
        ];
    }

    public function traveler(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function recipients(): HasMany
    {
        return $this->hasMany(TravelRequestRecipient::class);
    }

    public function agencies(): BelongsToMany
    {
        return $this->belongsToMany(Agency::class, 'travel_request_recipients')
            ->withPivot(['available_at', 'first_viewed_at', 'last_viewed_at', 'read_at', 'status'])
            ->withTimestamps();
    }

    public function services(): BelongsToMany
    {
        return $this->belongsToMany(Service::class, 'travel_request_services')->withTimestamps();
    }

    public function proposals(): HasMany
    {
        return $this->hasMany(TravelRequestProposal::class);
    }

    public function selectedProposal(): HasOne
    {
        return $this->hasOne(TravelRequestProposal::class)->where('status', 'selected');
    }

    public function events(): HasMany
    {
        return $this->hasMany(TravelRequestEvent::class)->latest('created_at')->latest('id');
    }

    public function adminNotes(): HasMany
    {
        return $this->hasMany(TravelRequestAdminNote::class)->latest('created_at')->latest('id');
    }

    public function booking(): HasOne
    {
        return $this->hasOne(Booking::class, 'custom_request_id');
    }

    public function scopeOpen(Builder $query): Builder
    {
        return $query->where('status', 'open')->whereDoesntHave('booking');
    }
}
