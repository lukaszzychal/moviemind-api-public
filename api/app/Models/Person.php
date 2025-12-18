<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\Pivot;
use Illuminate\Support\Str;

/**
 * @property-read Pivot|null $pivot
 */
class Person extends Model
{
    use HasFactory;

    protected $fillable = [
        'name', 'slug', 'birth_date', 'birthplace', 'tmdb_id',
    ];

    protected $casts = [
        'birth_date' => 'date',
    ];

    public function movies(): BelongsToMany
    {
        return $this->belongsToMany(Movie::class, 'movie_person')
            ->withPivot(['role', 'character_name', 'job', 'billing_order']);
    }

    public function bios(): HasMany
    {
        return $this->hasMany(PersonBio::class);
    }

    public function defaultBio(): HasOne
    {
        return $this->hasOne(PersonBio::class, 'id', 'default_bio_id');
    }

    /**
     * Generate a unique slug from person data.
     * Handles ambiguous names (same name, different people) by adding birth year or birthplace.
     *
     * @param  string  $name  Person's full name
     * @param  string|null  $birthDate  Birth date (YYYY-MM-DD format)
     * @param  string|null  $birthplace  Birthplace
     * @param  int|null  $excludeId  Person ID to exclude from duplicate check (for updates)
     * @return string Generated unique slug
     */
    public static function generateSlug(
        string $name,
        ?string $birthDate = null,
        ?string $birthplace = null,
        ?int $excludeId = null
    ): string {
        $baseSlug = Str::slug($name);

        // Extract year from birth date if available
        $birthYear = null;
        if ($birthDate !== null) {
            $parsedDate = \DateTime::createFromFormat('Y-m-d', $birthDate);
            if ($parsedDate !== false) {
                $birthYear = (int) $parsedDate->format('Y');
            }
        }

        // Try basic format first: name-slug
        $candidateSlug = $baseSlug;

        // Check if this slug already exists (excluding current person if updating)
        $query = static::where('slug', $candidateSlug);
        if ($excludeId !== null) {
            $query->where('id', '!=', $excludeId);
        }
        $existing = $query->exists();

        if (! $existing) {
            return $candidateSlug;
        }

        // Slug exists - try with birth year if available
        if ($birthYear !== null) {
            $candidateSlug = "{$baseSlug}-{$birthYear}";

            $query = static::where('slug', $candidateSlug);
            if ($excludeId !== null) {
                $query->where('id', '!=', $excludeId);
            }
            $existing = $query->exists();

            if (! $existing) {
                return $candidateSlug;
            }

            // Still exists - try with birthplace if available
            if ($birthplace !== null && $birthplace !== '') {
                $birthplaceSlug = Str::slug($birthplace);
                $candidateSlug = "{$baseSlug}-{$birthYear}-{$birthplaceSlug}";

                $query = static::where('slug', $candidateSlug);
                if ($excludeId !== null) {
                    $query->where('id', '!=', $excludeId);
                }
                $existing = $query->exists();

                if (! $existing) {
                    return $candidateSlug;
                }
            }
        }

        // Still duplicate - use numeric suffix
        $counter = 2;
        do {
            $candidateSlug = $birthYear !== null
                ? "{$baseSlug}-{$birthYear}-{$counter}"
                : "{$baseSlug}-{$counter}";
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
     * Parse slug to extract name and optional disambiguation.
     * For persons, slug format is simpler than movies (no year in base format).
     *
     * @param  string  $slug  The slug to parse
     * @return array{name: string, birth_year: int|null, birthplace: string|null, suffix: string|null}
     */
    public static function parseSlug(string $slug): array
    {
        // Pattern 1: name-slug-YYYY-birthplace-slug (with birth year and birthplace)
        if (preg_match('/^(.+)-(\d{4})-(.+)$/', $slug, $matches)) {
            $basePart = $matches[1];
            $year = (int) $matches[2];
            $suffix = $matches[3];

            // Check if suffix looks like a birthplace (has letters, not just numbers)
            if (preg_match('/^[a-z-]+$/', $suffix)) {
                return [
                    'name' => str_replace('-', ' ', $basePart),
                    'birth_year' => $year,
                    'birthplace' => str_replace('-', ' ', $suffix),
                    'suffix' => null,
                ];
            }

            // Numeric suffix
            if (preg_match('/^\d+$/', $suffix)) {
                return [
                    'name' => str_replace('-', ' ', $basePart),
                    'birth_year' => $year,
                    'birthplace' => null,
                    'suffix' => $suffix,
                ];
            }
        }

        // Pattern 2: name-slug-YYYY (year at the end, no disambiguation)
        if (preg_match('/^(.+)-(\d{4})$/', $slug, $matches)) {
            return [
                'name' => str_replace('-', ' ', $matches[1]),
                'birth_year' => (int) $matches[2],
                'birthplace' => null,
                'suffix' => null,
            ];
        }

        // Pattern 3: name-slug-N (numeric suffix, no year)
        if (preg_match('/^(.+)-(\d+)$/', $slug, $matches)) {
            return [
                'name' => str_replace('-', ' ', $matches[1]),
                'birth_year' => null,
                'birthplace' => null,
                'suffix' => $matches[2],
            ];
        }

        // No disambiguation found - just name
        return [
            'name' => str_replace('-', ' ', $slug),
            'birth_year' => null,
            'birthplace' => null,
            'suffix' => null,
        ];
    }
}
