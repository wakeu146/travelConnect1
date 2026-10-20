<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AdminAlert extends Model
{
    protected $fillable = [
        'source_type',
        'source_id',
        'status',
        'acknowledged_by',
        'acknowledged_at',
        'resolved_by',
        'resolved_at',
    ];

    protected function casts(): array
    {
        return [
            'acknowledged_at' => 'datetime',
            'resolved_at' => 'datetime',
        ];
    }

    public function actions(): HasMany
    {
        return $this->hasMany(AdminAlertAction::class)
            ->orderByDesc('created_at')
            ->orderByDesc('id');
    }
}
