<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AgencyModerationEvent extends Model
{
    protected $fillable = [
        'agency_id',
        'actor_id',
        'action',
        'previous_verification_status',
        'new_verification_status',
        'previous_operating_status',
        'new_operating_status',
        'reason',
    ];

    public function agency(): BelongsTo
    {
        return $this->belongsTo(Agency::class);
    }

    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_id');
    }
}
