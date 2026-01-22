<?php

namespace App\Services;

use App\Http\Resources\MovieResource;
use App\Models\Movie;
use App\Models\TmdbSnapshot;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class SimilarMoviesService
{
    public function __construct(
        private readonly TmdbVerificationService $tmdbVerificationService,
        private readonly HateoasService $hateoas
    ) {}

    /**
     * Get similar movies from TMDB API (cached for 24 hours).
     * Similar movies are NOT stored in database to prevent cascade effect.
     *
     * @return array<int, array<string, mixed>>
     */
    public function getSimilarMovies(Movie $movie, int $limit = 10): array
    {
        /** @var TmdbSnapshot|null $snapshot */
        $snapshot = $movie->tmdbSnapshot;
        if (! $snapshot) {
            return [];
        }

        // Cache similar movies for 24 hours (they can change, but not frequently)
        return Cache::remember(
            "movie_similar_{$movie->id}_{$limit}",
            now()->addHours(24),
            function () use ($movie, $snapshot, $limit) {
                try {
                    $movieDetails = $this->tmdbVerificationService->getMovieDetails($snapshot->tmdb_id);
                    $similarResults = $movieDetails['similar']['results'] ?? [];

                    // Limit to top N similar movies
                    $similarResults = array_slice($similarResults, 0, $limit);

                    // Transform TMDB results to API format
                    $transformed = [];
                    foreach ($similarResults as $similarMovie) {
                        $tmdbId = $similarMovie['id'] ?? null;
                        if (! $tmdbId) {
                            continue;
                        }

                        // Try to find existing movie in our database
                        /** @var Movie|null $existingMovie */
                        $existingMovie = Movie::where('tmdb_id', $tmdbId)->first();

                        if ($existingMovie) {
                            // Movie exists locally - return full movie data
                            $resource = MovieResource::make($existingMovie)->additional([
                                '_links' => $this->hateoas->movieLinks($existingMovie),
                            ]);

                            $movieData = $resource->resolve();
                            $movieData['relationship_type'] = 'SAME_UNIVERSE';
                            $movieData['relationship_label'] = 'Similar Movie';
                            $movieData['relationship_order'] = null;

                            $transformed[] = $movieData;
                        } else {
                            // Movie doesn't exist locally - return minimal TMDB data
                            $transformed[] = [
                                'id' => null,
                                'slug' => null,
                                'title' => $similarMovie['title'] ?? 'Unknown',
                                'release_year' => ! empty($similarMovie['release_date'])
                                    ? (int) substr($similarMovie['release_date'], 0, 4)
                                    : null,
                                'director' => null,
                                'genres' => [],
                                'description' => null,
                                'descriptions' => [],
                                'people' => [],
                                'relationship_type' => 'SAME_UNIVERSE',
                                'relationship_label' => 'Similar Movie',
                                'relationship_order' => null,
                                '_links' => [
                                    'self' => null, // Movie doesn't exist locally
                                    'generate' => [
                                        'href' => url('/api/v1/generate'),
                                        'method' => 'POST',
                                        'body' => [
                                            'entity_type' => 'MOVIE',
                                            'slug' => Movie::generateSlug(
                                                $similarMovie['title'] ?? 'Unknown',
                                                ! empty($similarMovie['release_date'])
                                                    ? (int) substr($similarMovie['release_date'], 0, 4)
                                                    : null
                                            ),
                                        ],
                                    ],
                                ],
                                '_meta' => [
                                    'exists_locally' => false,
                                    'tmdb_id' => $tmdbId,
                                    'source' => 'tmdb_similar',
                                ],
                            ];
                        }
                    }

                    return $transformed;
                } catch (\Throwable $e) {
                    Log::warning('Failed to fetch similar movies from TMDB', [
                        'movie_id' => $movie->id,
                        'tmdb_id' => $snapshot->tmdb_id,
                        'error' => $e->getMessage(),
                    ]);

                    return [];
                }
            }
        );
    }
}
