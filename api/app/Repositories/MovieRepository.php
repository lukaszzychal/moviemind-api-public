<?php

namespace App\Repositories;

use App\Models\Movie;
use Illuminate\Support\Collection;

class MovieRepository
{
    public function searchMovies(?string $query, int $limit = 50): Collection
    {
        return Movie::query()
            ->when($query, function ($builder) use ($query) {
                $builder->where('title', 'ILIKE', "%$query%")
                    ->orWhere('director', 'ILIKE', "%$query%")
                    ->orWhereHas('genres', function ($qg) use ($query) {
                        $qg->where('name', 'ILIKE', "%$query%");
                    });
            })
            ->with(['defaultDescription', 'genres', 'people'])
            ->withCount('descriptions')
            ->limit($limit)
            ->get();
    }

    public function findBySlugWithRelations(string $slug): ?Movie
    {
        // Try exact match first
        $movie = Movie::with(['descriptions', 'defaultDescription'])
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

            return Movie::with(['descriptions', 'defaultDescription'])
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
     */
    public function findAllByTitleSlug(string $baseSlug): Collection
    {
        return Movie::with(['descriptions', 'defaultDescription'])
            ->withCount('descriptions')
            ->whereRaw('slug LIKE ?', ["{$baseSlug}%"])
            ->orderBy('release_year', 'desc')
            ->get();
    }
}
