<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Movie;
use App\Repositories\MovieRepository;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

/**
 * Service for creating movies from TMDb data (without description).
 * Creates movie with metadata only (title, year, director).
 * Description should be generated asynchronously via queue job.
 */
class TmdbMovieCreationService
{
    public function __construct(
        private readonly MovieRepository $movieRepository
    ) {}

    /**
     * Create movie from TMDb data (metadata only, no description).
     * Description should be generated asynchronously via queue job.
     *
     * @param  array{title: string, release_date: string, overview: string, id: int, director?: string}  $tmdbData
     * @param  string  $requestSlug  Original slug from request
     * @return Movie|null Returns null if movie already exists
     */
    public function createFromTmdb(array $tmdbData, string $requestSlug): ?Movie
    {
        $title = $tmdbData['title'];
        $releaseYear = ! empty($tmdbData['release_date'])
            ? (int) substr($tmdbData['release_date'], 0, 4)
            : null;
        $director = $tmdbData['director'] ?? null;
        $overview = $tmdbData['overview'] ?? '';
        $tmdbId = $tmdbData['id'];

        // Generate slug from TMDb data
        $generatedSlug = Movie::generateSlug($title, $releaseYear, $director);

        // Check if movie already exists by generated slug
        $existing = $this->movieRepository->findBySlugForJob($generatedSlug);
        if ($existing) {
            Log::info('Movie already exists by generated slug, returning existing', [
                'request_slug' => $requestSlug,
                'generated_slug' => $generatedSlug,
                'movie_id' => $existing->id,
                'tmdb_id' => $tmdbId,
            ]);

            // Return existing movie
            return $existing;
        }

        // Check if movie exists by title + year (even if slug differs)
        $existingByTitleYear = Movie::where('title', $title)
            ->where('release_year', $releaseYear)
            ->first();

        if ($existingByTitleYear) {
            Log::info('Movie already exists by title+year, returning existing', [
                'request_slug' => $requestSlug,
                'generated_slug' => $generatedSlug,
                'existing_movie_id' => $existingByTitleYear->id,
                'tmdb_id' => $tmdbId,
            ]);

            // Return existing movie
            return $existingByTitleYear;
        }

        // Create movie from TMDb data (metadata only, no description)
        // Description will be generated asynchronously via queue job
        $movie = Movie::create([
            'title' => $title,
            'slug' => $generatedSlug,
            'release_year' => $releaseYear,
            'director' => $director,
            'genres' => [], // Genres can be added later if needed
        ]);

        // Save TMDb snapshot
        try {
            /** @var TmdbVerificationService $tmdbService */
            $tmdbService = app(TmdbVerificationService::class);
            $tmdbService->saveSnapshot('MOVIE', $movie->id, $tmdbId, 'movie', $tmdbData);
        } catch (\Throwable $e) {
            Log::warning('Failed to save TMDb snapshot after movie creation', [
                'movie_id' => $movie->id,
                'tmdb_id' => $tmdbId,
                'error' => $e->getMessage(),
            ]);
        }

        // Invalidate movie search cache when new movie is created
        $this->invalidateMovieSearchCache();

        Log::info('Movie created from TMDb data (metadata only, description will be generated asynchronously)', [
            'request_slug' => $requestSlug,
            'generated_slug' => $generatedSlug,
            'movie_id' => $movie->id,
            'tmdb_id' => $tmdbId,
            'title' => $title,
            'release_year' => $releaseYear,
        ]);

        return $movie;
    }

    /**
     * Invalidate movie search cache when a new movie is created.
     * Uses tagged cache if supported, otherwise clears all search cache keys.
     */
    private function invalidateMovieSearchCache(): void
    {
        try {
            // Try tagged cache invalidation (works with Redis, Memcached, DynamoDB)
            Cache::tags(['movie_search'])->flush();
            Log::debug('MovieSearchService: invalidated tagged cache after movie creation');
        } catch (\BadMethodCallException $e) {
            // Fallback: For database/file cache, we can't easily invalidate by tag
            // Cache will expire naturally after TTL (1 hour)
            // In production, consider using Redis for better cache control
            Log::debug('MovieSearchService: tagged cache not supported, cache will expire naturally', [
                'driver' => config('cache.default'),
            ]);
        }
    }
}
