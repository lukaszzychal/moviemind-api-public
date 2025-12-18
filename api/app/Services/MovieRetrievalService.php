<?php

declare(strict_types=1);

namespace App\Services;

use App\Actions\QueueMovieGenerationAction;
use App\Enums\Locale;
use App\Helpers\SlugValidator;
use App\Models\Movie;
use App\Models\MovieDescription;
use App\Repositories\MovieRepository;
use App\Support\MovieRetrievalResult;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Laravel\Pennant\Feature;

/**
 * Service for retrieving movies from local database or TMDB.
 * Handles caching, local lookup, TMDB search, and movie creation.
 */
class MovieRetrievalService
{
    public function __construct(
        private readonly MovieRepository $movieRepository,
        private readonly EntityVerificationServiceInterface $tmdbVerificationService,
        private readonly TmdbMovieCreationService $tmdbMovieCreationService,
        private readonly QueueMovieGenerationAction $queueMovieGenerationAction
    ) {}

    /**
     * Retrieve movie by slug, optionally with specific description.
     *
     * @param  string  $slug  Movie slug
     * @param  string|null  $descriptionId  Description ID (UUID) or null
     */
    public function retrieveMovie(string $slug, ?string $descriptionId): MovieRetrievalResult
    {
        // Parse slug to check if it contains year
        $parsed = Movie::parseSlug($slug);
        $titleSlug = \Illuminate\Support\Str::slug($parsed['title']);

        // Check cache first for exact slug match (before disambiguation logic)
        $cacheKey = $this->generateCacheKey($slug, $descriptionId);
        if ($cachedData = Cache::get($cacheKey)) {
            return MovieRetrievalResult::fromCache($cachedData);
        }

        // Check if slug is ambiguous (no year) - check for multiple matches
        // If multiple matches found, return the most recent one (200) with _meta, not disambiguation (300)
        if ($parsed['year'] === null) {
            $allMatches = $this->movieRepository->findAllByTitleSlug($titleSlug);
            if ($allMatches->count() > 1) {
                // Multiple movies found - return most recent one (already sorted by release_year desc)
                // The _meta will be added by MovieDisambiguationService in the formatter
                $movie = $allMatches->first();
            } elseif ($allMatches->count() === 1) {
                // Only one match found - use it
                $movie = $allMatches->first();
            } else {
                // No matches found - try exact match
                $movie = $this->movieRepository->findBySlugWithRelations($slug);
            }
        } else {
            // Slug contains year - check for exact match
            $movie = $this->movieRepository->findBySlugWithRelations($slug);
        }

        if ($movie === null) {
            return $this->handleMovieNotFound($slug, $descriptionId);
        }

        $selectedDescription = $this->findSelectedDescription($movie, $descriptionId);

        if ($selectedDescription === false) {
            return MovieRetrievalResult::descriptionNotFound();
        }

        return MovieRetrievalResult::found($movie, $selectedDescription);
    }

    /**
     * Find selected description for movie if description_id is provided.
     *
     * @return MovieDescription|null|false Returns MovieDescription if found, null if no description_id provided, false if not found
     */
    /**
     * Find selected description by ID (UUID).
     *
     * @param  Movie  $movie  Movie instance
     * @param  string|null  $descriptionId  Description ID (UUID) or null
     * @return MovieDescription|null|false Returns description if found, null if not provided, false if invalid
     */
    private function findSelectedDescription(Movie $movie, ?string $descriptionId): MovieDescription|null|false
    {
        if ($descriptionId === null) {
            return null;
        }

        $candidate = $movie->descriptions->firstWhere('id', $descriptionId);

        if ($candidate instanceof MovieDescription) {
            return $candidate;
        }

        return false;
    }

