<?php

namespace App\Models;

use App\Enums\PackageAvailabilityStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PackageAvailability extends Model
{
    use HasFactory;

    protected $table = 'package_availability';

    protected $fillable = [
        'package_id',
        'departure_date',
        'capacity',
        'status',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'departure_date' => 'date',
            'capacity' => 'integer',
            'status' => PackageAvailabilityStatus::class,
        ];
    }

    public function package(): BelongsTo
    {
        return $this->belongsTo(Package::class);
    }

    public function bookings(): HasMany
    {
        return $this->hasMany(Booking::class);
    }
}
