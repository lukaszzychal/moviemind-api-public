<?php

declare(strict_types=1);

namespace App\Services;

use App\Http\Resources\MovieResource;
use App\Models\Movie;
use App\Models\TmdbSnapshot;
use App\Repositories\MovieRepository;
use Illuminate\Support\Collection;

/**
 * Service for retrieving movie collections from TMDb snapshots.
 *
 * @author MovieMind API Team
 */
class MovieCollectionService
{
    public function __construct(
        private readonly MovieRepository $movieRepository
    ) {}

    /**
     * Get collection for a movie by slug.
     *
     * @return array{collection: array{name: string, tmdb_collection_id: int, count: int}, movies: array}|null Returns collection data or null if not found
     */
    public function getCollectionByMovieSlug(string $slug): ?array
    {
        // Find movie
        $movie = $this->movieRepository->findBySlugWithRelations($slug);
        if (! $movie) {
            return null;
        }

        // Find TMDb snapshot
        /** @var TmdbSnapshot|null $snapshot */
        $snapshot = $movie->tmdbSnapshot;
        if (! $snapshot) {
            return null;
        }

        // Check if movie belongs to a collection
        /** @var array<string, mixed> $rawData */
        $rawData = $snapshot->raw_data;
        if (empty($rawData['belongs_to_collection']) || ! isset($rawData['belongs_to_collection']['id'])) {
            return null;
        }

        $collectionId = $rawData['belongs_to_collection']['id'];
        $collectionName = $rawData['belongs_to_collection']['name'] ?? 'Unknown Collection';

        // Find all movies in the same collection
        $collectionMovies = $this->findMoviesInCollection($collectionId);

        return [
            'collection' => [
                'name' => $collectionName,
                'tmdb_collection_id' => $collectionId,
                'count' => $collectionMovies->count(),
            ],
            'movies' => $collectionMovies->map(function (Movie $movie) {
                return MovieResource::make($movie)->resolve();
            })->toArray(),
        ];
    }

    /**
     * Find all movies in a collection by TMDb collection ID.
     *
     * @return Collection<int, Movie>
     */
    private function findMoviesInCollection(int $collectionId): Collection
    {
        // Find all snapshots with the same collection_id
        $snapshots = TmdbSnapshot::where('entity_type', 'MOVIE')
            ->whereJsonContains('raw_data->belongs_to_collection->id', $collectionId)
            ->get();

        // Extract movie IDs
        $movieIds = $snapshots->pluck('entity_id')->unique()->toArray();

        if (empty($movieIds)) {
            return collect();
        }

        // Load movies with relations
        return Movie::whereIn('id', $movieIds)
            ->with(['defaultDescription', 'descriptions'])
            ->get();
    }
}
