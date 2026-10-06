<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TrustedAdminDevice extends Model
{
    protected $fillable = [
        'user_id',
        'token_hash',
        'user_agent',
        'first_ip',
        'last_ip',
        'last_used_at',
    ];

    protected $hidden = ['token_hash'];

    protected function casts(): array
    {
        return [
            'last_used_at' => 'datetime',
        ];
    }
}
