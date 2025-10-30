<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Str;

class Movie extends Model
{
    use HasFactory;

    protected $fillable = [
        'title', 'slug',
        'release_year',
        'director',
        'genres',
        'default_description_id',
    ];

    protected $casts = [
        'genres' => 'array',
    ];

    /**
     * Generate a unique slug from title, release year, and optional director.
     * Handles duplicates automatically by adding suffixes.
     *
     * Format priority:
     * 1. "title-slug-YYYY" (if no duplicates)
     * 2. "title-slug-YYYY-director-slug" (if director available and helps)
     * 3. "title-slug-YYYY-2", "title-slug-YYYY-3", etc. (fallback)
     *
     * Examples:
     * - "bad-boys-1995" (unique)
     * - "the-prestige-2006-christopher-nolan" (if duplicate exists)
     * - "heat-1995-michael-mann" (if duplicate exists)
     * - "heat-1995-2" (if director not available or also duplicates)
     *
     * @param  string  $title  Movie title
     * @param  int|null  $releaseYear  Release year
     * @param  string|null  $director  Director name (optional, helps with disambiguation)
     * @param  int|null  $excludeId  Movie ID to exclude from duplicate check (for updates)
     */
    public static function generateSlug(
        string $title,
        ?int $releaseYear = null,
        ?string $director = null,
        ?int $excludeId = null
    ): string {
        $baseSlug = Str::slug($title);

        if ($releaseYear === null) {
            return $baseSlug;
        }

        // Try basic format first: title-slug-YYYY
        $candidateSlug = "{$baseSlug}-{$releaseYear}";

        // Check if this slug already exists (excluding current movie if updating)
        $query = static::where('slug', $candidateSlug);
        if ($excludeId !== null) {
            $query->where('id', '!=', $excludeId);
        }
        $existing = $query->exists();

        if (! $existing) {
            return $candidateSlug;
        }

        // Slug exists - try with director if available
        if ($director !== null && $director !== '') {
            $directorSlug = Str::slug($director);
            $candidateSlug = "{$baseSlug}-{$releaseYear}-{$directorSlug}";

            $query = static::where('slug', $candidateSlug);
            if ($excludeId !== null) {
                $query->where('id', '!=', $excludeId);
            }
            $existing = $query->exists();
            if (! $existing) {
                return $candidateSlug;
            }
        }

        // Still duplicate - use numeric suffix
        $counter = 2;
        do {
            $candidateSlug = "{$baseSlug}-{$releaseYear}-{$counter}";
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
     * Parse slug to extract title, year, and optional disambiguation.
     * Returns ['title' => string, 'year' => int|null, 'director' => string|null, 'suffix' => string|null]
     *
     * Handles formats:
     * - "title-slug-YYYY" → year extracted
     * - "title-slug-YYYY-director-slug" → year + director extracted
     * - "title-slug-YYYY-2" → year + numeric suffix extracted
     */
    public static function parseSlug(string $slug): array
    {
        // Pattern 1: title-slug-YYYY-director-slug (with director disambiguation)
        if (preg_match('/^(.+)-(\d{4})-(.+)$/', $slug, $matches)) {
            $basePart = $matches[1];
            $year = (int) $matches[2];
            $suffix = $matches[3];

            // Check if suffix looks like a director name (has letters, not just numbers)
            if (preg_match('/^[a-z-]+$/', $suffix)) {
                return [
                    'title' => str_replace('-', ' ', $basePart),
                    'year' => $year,
                    'director' => str_replace('-', ' ', $suffix),
                    'suffix' => null,
                ];
            }

            // Numeric suffix
            if (preg_match('/^\d+$/', $suffix)) {
                return [
                    'title' => str_replace('-', ' ', $basePart),
                    'year' => $year,
                    'director' => null,
                    'suffix' => $suffix,
                ];
            }
        }

        // Pattern 2: title-slug-YYYY (year at the end, no disambiguation)
        if (preg_match('/^(.+)-(\d{4})$/', $slug, $matches)) {
            return [
                'title' => str_replace('-', ' ', $matches[1]),
                'year' => (int) $matches[2],
                'director' => null,
                'suffix' => null,
            ];
        }

        // No year found
        return [
            'title' => str_replace('-', ' ', $slug),
            'year' => null,
            'director' => null,
            'suffix' => null,
        ];
    }

    public function descriptions(): HasMany
    {
        return $this->hasMany(MovieDescription::class);
    }

    public function defaultDescription(): HasOne
    {
        return $this->hasOne(MovieDescription::class, 'id', 'default_description_id');
    }

    public function genres(): BelongsToMany
    {
        return $this->belongsToMany(Genre::class, 'movie_genre');
    }

    public function people(): BelongsToMany
    {
        return $this->belongsToMany(Person::class, 'movie_person')
            ->withPivot(['role', 'character_name', 'job', 'billing_order']);
    }
}