    /**
     * Handle case when movie is not found locally.
     *
     * @param  string  $slug  Movie slug
     * @param  string|null  $descriptionId  Description ID (UUID) or null
     */
    private function handleMovieNotFound(string $slug, ?string $descriptionId): MovieRetrievalResult
    {
        if (! Feature::active('ai_description_generation')) {
            return MovieRetrievalResult::notFound();
        }

        $validation = SlugValidator::validateMovieSlug($slug);
        if (! $validation['valid']) {
            return MovieRetrievalResult::invalidSlug($slug, $validation);
        }

        return $this->attemptToFindOrCreateMovieFromTmdb($slug, $descriptionId, $validation);
    }

    /**
     * Attempt to find or create movie from TMDB.
     *
     * @param  string  $slug  Movie slug
     * @param  string|null  $descriptionId  Description ID (UUID) or null
     * @param  array  $validation  Validation result
     */
    private function attemptToFindOrCreateMovieFromTmdb(string $slug, ?string $descriptionId, array $validation): MovieRetrievalResult
    {
        // If tmdb_verification is disabled, allow generation without TMDB check
        if (! Feature::active('tmdb_verification')) {
            return $this->queueGenerationWithoutTmdb($slug, $validation);
        }

        $tmdbData = $this->tmdbVerificationService->verifyMovie($slug);

        if ($tmdbData !== null) {
            return $this->handleTmdbExactMatch($tmdbData, $slug, $descriptionId, $validation);
        }

        return $this->handleTmdbSearch($slug, $descriptionId, $validation);
    }

    /**
     * Queue generation without TMDB verification (when feature flag is disabled).
     */
    private function queueGenerationWithoutTmdb(string $slug, array $validation): MovieRetrievalResult
    {
        // Check if movie already exists locally
        $movie = $this->movieRepository->findBySlugWithRelations($slug);

        if ($movie !== null) {
            // Movie exists - check if it has descriptions
            if ($movie->descriptions()->exists()) {
                $selectedDescription = $this->findSelectedDescription($movie, null);

                return MovieRetrievalResult::found($movie, $selectedDescription);
            }

            // Movie exists but no descriptions - queue generation
            $generationResult = $this->queueMovieGenerationAction->handle(
                $slug,
                confidence: $validation['confidence'],
                locale: Locale::EN_US->value,
                existingMovie: $movie
            );

            return MovieRetrievalResult::generationQueued($generationResult);
        }

        // Movie doesn't exist - create it from slug and queue generation
        $parsedSlug = Movie::parseSlug($slug);
        $movie = Movie::create([
            'title' => $parsedSlug['title'] ?? 'Unknown',
            'slug' => $slug,
            'release_year' => $parsedSlug['year'],
            'director' => $parsedSlug['director'] ?? null,
        ]);

        // Invalidate movie search cache when new movie is created
        $this->invalidateMovieSearchCache();

        $generationResult = $this->queueMovieGenerationAction->handle(
            $slug,
            confidence: $validation['confidence'],
            locale: Locale::EN_US->value,
            existingMovie: $movie
        );

        return MovieRetrievalResult::generationQueued($generationResult);
    }

    /**
     * Handle exact match found in TMDB.
     */
    /**
     * Handle exact match from TMDB.
     *
     * @param  array  $tmdbData  TMDB movie data
     * @param  string  $slug  Movie slug
     * @param  string|null  $descriptionId  Description ID (UUID) or null
     * @param  array  $validation  Validation result
     */
    private function handleTmdbExactMatch(array $tmdbData, string $slug, ?string $descriptionId, array $validation): MovieRetrievalResult
    {
        $createdMovie = $this->tmdbMovieCreationService->createFromTmdb($tmdbData, $slug);

        if ($createdMovie !== null) {
            $generationResult = $this->queueGenerationForNewMovie($createdMovie, $slug, $validation, $tmdbData);

            return MovieRetrievalResult::generationQueued($generationResult);
        }

        return $this->handleExistingMovieFromTmdb($tmdbData, $descriptionId, $validation);
    }

