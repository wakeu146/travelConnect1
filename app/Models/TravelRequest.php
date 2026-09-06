<?php

namespace App\Models;

use Database\Factories\TravelRequestFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TravelRequest extends Model
{
    /** @use HasFactory<TravelRequestFactory> */
    use HasFactory;

    protected $fillable = ['user_id', 'title', 'destination', 'travel_date', 'budget', 'description', 'status'];

    protected function casts(): array
    {
        return ['travel_date' => 'date', 'budget' => 'decimal:2'];
    }

    public function traveler(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function scopeOpen(Builder $query): Builder
    {
        return $query->where('status', 'open');
    }
}