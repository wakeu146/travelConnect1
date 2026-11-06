<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TrustSafetyEvent extends Model
{
    protected $fillable = [
        'source_type',
        'source_id',
        'actor_id',
        'action',
        'status_before',
        'status_after',
        'reason',
    ];

    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_id');
    }
}
