<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Enums\RelationshipType;
use App\Models\Movie;
use App\Models\MovieRelationship;
use App\Services\TmdbMovieCreationService;
use App\Services\TmdbVerificationService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

/**
 * Job to synchronize movie relationships (sequels, prequels, remakes, etc.) from TMDb.
 * This job runs asynchronously after a movie is created or metadata is synced.
 *
 * @author MovieMind API Team
 */
class SyncMovieRelationshipsJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public int $timeout = 120;

    public function __construct(
        public int $movieId
    ) {}

    /**
     * Execute the job.
     */
    public function handle(
        TmdbVerificationService $tmdbVerificationService,
        TmdbMovieCreationService $tmdbMovieCreationService
    ): void {
        Log::info('SyncMovieRelationshipsJob started', [
            'movie_id' => $this->movieId,
            'attempt' => $this->attempts(),
        ]);

        $movie = Movie::find($this->movieId);
        if (! $movie) {
            Log::warning('SyncMovieRelationshipsJob: Movie not found', [
                'movie_id' => $this->movieId,
            ]);

            return;
        }

        /** @var \App\Models\TmdbSnapshot|null $snapshot */
        $snapshot = $movie->tmdbSnapshot;
        if (! $snapshot) {
            Log::warning('SyncMovieRelationshipsJob: No TMDb snapshot found for movie', [
                'movie_id' => $this->movieId,
            ]);

            return;
        }

        $tmdbId = $snapshot->tmdb_id;

        // Ensure movie has tmdb_id set (for relationship matching)
        if (! $movie->tmdb_id) {
            $movie->update(['tmdb_id' => $tmdbId]);
            Log::info('SyncMovieRelationshipsJob: Updated movie tmdb_id from snapshot', [
                'movie_id' => $this->movieId,
                'tmdb_id' => $tmdbId,
            ]);
        }

        $movieDetails = $tmdbVerificationService->getMovieDetails($tmdbId);

        if (empty($movieDetails)) {
            Log::warning('SyncMovieRelationshipsJob: No TMDb details found for movie', [
                'movie_id' => $this->movieId,
                'tmdb_id' => $tmdbId,
            ]);

            return;
        }

        // Sync relationships from collection (sequels, prequels, series)
        if (! empty($movieDetails['belongs_to_collection'])) {
            $this->syncCollectionRelationships($movie, $snapshot, $movieDetails['belongs_to_collection'], $tmdbVerificationService, $tmdbMovieCreationService);
        }

        // Sync similar movies (SAME_UNIVERSE)
        if (! empty($movieDetails['similar']['results'])) {
            $this->syncSimilarMovies($movie, $movieDetails['similar']['results'], $tmdbVerificationService, $tmdbMovieCreationService);
        }

        Log::info('SyncMovieRelationshipsJob finished', [
            'movie_id' => $this->movieId,
        ]);
    }

    /**
     * Sync relationships from TMDb collection (sequels, prequels, series).
     *
     * @param  array{id: int, name: string}  $collection
     */
    /**
     * @param  array<string, mixed>  $collection
     */
    private function syncCollectionRelationships(
        Movie $movie,
        \App\Models\TmdbSnapshot $snapshot,
        array $collection,
        TmdbVerificationService $tmdbVerificationService,
        TmdbMovieCreationService $tmdbMovieCreationService
    ): void {
        $collectionId = $collection['id'];
        if (! $collectionId) {
            return;
        }

        try {
            $collectionData = $tmdbVerificationService->getCollectionDetails($collectionId);
            if (empty($collectionData) || ! isset($collectionData['parts']) || empty($collectionData['parts'])) {
                Log::info('SyncMovieRelationshipsJob: Collection has no parts or empty', [
                    'movie_id' => $movie->id,
                    'collection_id' => $collectionId,
                    'collection_data_keys' => array_keys($collectionData),
                ]);

                return;
            }

            // Use tmdb_id from snapshot (more reliable than movie->tmdb_id which may be NULL)
            $currentTmdbId = $snapshot->tmdb_id;
            $parts = $collectionData['parts'];
            $currentIndex = null;

            // Find current movie's position in collection
            foreach ($parts as $index => $part) {
                if (isset($part['id']) && $part['id'] === $currentTmdbId) {
                    $currentIndex = $index;
                    break;
                }
            }

            if ($currentIndex === null) {
                return;
            }

            // Create relationships based on position
            foreach ($parts as $index => $part) {
                if ($index === $currentIndex) {
                    continue; // Skip self
                }

                $relatedTmdbId = $part['id'] ?? null;
                if (! $relatedTmdbId) {
                    continue;
                }

                // Find or create related movie
                $relatedMovie = Movie::where('tmdb_id', $relatedTmdbId)->first();
                if (! $relatedMovie) {
                    // Movie doesn't exist yet - create it from TMDB data
                    $relatedTmdbData = [
                        'id' => $relatedTmdbId,
                        'title' => ($part['title'] ?? '') ?: 'Unknown',
                        'release_date' => $part['release_date'] ?? null,
                        'overview' => null, // Overview not available in collection parts
                        'director' => null, // Director not available in collection parts
                    ];

                    // Generate slug for the related movie
                    $releaseYear = ! empty($relatedTmdbData['release_date'])
                        ? (int) substr($relatedTmdbData['release_date'], 0, 4)
                        : null;
                    $generatedSlug = Movie::generateSlug($relatedTmdbData['title'], $releaseYear, null);

                    $relatedMovie = $tmdbMovieCreationService->createFromTmdb($relatedTmdbData, $generatedSlug);

                    if (! $relatedMovie) {
                        Log::warning('SyncMovieRelationshipsJob: Failed to create related movie', [
                            'tmdb_id' => $relatedTmdbId,
                            'title' => $relatedTmdbData['title'],
                        ]);

                        continue;
                    }

                    Log::info('SyncMovieRelationshipsJob: Created related movie from collection', [
                        'movie_id' => $movie->id,
                        'related_movie_id' => $relatedMovie->id,
                        'related_tmdb_id' => $relatedTmdbId,
                    ]);
                }

                // Determine relationship type based on position
                $relationshipType = $this->determineRelationshipType($currentIndex, $index, count($parts));

                // Create relationship if it doesn't exist
                MovieRelationship::firstOrCreate(
                    [
                        'movie_id' => $movie->id,
                        'related_movie_id' => $relatedMovie->id,
                        'relationship_type' => $relationshipType,
                    ],
                    [
                        'order' => abs($index - $currentIndex),
                    ]
                );
            }
        } catch (\Throwable $e) {
            Log::warning('SyncMovieRelationshipsJob: Failed to sync collection relationships', [
                'movie_id' => $movie->id,
                'collection_id' => $collectionId,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Sync similar movies (SAME_UNIVERSE relationship).
     *
     * @param  array<int, array<string, mixed>>  $similarMovies
     */
    private function syncSimilarMovies(
        Movie $movie,
        array $similarMovies,
        TmdbVerificationService $tmdbVerificationService,
        TmdbMovieCreationService $tmdbMovieCreationService
    ): void {
        // Limit to top 10 similar movies
        $similarMovies = array_slice($similarMovies, 0, 10);

        foreach ($similarMovies as $similarMovie) {
            $tmdbId = $similarMovie['id'] ?? null;
            if (! $tmdbId) {
                continue;
            }

            // Find or create related movie
            $relatedMovie = Movie::where('tmdb_id', $tmdbId)->first();
            if (! $relatedMovie) {
                // Movie doesn't exist yet - create it from TMDB data
                $relatedTmdbData = [
                    'id' => $tmdbId,
                    'title' => ($similarMovie['title'] ?? '') ?: 'Unknown',
                    'release_date' => $similarMovie['release_date'] ?? null,
                    'overview' => null, // Overview not available in similar movies list
                    'director' => null, // Director not available in similar movies list
                ];

                // Generate slug for the related movie
                $releaseYear = ! empty($relatedTmdbData['release_date'])
                    ? (int) substr($relatedTmdbData['release_date'], 0, 4)
                    : null;
                $generatedSlug = Movie::generateSlug($relatedTmdbData['title'], $releaseYear, null);

                $relatedMovie = $tmdbMovieCreationService->createFromTmdb($relatedTmdbData, $generatedSlug);

                if (! $relatedMovie) {
                    Log::warning('SyncMovieRelationshipsJob: Failed to create similar movie', [
                        'tmdb_id' => $tmdbId,
                        'title' => $relatedTmdbData['title'],
                    ]);

                    continue;
                }

                Log::info('SyncMovieRelationshipsJob: Created similar movie', [
                    'movie_id' => $movie->id,
                    'related_movie_id' => $relatedMovie->id,
                    'related_tmdb_id' => $tmdbId,
                ]);
            }

            // Create SAME_UNIVERSE relationship if it doesn't exist
            MovieRelationship::firstOrCreate(
                [
                    'movie_id' => $movie->id,
                    'related_movie_id' => $relatedMovie->id,
                    'relationship_type' => RelationshipType::SAME_UNIVERSE,
                ]
            );
        }
    }

    /**
     * Determine relationship type based on positions in collection.
     */
    private function determineRelationshipType(int $currentIndex, int $relatedIndex, int $totalParts): RelationshipType
    {
        if ($relatedIndex < $currentIndex) {
            // Related movie comes before current - it's a PREQUEL
            return RelationshipType::PREQUEL;
        }

        if ($relatedIndex > $currentIndex) {
            // Related movie comes after current - it's a SEQUEL
            return RelationshipType::SEQUEL;
        }

        // Same position (shouldn't happen, but fallback)
        return RelationshipType::SERIES;
    }
}
