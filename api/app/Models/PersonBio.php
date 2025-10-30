<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PersonBio extends Model
{
    use HasFactory;

    protected $fillable = [
        'person_id', 'locale', 'text', 'context_tag', 'origin', 'ai_model',
    ];

    public function person(): BelongsTo
    {
        return $this->belongsTo(Person::class);
    }
}


