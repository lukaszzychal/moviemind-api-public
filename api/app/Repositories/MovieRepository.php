<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Models\Movie;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class MovieRepository
{
    /**
     * Search movies by text query and/or actor, director, year.
     * When only actor/director/year is set (no query), filters in DB by those criteria.
     *
     * @param  string|array|null  $actor  Single name or list of names (movie must have at least one matching ACTOR)
     */
    public function searchMovies(
        ?string $query,
        int $limit = 50,
        string|array|null $actor = null,
        ?string $director = null,
        ?int $year = null
    ): Collection {
        return Movie::query()
            ->when($query !== null && $query !== '', function ($builder) use ($query) {
                $driver = DB::getDriverName();
                $genresColumn = match ($driver) {
                    'pgsql' => 'genres::text',
                    'sqlite' => 'CAST(genres AS TEXT)',
                    default => 'genres',
                };

                $pattern = '%'.$query.'%';
                $builder->whereRaw('LOWER(title) LIKE LOWER(?)', [$pattern])
                    ->orWhereRaw('LOWER(director) LIKE LOWER(?)', [$pattern])
                    ->orWhereRaw("LOWER({$genresColumn}) LIKE LOWER(?)", [$pattern]);
            })
            ->when($actor !== null && $actor !== [], function ($builder) use ($actor) {
                $names = is_array($actor) ? $actor : [$actor];
                $builder->whereHas('people', function ($q) use ($names) {
                    $q->where('movie_person.role', 'ACTOR');
                    $q->where(function ($q2) use ($names) {
                        foreach ($names as $name) {
                            $q2->orWhereRaw('LOWER(people.name) LIKE LOWER(?)', ['%'.trim((string) $name).'%']);
                        }
                    });
                });
            })
            ->when($director !== null && $director !== '', function ($builder) use ($director) {
                $builder->whereRaw('LOWER(director) LIKE LOWER(?)', ['%'.trim($director).'%']);
            })
            ->when($year !== null, function ($builder) use ($year) {
                $builder->where('release_year', $year);
            })
            ->with(['defaultDescription', 'people'])
            ->withCount('descriptions')
            ->limit($limit)
            ->get();
    }

    public function findBySlugWithRelations(string $slug): ?Movie
    {
        // Try exact match first
        $movie = Movie::with(['descriptions', 'defaultDescription', 'people'])
            ->withCount('descriptions')
            ->where('slug', $slug)
            ->first();

        if ($movie) {
            return $movie;
        }

        // If slug doesn't contain year, try to find by title only
        // This handles backwards compatibility with slugs without year
        $parsed = Movie::parseSlug($slug);
        if ($parsed['year'] === null) {
            // Try to match by title slug (without year)
            // Note: This may return the first match if multiple movies share the same title
            $titleSlug = \Illuminate\Support\Str::slug($parsed['title']);

            return Movie::with(['descriptions', 'defaultDescription', 'people'])
                ->withCount('descriptions')
                ->whereRaw('slug LIKE ?', ["{$titleSlug}%"])
                ->orderBy('release_year', 'desc') // Return most recent by default
                ->first();
        }

        return null;
    }

    /**
     * Find all movies with the same title (different years).
     * Useful for disambiguation when multiple movies share a title.
     * Searches by title slug in both slug field and title field to find all matches.
     */
    public function findAllByTitleSlug(string $baseSlug): Collection
    {
        // Search by slug containing the base slug (e.g., "matrix" matches both "matrix-1973" and "armitage-dual-matrix-2002")
        // Also search by title containing the base slug (e.g., "matrix" matches "Armitage: Dual Matrix")
        $titleSlugPattern = '%'.str_replace('-', '%', $baseSlug).'%';
        $slugPattern = '%'.$baseSlug.'%';

        return Movie::with(['descriptions', 'defaultDescription'])
            ->withCount('descriptions')
            ->where(function ($query) use ($slugPattern, $titleSlugPattern) {
                $query->whereRaw('slug LIKE ?', [$slugPattern])
                    ->orWhereRaw('LOWER(title) LIKE LOWER(?)', [$titleSlugPattern]);
            })
            ->orderBy('release_year', 'desc')
            ->get();
    }

    /**
     * Find movie by slug for use in Jobs.
     * Handles ambiguous slugs (without year) by returning the most recent movie.
     * Also handles cases where request slug doesn't have year but database slug does.
     * Uses lighter relations than findBySlugWithRelations (only 'descriptions').
     *
     * @param  string  $slug  The slug to search for
     * @param  string|null  $existingId  Optional existing movie ID (UUID) to check first
     */
    public function findBySlugForJob(string $slug, ?string $existingId = null): ?Movie
    {
        // If existing ID is provided, try to find by ID first
        if ($existingId !== null) {
            $movie = Movie::with('descriptions')->find($existingId);
            if ($movie) {
                return $movie;
            }
        }

        // Try exact match first
        $movie = Movie::with('descriptions')->where('slug', $slug)->first();
        if ($movie) {
            return $movie;
        }

        // Parse slug to extract title and year
        $parsed = Movie::parseSlug($slug);
        $titleSlug = \Illuminate\Support\Str::slug($parsed['title']);

        // If slug from request doesn't contain year, try to find by title only
        // This handles ambiguous slugs and cases where job generated slug with year
        // but request slug doesn't have year
        if ($parsed['year'] === null) {
            // Return most recent movie with matching title slug
            // This will find movies like "the-matrix-1999" even if request slug is "the-matrix"
            return Movie::with('descriptions')
                ->whereRaw('slug LIKE ?', ["{$titleSlug}%"])
                ->orderBy('release_year', 'desc')
                ->first();
        }

        // Slug from request HAS year - check if movie exists with same title + year
        // This handles cases where slug format differs (e.g., "the-matrix" vs "the-matrix-1999")
        // but represents the same movie
        $year = $parsed['year'];
        // Check if movie exists with same title and year (even if slug format differs)
        $movie = Movie::with('descriptions')
            ->whereRaw('slug LIKE ?', ["{$titleSlug}-{$year}%"])
            ->where('release_year', $year)
            ->first();
        if ($movie) {
            return $movie;
        }

        return null;
    }

    /**
     * Find multiple movies by slugs.
     * Returns movies in the same order as requested slugs.
     * Handles duplicate slugs by deduplicating them.
     *
     * @param  array<int, string>  $slugs
     * @param  array<int, string>  $include  Relations to include (descriptions, people, genres)
     * @return Collection<int, Movie>
     */
    public function findBySlugs(array $slugs, array $include = []): Collection
    {
        // Deduplicate slugs while preserving order
        $uniqueSlugs = array_values(array_unique($slugs));

        if (empty($uniqueSlugs)) {
            return collect();
        }

        // Build relations array based on include parameter
        $relations = ['defaultDescription'];
        if (in_array('descriptions', $include, true)) {
            $relations[] = 'descriptions';
        }
        if (in_array('people', $include, true)) {
            $relations[] = 'people';
        }
        if (in_array('genres', $include, true)) {
            $relations[] = 'genres';
        }

        // Fetch movies
        $movies = Movie::with($relations)
            ->withCount('descriptions')
            ->whereIn('slug', $uniqueSlugs)
            ->get();

        // Create a map for quick lookup
        $moviesBySlug = $movies->keyBy('slug');

        // Return movies in the same order as requested slugs
        $orderedMovies = collect();
        foreach ($uniqueSlugs as $slug) {
            if ($moviesBySlug->has($slug)) {
                $orderedMovies->push($moviesBySlug->get($slug));
            }
        }

        return $orderedMovies;
    }
}
