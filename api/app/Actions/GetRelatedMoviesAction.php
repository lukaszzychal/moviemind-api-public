<?php

declare(strict_types=1);

namespace App\Actions;

use App\Models\Movie;
use App\Models\MovieRelationship;
use App\Services\HateoasService;
use Illuminate\Http\Request;

/**
 * Action for retrieving related movies for a given movie.
 *
 * Supports filtering by relationship type:
 * - ?type=collection - Only collection relationships (sequels, prequels, etc.)
 * - ?type=similar - Only similar movies (from TMDB API, cached)
 * - ?type=all or no filter - Both collection and similar movies
 *
 * @author MovieMind API Team
 */
class GetRelatedMoviesAction
{
    public function __construct(
        private readonly HateoasService $hateoas
    ) {}

    /**
     * Handle the action.
     *
     * @param  Movie  $movie  The movie to get related movies for
     * @param  Request  $request  HTTP request with optional type filter
     * @return array Response data with movie, related movies, count, filters, and links
     *
     * @throws \InvalidArgumentException If invalid type filter is provided
     */
    public function handle(Movie $movie, Request $request): array
    {
        $typeFilter = $this->normalizeTypeFilter($request->query('type'));

        // Get collection relationships (from database)
        $collectionRelationships = $this->getCollectionRelationships($movie, $typeFilter);
        $collectionCount = $collectionRelationships->count();

        // Get similar movies (from TMDb - placeholder for now)
        $similarMovies = $this->getSimilarMovies($movie, $typeFilter);
        $similarCount = $similarMovies->count();

        // Format response - get relationships with order preserved
        $formattedMovies = [];

        // Format collection relationships
        foreach ($collectionRelationships as $relationship) {
            $relatedMovie = $relationship->movie_id === $movie->id
                ? $relationship->relatedMovie
                : $relationship->movie;

            if ($relatedMovie !== null) {
                $formattedMovies[] = [
                    'id' => $relatedMovie->id,
                    'slug' => $relatedMovie->slug,
                    'title' => $relatedMovie->title,
                    'release_year' => $relatedMovie->release_year,
                    'relationship_type' => $relationship->relationship_type->value,
                    'relationship_label' => $relationship->relationship_type->label(),
                    'relationship_order' => $relationship->order,
                ];
            }
        }

        // Add similar movies (if any) - for now empty
        // TODO: Add similar movies from TMDb when implemented

        // Build filters metadata
        $filters = [
            'type' => $typeFilter,
            'collection_count' => $collectionCount,
            'similar_count' => $similarCount,
        ];

        // Build HATEOAS links
        $links = [
            'self' => [
                'href' => url("/api/v1/movies/{$movie->slug}/related"),
            ],
            'movie' => [
                'href' => url("/api/v1/movies/{$movie->slug}"),
            ],
        ];

        return [
            'movie' => [
                'id' => $movie->id,
                'slug' => $movie->slug,
                'title' => $movie->title,
                'release_year' => $movie->release_year,
            ],
            'related_movies' => $formattedMovies,
            'count' => count($formattedMovies),
            'filters' => $filters,
            '_links' => $links,
        ];
    }

    /**
     * Normalize type filter parameter.
     *
     * @param  mixed  $type  Type filter from request
     * @return string Normalized type filter (collection, similar, or all)
     */
    private function normalizeTypeFilter(mixed $type): string
    {
        if ($type === null || $type === '') {
            return 'all';
        }

        $typeLower = strtolower((string) $type);

        return match ($typeLower) {
            'collection', 'similar', 'all' => $typeLower,
            default => throw new \InvalidArgumentException("Invalid type filter: {$type}. Allowed values: collection, similar, all"),
        };
    }

    /**
     * Get collection relationships with full relationship data.
     *
     * @param  Movie  $movie  The movie to get relationships for
     * @param  string  $typeFilter  Type filter (collection, similar, all)
     * @return \Illuminate\Database\Eloquent\Collection<int, MovieRelationship>
     */
    private function getCollectionRelationships(Movie $movie, string $typeFilter): \Illuminate\Database\Eloquent\Collection
    {
        if ($typeFilter === 'similar') {
            // If filtering by similar only, return empty collection
            return collect();
        }

        // Get relationships from database (both directions) ordered by order field
        return MovieRelationship::where(function ($q) use ($movie) {
            $q->where('movie_id', $movie->id)
                ->orWhere('related_movie_id', $movie->id);
        })
            ->with(['movie', 'relatedMovie'])
            ->orderBy('order')
            ->get();
    }

    /**
     * Get similar movies from TMDb (placeholder - returns empty for now).
     *
     * @param  Movie  $movie  The movie to get similar movies for
     * @param  string  $typeFilter  Type filter (collection, similar, all)
     * @return \Illuminate\Database\Eloquent\Collection<int, Movie>
     */
    private function getSimilarMovies(Movie $movie, string $typeFilter): \Illuminate\Database\Eloquent\Collection
    {
        if ($typeFilter === 'collection') {
            // If filtering by collection only, return empty collection
            return collect();
        }

        // TODO: Implement TMDb similar movies retrieval
        // For now, return empty collection
        return collect();
    }
}