    /**
     * Handle case when movie already exists (found by TMDB data).
     */
    /**
     * Handle existing movie found from TMDB.
     *
     * @param  array  $tmdbData  TMDB movie data
     * @param  string|null  $descriptionId  Description ID (UUID) or null
     * @param  array  $validation  Validation result
     */
    private function handleExistingMovieFromTmdb(array $tmdbData, ?string $descriptionId, array $validation): MovieRetrievalResult
    {
        $movieSlug = $this->generateSlugFromTmdbData($tmdbData);
        $existingMovie = $this->movieRepository->findBySlugWithRelations($movieSlug);

        if ($existingMovie === null) {
            return MovieRetrievalResult::notFound();
        }

        if ($existingMovie->descriptions()->exists()) {
            $selectedDescription = $this->findSelectedDescription($existingMovie, $descriptionId);
            if ($selectedDescription === false) {
                return MovieRetrievalResult::descriptionNotFound();
            }

            return MovieRetrievalResult::found($existingMovie, $selectedDescription);
        }

        $generationResult = $this->queueGenerationForExistingMovie($existingMovie, $movieSlug, $validation, $tmdbData);

        return MovieRetrievalResult::generationQueued($generationResult);
    }

    /**
     * Handle TMDB search results (multiple or single match).
     */
    /**
     * Handle TMDB search (multiple or no results).
     *
     * @param  string  $slug  Movie slug
     * @param  string|null  $descriptionId  Description ID (UUID) or null
     * @param  array  $validation  Validation result
     */
    private function handleTmdbSearch(string $slug, ?string $descriptionId, array $validation): MovieRetrievalResult
    {
        $searchResults = $this->tmdbVerificationService->searchMovies($slug, 5);

        if (count($searchResults) > 1) {
            $options = $this->buildDisambiguationOptions($slug, $searchResults);

            return MovieRetrievalResult::disambiguation($slug, $options);
        }

        if (count($searchResults) === 1) {
            return $this->handleSingleTmdbResult($searchResults[0], $slug, $descriptionId, $validation);
        }

        return MovieRetrievalResult::notFound();
    }

    /**
     * Handle single result from TMDB search.
     */
    /**
     * Handle single TMDB search result.
     *
     * @param  array  $tmdbResult  Single TMDB movie result
     * @param  string  $slug  Movie slug
     * @param  string|null  $descriptionId  Description ID (UUID) or null
     * @param  array  $validation  Validation result
     */
    private function handleSingleTmdbResult(array $tmdbResult, string $slug, ?string $descriptionId, array $validation): MovieRetrievalResult
    {
        if ($this->doesYearMatchRequest($tmdbResult, $slug) === false) {
            return new MovieRetrievalResult(
                isNotFound: true,
                errorMessage: $this->buildYearMismatchMessage($tmdbResult, $slug),
                errorCode: 404
            );
        }

        $createdMovie = $this->tmdbMovieCreationService->createFromTmdb($tmdbResult, $slug);

        if ($createdMovie !== null) {
            $generationResult = $this->queueGenerationForNewMovie($createdMovie, $slug, $validation, $tmdbResult);

            return MovieRetrievalResult::generationQueued($generationResult);
        }

        return $this->handleExistingMovieFromTmdb($tmdbResult, $descriptionId, $validation);
    }

    /**
     * Check if TMDB result year matches the year in request slug.
     */
    private function doesYearMatchRequest(array $tmdbResult, string $slug): bool
    {
        $parsedSlug = Movie::parseSlug($slug);
        $requestedYear = $parsedSlug['year'];

        if ($requestedYear === null) {
            return true; // No year in request, so any year matches
        }

        $resultYear = ! empty($tmdbResult['release_date'])
            ? (int) substr($tmdbResult['release_date'], 0, 4)
            : null;

        return $resultYear === $requestedYear;
    }

