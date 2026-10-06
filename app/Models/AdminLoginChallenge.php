<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AdminLoginChallenge extends Model
{
    protected $fillable = [
        'user_id',
        'session_hash',
        'code_hash',
        'attempts',
        'expires_at',
        'consumed_at',
        'invalidated_at',
    ];

    protected $hidden = ['session_hash', 'code_hash'];

    protected function casts(): array
    {
        return [
            'attempts' => 'integer',
            'expires_at' => 'datetime',
            'consumed_at' => 'datetime',
            'invalidated_at' => 'datetime',
        ];
    }
}
