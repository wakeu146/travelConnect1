<?php

namespace App\Models;

use App\Enums\PackageStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Package extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'agency_id',
        'slug',
        'title',
        'summary',
        'description',
        'destination',
        'price_from',
        'duration_days',
        'status',
        'published_at',
        'is_featured',
    ];

    protected function casts(): array
    {
        return [
            'price_from' => 'decimal:2',
            'duration_days' => 'integer',
            'is_featured' => 'boolean',
            'status' => PackageStatus::class,
            'published_at' => 'datetime',
        ];
    }

    public function agency(): BelongsTo
    {
        return $this->belongsTo(Agency::class);
    }

    public function images(): HasMany
    {
        return $this->hasMany(PackageImage::class)->orderBy('sort_order')->orderBy('id');
    }

    public function availabilities(): HasMany
    {
        return $this->hasMany(PackageAvailability::class)->orderBy('departure_date');
    }

    public function bookings(): HasMany
    {
        return $this->hasMany(Booking::class);
    }

    public function moderationEvents(): HasMany
    {
        return $this->hasMany(PackageModerationEvent::class);
    }
}
