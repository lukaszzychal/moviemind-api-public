<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ActorBio extends Model
{
    use HasFactory;

    protected $fillable = [
        'actor_id',
        'locale',
        'text',
        'context_tag',
        'origin',
        'ai_model',
    ];

    public function actor(): BelongsTo
    {
        return $this->belongsTo(Actor::class);
    }
}


