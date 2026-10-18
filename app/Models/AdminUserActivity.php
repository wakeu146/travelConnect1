<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AdminUserActivity extends Model
{
    public const UPDATED_AT = null;

    protected $fillable = [
        'user_id',
        'actor_id',
        'action',
        'outcome',
        'reason',
        'note',
        'created_at',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_id');
    }
}
