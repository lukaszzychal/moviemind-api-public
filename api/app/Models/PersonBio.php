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
        // context_tag is handled by getter to allow fallback for invalid values
        'origin' => DescriptionOrigin::class,
    ];

    /**
     * Get the context_tag attribute with fallback for invalid values.
     * If database contains invalid enum value (e.g., "DEFAULT_2"), fallback to DEFAULT.
     */
    public function getContextTagAttribute($value): ?ContextTag
    {
        // During creation, $value might be null or the model might not be saved yet
        if ($value === null || ! $this->exists) {
            return null;
        }

        // If already a ContextTag enum, return as-is
        if ($value instanceof ContextTag) {
            return $value;
        }

        // Try to cast string to enum
        try {
            return ContextTag::from($value);
        } catch (\ValueError $e) {
            // Invalid enum value (e.g., "DEFAULT_2") - fallback to DEFAULT
            \Illuminate\Support\Facades\Log::warning('Invalid context_tag value in database, falling back to DEFAULT', [
                'person_id' => $this->person_id,
                'bio_id' => $this->id,
                'invalid_value' => $value,
            ]);

            return ContextTag::DEFAULT;
        }
    }

    /**
     * Convert the model instance to an array, ensuring context_tag is serialized as string.
     */
    public function toArray(): array
    {
        $array = parent::toArray();

        // Convert context_tag enum to string value for JSON serialization
        if (isset($array['context_tag']) && $array['context_tag'] instanceof ContextTag) {
            $array['context_tag'] = $array['context_tag']->value;
        }

        return $array;
    }

    public function person(): BelongsTo
    {
        return $this->belongsTo(Person::class);
    }
}
