<?php

namespace App\Models;

use Database\Factories\TravelRequestRecipientFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TravelRequestRecipient extends Model
{
    /** @use HasFactory<TravelRequestRecipientFactory> */
    use HasFactory;

    protected $fillable = [
        'travel_request_id',
        'agency_id',
        'available_at',
        'first_viewed_at',
        'last_viewed_at',
        'read_at',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'available_at' => 'datetime',
            'first_viewed_at' => 'datetime',
            'last_viewed_at' => 'datetime',
            'read_at' => 'datetime',
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
}