    /**
     * Build error message for year mismatch.
     */
    private function buildYearMismatchMessage(array $tmdbResult, string $slug): string
    {
        $parsedSlug = Movie::parseSlug($slug);
        $requestedYear = $parsedSlug['year'];
        $resultYear = ! empty($tmdbResult['release_date'])
            ? (int) substr($tmdbResult['release_date'], 0, 4)
            : null;

        Log::info('MovieRetrievalService: search result year does not match requested year', [
            'slug' => $slug,
            'requested_year' => $requestedYear,
            'result_year' => $resultYear,
            'result_title' => $tmdbResult['title'] ?? null,
        ]);

        return "No movie found matching '{$slug}'. Found '{$tmdbResult['title']}' ({$resultYear}) but requested year was {$requestedYear}.";
    }

    /**
     * Generate slug from TMDB data.
     */
    private function generateSlugFromTmdbData(array $tmdbData): string
    {
        $title = $tmdbData['title'];
        $releaseYear = ! empty($tmdbData['release_date'])
            ? (int) substr($tmdbData['release_date'], 0, 4)
            : null;
        $director = $tmdbData['director'] ?? null;

        return Movie::generateSlug($title, $releaseYear, $director);
    }

    /**
     * Queue generation job for newly created movie.
     *
     * @return array<string, mixed> Generation result
     */
    private function queueGenerationForNewMovie(Movie $movie, string $slug, array $validation, array $tmdbData): array
    {
        return $this->queueMovieGenerationAction->handle(
            $movie->slug,
            confidence: $validation['confidence'],
            existingMovie: $movie,
            locale: Locale::EN_US->value,
            tmdbData: $tmdbData
        );
    }

    /**
     * Queue generation job for existing movie without description.
     *
     * @return array<string, mixed> Generation result
     */
    private function queueGenerationForExistingMovie(Movie $movie, string $slug, array $validation, array $tmdbData): array
    {
        return $this->queueMovieGenerationAction->handle(
            $slug,
            confidence: $validation['confidence'],
            existingMovie: $movie,
            locale: Locale::EN_US->value,
            tmdbData: $tmdbData
        );
    }

    /**
     * Build disambiguation options from TMDB search results.
     *
     * @param  array<int, array<string, mixed>>  $searchResults
     * @return array<int, array<string, mixed>>
     */
    private function buildDisambiguationOptions(string $slug, array $searchResults): array
    {
        return array_map(function ($result) {
            $year = ! empty($result['release_date']) ? substr($result['release_date'], 0, 4) : null;
            $director = $result['director'] ?? null;
            $suggestedSlug = Movie::generateSlug($result['title'], $year ? (int) $year : null, $director);

            return [
                'slug' => $suggestedSlug,
                'title' => $result['title'],
                'release_year' => $year ? (int) $year : null,
                'director' => $director,
                'overview' => substr($result['overview'] ?? '', 0, 200).(strlen($result['overview'] ?? '') > 200 ? '...' : ''),
                'select_url' => url("/api/v1/movies/{$suggestedSlug}"),
            ];
        }, $searchResults);
    }

    /**
     * Generate cache key for movie.
     *
     * @param  string  $slug  Movie slug
     * @param  string|null  $descriptionId  Description ID (UUID) or null
     * @return string Cache key
     */
    private function generateCacheKey(string $slug, ?string $descriptionId): string
    {
        $suffix = $descriptionId !== null ? 'desc:'.$descriptionId : 'desc:default';

        return 'movie:'.$slug.':'.$suffix;
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
            Log::debug('MovieRetrievalService: invalidated tagged cache after movie creation');
        } catch (\BadMethodCallException $e) {
            // Fallback: For database/file cache, we can't easily invalidate by tag
            // Cache will expire naturally after TTL (1 hour)
            // In production, consider using Redis for better cache control
            Log::debug('MovieRetrievalService: tagged cache not supported, cache will expire naturally', [
                'driver' => config('cache.default'),
            ]);
        }
    }
}
