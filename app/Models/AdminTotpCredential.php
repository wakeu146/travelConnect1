<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AdminTotpCredential extends Model
{
    protected $fillable = [
        'user_id',
        'secret',
        'recovery_code_hashes',
        'verified_at',
        'last_used_counter',
    ];

    protected $hidden = [
        'secret',
        'recovery_code_hashes',
    ];

    protected function casts(): array
    {
        return [
            'secret' => 'encrypted',
            'recovery_code_hashes' => 'array',
            'verified_at' => 'datetime',
            'last_used_counter' => 'integer',
        ];
    }
}
