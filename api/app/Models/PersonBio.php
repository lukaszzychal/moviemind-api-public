<?php

namespace App\Models;

use App\Enums\ContextTag;
use App\Enums\DescriptionOrigin;
use App\Enums\Locale as LocaleEnum;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PersonBio extends Model
{
    use HasFactory;

    protected $fillable = [
        'person_id', 'locale', 'text', 'context_tag', 'origin', 'ai_model',
    ];

    protected $casts = [
        'locale' => LocaleEnum::class,
        'context_tag' => ContextTag::class,
        'origin' => DescriptionOrigin::class,
    ];

    public function person(): BelongsTo
    {
        return $this->belongsTo(Person::class);
    }
}


