<?php

namespace App\Models;

use Database\Factories\TravelRequestRevisionFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TravelRequestRevision extends Model
{
    /** @use HasFactory<TravelRequestRevisionFactory> */
    use HasFactory;

    protected $fillable = ['travel_request_proposal_id', 'actor_id', 'snapshot'];

    protected function casts(): array
    {
        return ['snapshot' => 'array'];
    }

    public function proposal(): BelongsTo
    {
        return $this->belongsTo(TravelRequestProposal::class, 'travel_request_proposal_id');
    }

    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_id');
    }
}
