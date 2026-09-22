<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PackageItineraryDay extends Model
{
    protected $fillable = ['package_id', 'day_number', 'title', 'description', 'location', 'meals', 'overnight'];

    protected function casts(): array
    {
        return [
            'day_number' => 'integer',
        ];
    }

    public function package(): BelongsTo
    {
        return $this->belongsTo(Package::class);
    }
}
