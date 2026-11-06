<?php

namespace App\Models;

use App\Enums\ReviewStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Review extends Model
{
    use HasFactory;

    protected $fillable = ['agency_id', 'user_id', 'booking_id', 'rating', 'comment', 'status', 'agency_response', 'agency_responded_at', 'is_hidden'];

    protected function casts(): array
    {
        return ['rating' => 'integer', 'status' => ReviewStatus::class, 'agency_responded_at' => 'datetime', 'is_hidden' => 'boolean'];
    }

    public function agency(): BelongsTo
    {
        return $this->belongsTo(Agency::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function booking(): BelongsTo
    {
        return $this->belongsTo(Booking::class);
    }

    public function reports(): HasMany
    {
        return $this->hasMany(ContentReport::class);
    }

    public function events(): HasMany
    {
        return $this->hasMany(TrustSafetyEvent::class, 'source_id')
            ->where('source_type', 'review')
            ->latest();
    }
}
