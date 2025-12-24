<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Str;

/**
 * TvShow model.
 *
 * @author MovieMind API Team
 *
 * @property string $id UUIDv7 primary key
 * @property int|null $tmdb_id
 */
class TvShow extends Model
{
    use HasFactory, HasUuids;

    protected $fillable = [
        'title',
        'slug',
        'first_air_date',
        'last_air_date',
        'number_of_seasons',
        'number_of_episodes',
        'genres',
        'show_type',
        'default_description_id',
        'tmdb_id',
    ];

    protected $casts = [
        'genres' => 'array',
        'first_air_date' => 'date',
        'last_air_date' => 'date',
    ];

    /**
     * Generate a unique slug from title and first air date.
     * Handles duplicates automatically by adding suffixes.
     *
     * Format priority:
     * 1. "title-slug-YYYY" (if no duplicates)
     * 2. "title-slug-YYYY-2", "title-slug-YYYY-3", etc. (fallback)
     *
     * Examples:
     * - "the-tonight-show-1954" (unique)
     * - "survivor-2000-2" (if duplicate exists)
     *
     * @param  string  $title  TV Show title
     * @param  int|null  $firstAirYear  First air year (extracted from first_air_date)
     * @param  string|null  $excludeId  TV Show ID (UUID) to exclude from duplicate check (for updates)
     */
    public static function generateSlug(
        string $title,
        ?int $firstAirYear = null,
        ?string $excludeId = null
    ): string {
        $baseSlug = Str::slug($title);

        if ($firstAirYear === null) {
            return $baseSlug;
        }

        // Try basic format first: title-slug-YYYY
        $candidateSlug = "{$baseSlug}-{$firstAirYear}";

        // Check if this slug already exists (excluding current tv show if updating)
        $query = static::where('slug', $candidateSlug);
        if ($excludeId !== null) {
            $query->where('id', '!=', $excludeId);
        }
        $existing = $query->exists();

        if (! $existing) {
            return $candidateSlug;
        }

        // Still duplicate - use numeric suffix
        $counter = 2;
        do {
            $candidateSlug = "{$baseSlug}-{$firstAirYear}-{$counter}";
            $query = static::where('slug', $candidateSlug);
            if ($excludeId !== null) {
                $query->where('id', '!=', $excludeId);
            }
            $existing = $query->exists();
            $counter++;
        } while ($existing && $counter < 100); // Safety limit

        return $candidateSlug;
    }

    /**
     * Parse slug to extract title and year.
     * Returns ['title' => string, 'year' => int|null, 'suffix' => string|null]
     *
     * Handles formats:
     * - "title-slug-YYYY" → year extracted
     * - "title-slug-YYYY-2" → year + numeric suffix extracted
     */
    public static function parseSlug(string $slug): array
    {
        // Pattern 1: title-slug-YYYY-suffix (with numeric suffix)
        if (preg_match('/^(.+)-(\d{4})-(\d+)$/', $slug, $matches)) {
            return [
                'title' => str_replace('-', ' ', $matches[1]),
                'year' => (int) $matches[2],
                'suffix' => $matches[3],
            ];
        }

        // Pattern 2: title-slug-YYYY (year at the end, no suffix)
        if (preg_match('/^(.+)-(\d{4})$/', $slug, $matches)) {
            return [
                'title' => str_replace('-', ' ', $matches[1]),
                'year' => (int) $matches[2],
                'suffix' => null,
            ];
        }

        // No year found
        return [
            'title' => str_replace('-', ' ', $slug),
            'year' => null,
            'suffix' => null,
        ];
    }

    public function descriptions(): HasMany
    {
        return $this->hasMany(TvShowDescription::class);
    }

    public function defaultDescription(): HasOne
    {
        return $this->hasOne(TvShowDescription::class, 'id', 'default_description_id');
    }

    public function people(): BelongsToMany
    {
        return $this->belongsToMany(Person::class, 'tv_show_person')
            ->withPivot(['role', 'character_name', 'job', 'billing_order']);
    }
}
