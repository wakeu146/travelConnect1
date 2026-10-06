<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AdminLoginEvent extends Model
{
    public const UPDATED_AT = null;

    protected $fillable = [
        'user_id',
        'email',
        'ip_address',
        'user_agent',
        'outcome',
        'failed_step',
        'device_hash',
        'created_at',
    ];
}
