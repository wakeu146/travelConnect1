<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AdminAlertAction extends Model
{
    public const UPDATED_AT = null;

    protected $fillable = [
        'admin_alert_id',
        'actor_id',
        'action',
        'status_before',
        'status_after',
        'created_at',
    ];

    public function alert(): BelongsTo
    {
        return $this->belongsTo(AdminAlert::class, 'admin_alert_id');
    }

    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_id');
    }
}
