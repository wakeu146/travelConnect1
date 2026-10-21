<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class IntegrationFailure extends Model
{
    protected $fillable = [
        'integration',
        'operation',
        'safe_message',
        'exception_class',
    ];
}
