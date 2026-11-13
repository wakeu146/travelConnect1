<?php

namespace App\Models;

use App\Enums\ReviewStatus;
use App\Enums\VerificationStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Storage;

class Agency extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = ['user_id', 'company_name', 'description', 'license_number', 'verification_status', 'trust_score', 'address', 'phone', 'email', 'website', 'logo_path', 'cover_image_path'];

    protected function casts(): array
    {
        return [
            'verification_status' => VerificationStatus::class,
            'trust_score' => 'integer',
            'suspended_at' => 'datetime',
            'verified_at' => 'datetime',
        ];
    }

    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function documents(): HasMany
    {
        return $this->hasMany(AgencyDocument::class);
    }

    public function experiences(): HasMany
    {
        return $this->hasMany(AgencyExperience::class);
    }

    public function packages(): HasMany
    {
        return $this->hasMany(Package::class);
    }

    public function countries(): BelongsToMany
    {
        return $this->belongsToMany(Country::class, 'agency_country')->withTimestamps();
    }

    public function services(): BelongsToMany
    {
        return $this->belongsToMany(Service::class, 'agency_service')->withTimestamps();
    }

    public function reviews(): HasMany
    {
        return $this->hasMany(Review::class);
    }

    public function latestTrustScoreLog(): HasOne
    {
        return $this->hasOne(TrustScoreLog::class)->latestOfMany('calculated_at');
    }

    public function contentReports(): HasManyThrough
    {
        return $this->hasManyThrough(ContentReport::class, Review::class, 'agency_id', 'review_id');
    }

    public function travelerIssues(): HasManyThrough
    {
        return $this->hasManyThrough(TravelerIssue::class, Booking::class, 'agency_id', 'booking_id');
    }

    public function conversations(): HasMany
    {
        return $this->hasMany(Conversation::class);
    }

    public function inquiries(): HasMany
    {
        return $this->hasMany(Inquiry::class);
    }

    public function bookings(): HasMany
    {
        return $this->hasMany(Booking::class);
    }

    public function travelRequestRecipients(): HasMany
    {
        return $this->hasMany(TravelRequestRecipient::class);
    }

    public function travelRequestProposals(): HasMany
    {
        return $this->hasMany(TravelRequestProposal::class);
    }

    public function favorites(): HasMany
    {
        return $this->hasMany(Favorite::class);
    }

    public function trustScoreLogs(): HasMany
    {
        return $this->hasMany(TrustScoreLog::class);
    }

    public function moderationEvents(): HasMany
    {
        return $this->hasMany(AgencyModerationEvent::class);
    }

    public function recalculateTrustScoreFromPublishedReviews(): int
    {
        $publishedReviews = $this->reviews()
            ->where('status', ReviewStatus::PUBLISHED->value)
            ->where('is_hidden', false);
        $reviewCount = (clone $publishedReviews)->count();

        if ($reviewCount === 0) {
            $this->update(['trust_score' => 0]);

            return 0;
        }

        $averageRating = (float) (clone $publishedReviews)->avg('rating');
        $score = (int) round($averageRating * 20);

        $this->update(['trust_score' => $score]);
        $this->trustScoreLogs()->create([
            'score' => $score,
            'factors' => [
                'average_published_rating' => $averageRating,
                'published_review_count' => $reviewCount,
                'calculation' => 'average_rating_x_20',
            ],
            'calculated_at' => now(),
        ]);

        return $score;
    }

    public function logoUrl(): ?string
    {
        return $this->mediaUrl($this->logo_path);
    }

    public function coverImageUrl(): ?string
    {
        return $this->mediaUrl($this->cover_image_path);
    }

    private function mediaUrl(?string $path): ?string
    {
        $disk = Storage::disk('public');

        if (! $path || ! $disk->exists($path)) {
            return null;
        }

        $url = '/storage/'.ltrim($path, '/');
        $version = $this->updated_at?->format('U.u') ?? crc32($path);

        return $url.(str_contains($url, '?') ? '&' : '?').http_build_query(['v' => $version]);
    }

    public function scopeVerified(Builder $query): Builder
    {
        return $query
            ->where('verification_status', VerificationStatus::VERIFIED)
            ->where('operating_status', 'active');
    }
    // Example: Agency::with(['countries', 'services'])->withAvg(['reviews' => fn ($q) => $q->where('status', 'published')], 'rating')->get();
}
