<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\Locale as LocaleEnum;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * MovieLocale model.
 *
 * Stores localized metadata for movies (title, director, tagline, synopsis).
 * Each movie can have multiple locales, but only one record per locale.
 *
 * @property string $id UUIDv7 primary key
 * @property string $movie_id UUIDv7 foreign key
 * @property LocaleEnum $locale Locale code (pl-PL, en-US, etc.)
 * @property string|null $title_localized Localized movie title
 * @property string|null $director_localized Localized director name
 * @property string|null $tagline Localized tagline
 * @property string|null $synopsis Localized synopsis
 */
class MovieLocale extends Model
{
    use HasFactory, HasUuids;

    protected $fillable = [
        'movie_id',
        'locale',
        'title_localized',
        'director_localized',
        'tagline',
        'synopsis',
    ];

    protected $casts = [
        'locale' => LocaleEnum::class,
    ];

    /**
     * Get the movie that owns this locale.
     */
    public function movie(): BelongsTo
    {
        return $this->belongsTo(Movie::class);
    }

    /**
     * Scope a query to only include locales for a specific locale code.
     *
     * @param  \Illuminate\Database\Eloquent\Builder  $query
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeForLocale($query, string $locale)
    {
        return $query->where('locale', $locale);
    }
}
