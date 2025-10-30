<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MovieDescription extends Model
{
    use HasFactory;

    protected $fillable = [
        'movie_id',
        'locale',
        'text',
        'context_tag',
        'origin',
        'ai_model',
    ];

    public function movie(): BelongsTo
    {
        return $this->belongsTo(Movie::class);
    }
}


