<?php

declare(strict_types=1);

namespace App\Services;

use App\Actions\QueueTvSeriesGenerationAction;
use App\Enums\Locale;
use App\Helpers\SlugValidator;
use App\Models\TvSeries;
use App\Models\TvSeriesDescription;
use App\Repositories\TvSeriesRepository;
use App\Support\TvSeriesRetrievalResult;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Laravel\Pennant\Feature;

/**
 * Service for retrieving TV series from local database or TMDB.
 * Handles caching, local lookup, TMDB search, and TV series creation.
 */
class TvSeriesRetrievalService
{
    public function __construct(
        private readonly TvSeriesRepository $tvSeriesRepository,
        private readonly EntityVerificationServiceInterface $tmdbVerificationService,
        private readonly TmdbTvSeriesCreationService $tmdbTvSeriesCreationService,
        private readonly QueueTvSeriesGenerationAction $queueTvSeriesGenerationAction
    ) {}

    /**
     * Retrieve TV series by slug, optionally with specific description.
     *
     * @param  string  $slug  TV Series slug
     * @param  string|null  $descriptionId  Description ID (UUID) or null
     */
    public function retrieveTvSeries(string $slug, ?string $descriptionId): TvSeriesRetrievalResult
    {
        // Parse slug to check if it contains year
        $parsed = TvSeries::parseSlug($slug);
        $titleSlug = \Illuminate\Support\Str::slug($parsed['title']);

        // Check cache first for exact slug match
        $cacheKey = $this->generateCacheKey($slug, $descriptionId);
        if ($cachedData = Cache::get($cacheKey)) {
            return TvSeriesRetrievalResult::fromCache($cachedData);
        }

        // Check if slug is ambiguous (no year) - check for multiple matches
        if ($parsed['year'] === null) {
            $allMatches = $this->tvSeriesRepository->findAllByTitleSlug($titleSlug);
            if ($allMatches->count() > 1) {
                // Multiple TV series found - return most recent one
                $tvSeries = $allMatches->first();
            } elseif ($allMatches->count() === 1) {
                // Only one match found - use it
                $tvSeries = $allMatches->first();
            } else {
                // No matches found - try exact match
                $tvSeries = $this->tvSeriesRepository->findBySlugWithRelations($slug);
            }
        } else {
            // Slug contains year - check for exact match
            $tvSeries = $this->tvSeriesRepository->findBySlugWithRelations($slug);
        }

        if ($tvSeries === null) {
            return $this->handleTvSeriesNotFound($slug, $descriptionId);
        }

        $selectedDescription = $this->findSelectedDescription($tvSeries, $descriptionId);

        if ($selectedDescription === false) {
            return TvSeriesRetrievalResult::descriptionNotFound();
        }

        return TvSeriesRetrievalResult::found($tvSeries, $selectedDescription);
    }

    /**
     * Find selected description by ID (UUID).
     *
     * @param  TvSeries  $tvSeries  TV Series instance
     * @param  string|null  $descriptionId  Description ID (UUID) or null
     * @return TvSeriesDescription|null|false Returns description if found, null if not provided, false if invalid
     */
    private function findSelectedDescription(TvSeries $tvSeries, ?string $descriptionId): TvSeriesDescription|null|false
    {
        if ($descriptionId === null) {
            return null;
        }

        $candidate = $tvSeries->descriptions->firstWhere('id', $descriptionId);

        return $candidate ?? false;
    }

    /**
     * Handle TV series not found case.
     */
    private function handleTvSeriesNotFound(string $slug, ?string $descriptionId): TvSeriesRetrievalResult
    {
        if (! Feature::active('ai_description_generation')) {
            return TvSeriesRetrievalResult::notFound();
        }

        $validation = SlugValidator::validateTvSeriesSlug($slug);
        if (! $validation['valid']) {
            return TvSeriesRetrievalResult::invalidSlug($slug, $validation);
        }

        return $this->attemptToFindOrCreateTvSeriesFromTmdb($slug, $descriptionId, $validation);
    }

    /**
     * Attempt to find or create TV series from TMDB.
     *
     * @param  string  $slug  TV Series slug
     * @param  string|null  $descriptionId  Description ID (UUID) or null
     * @param  array  $validation  Validation result
     */
    private function attemptToFindOrCreateTvSeriesFromTmdb(string $slug, ?string $descriptionId, array $validation): TvSeriesRetrievalResult
    {
        // If tmdb_verification is disabled, allow generation without TMDB check
        if (! Feature::active('tmdb_verification')) {
            return $this->queueGenerationWithoutTmdb($slug, $validation);
        }

        $tmdbData = $this->tmdbVerificationService->verifyTvSeries($slug);

        if ($tmdbData !== null) {
            return $this->handleTmdbExactMatch($tmdbData, $slug, $descriptionId, $validation);
        }

        return $this->handleTmdbSearch($slug, $descriptionId, $validation);
    }

    /**
     * Queue generation without TMDB verification (when feature flag is disabled).
     */
    private function queueGenerationWithoutTmdb(string $slug, array $validation): TvSeriesRetrievalResult
    {
        // Check if TV series already exists locally
        $tvSeries = $this->tvSeriesRepository->findBySlugWithRelations($slug);

        if ($tvSeries !== null) {
            // TV series exists - check if it has descriptions
            if ($tvSeries->descriptions()->exists()) {
                $selectedDescription = $this->findSelectedDescription($tvSeries, null);

                return TvSeriesRetrievalResult::found($tvSeries, $selectedDescription);
            }

            // TV series exists but no descriptions - queue generation
            $generationResult = $this->queueTvSeriesGenerationAction->handle(
                $slug,
                confidence: $validation['confidence'],
                locale: Locale::EN_US->value,
                existingTvSeries: $tvSeries
            );

            return TvSeriesRetrievalResult::generationQueued($generationResult);
        }

        // TV series doesn't exist - create it from slug and queue generation
        $parsedSlug = TvSeries::parseSlug($slug);
        $tvSeries = TvSeries::create([
            'title' => $parsedSlug['title'] ?? 'Unknown',
            'slug' => $slug,
            'first_air_date' => $parsedSlug['year'] ? $parsedSlug['year'].'-01-01' : null,
        ]);

        // Invalidate TV series search cache when new TV series is created
        $this->invalidateTvSeriesSearchCache();

        $generationResult = $this->queueTvSeriesGenerationAction->handle(
            $slug,
            confidence: $validation['confidence'],
            locale: Locale::EN_US->value,
            existingTvSeries: $tvSeries
        );

        return TvSeriesRetrievalResult::generationQueued($generationResult);
    }

    /**
     * Handle exact match from TMDB.
     *
     * @param  array  $tmdbData  TMDB TV series data
     * @param  string  $slug  TV Series slug
     * @param  string|null  $descriptionId  Description ID (UUID) or null
     * @param  array  $validation  Validation result
     */
    private function handleTmdbExactMatch(array $tmdbData, string $slug, ?string $descriptionId, array $validation): TvSeriesRetrievalResult
    {
        $createdTvSeries = $this->tmdbTvSeriesCreationService->createFromTmdb($tmdbData, $slug);

        if ($createdTvSeries !== null) {
            $generationResult = $this->queueGenerationForNewTvSeries($createdTvSeries, $slug, $validation, $tmdbData);

            return TvSeriesRetrievalResult::generationQueued($generationResult);
        }

        return $this->handleExistingTvSeriesFromTmdb($tmdbData, $descriptionId, $validation);
    }

    /**
     * Handle existing TV series found from TMDB.
     *
     * @param  array  $tmdbData  TMDB TV series data
     * @param  string|null  $descriptionId  Description ID (UUID) or null
     * @param  array  $validation  Validation result
     */
    private function handleExistingTvSeriesFromTmdb(array $tmdbData, ?string $descriptionId, array $validation): TvSeriesRetrievalResult
    {
        $tvSeriesSlug = $this->generateSlugFromTmdbData($tmdbData);
        $existingTvSeries = $this->tvSeriesRepository->findBySlugWithRelations($tvSeriesSlug);

        if ($existingTvSeries === null) {
            return TvSeriesRetrievalResult::notFound();
        }

        if ($existingTvSeries->descriptions()->exists()) {
            $selectedDescription = $this->findSelectedDescription($existingTvSeries, $descriptionId);
            if ($selectedDescription === false) {
                return TvSeriesRetrievalResult::descriptionNotFound();
            }

            return TvSeriesRetrievalResult::found($existingTvSeries, $selectedDescription);
        }

        $generationResult = $this->queueGenerationForExistingTvSeries($existingTvSeries, $tvSeriesSlug, $validation, $tmdbData);

        return TvSeriesRetrievalResult::generationQueued($generationResult);
    }

    /**
     * Handle TMDB search (multiple or no results).
     *
     * @param  string  $slug  TV Series slug
     * @param  string|null  $descriptionId  Description ID (UUID) or null
     * @param  array  $validation  Validation result
     */
    private function handleTmdbSearch(string $slug, ?string $descriptionId, array $validation): TvSeriesRetrievalResult
    {
        $searchResults = $this->tmdbVerificationService->searchTvSeries($slug, 5);

        if (count($searchResults) > 1) {
            $options = $this->buildDisambiguationOptions($slug, $searchResults);

            return TvSeriesRetrievalResult::disambiguation($slug, $options);
        }

        if (count($searchResults) === 1) {
            return $this->handleSingleTmdbResult($searchResults[0], $slug, $descriptionId, $validation);
        }

        return TvSeriesRetrievalResult::notFound();
    }

    /**
     * Handle single TMDB search result.
     *
     * @param  array  $tmdbResult  Single TMDB TV series result
     * @param  string  $slug  TV Series slug
     * @param  string|null  $descriptionId  Description ID (UUID) or null
     * @param  array  $validation  Validation result
     */
    private function handleSingleTmdbResult(array $tmdbResult, string $slug, ?string $descriptionId, array $validation): TvSeriesRetrievalResult
    {
        if ($this->doesYearMatchRequest($tmdbResult, $slug) === false) {
            return TvSeriesRetrievalResult::notFound();
        }

        $createdTvSeries = $this->tmdbTvSeriesCreationService->createFromTmdb($tmdbResult, $slug);

        if ($createdTvSeries !== null) {
            $generationResult = $this->queueGenerationForNewTvSeries($createdTvSeries, $slug, $validation, $tmdbResult);

            return TvSeriesRetrievalResult::generationQueued($generationResult);
        }

        return $this->handleExistingTvSeriesFromTmdb($tmdbResult, $descriptionId, $validation);
    }

    /**
     * Check if TMDB result year matches the year in request slug.
     */
    private function doesYearMatchRequest(array $tmdbResult, string $slug): bool
    {
        $parsedSlug = TvSeries::parseSlug($slug);
        $requestedYear = $parsedSlug['year'];

        if ($requestedYear === null) {
            return true; // No year in request, so any year matches
        }

        $resultYear = ! empty($tmdbResult['first_air_date'])
            ? (int) substr($tmdbResult['first_air_date'], 0, 4)
            : null;

        return $resultYear === $requestedYear;
    }

    /**
     * Generate slug from TMDB data.
     */
    private function generateSlugFromTmdbData(array $tmdbData): string
    {
        $title = $tmdbData['name'];
        $firstAirYear = ! empty($tmdbData['first_air_date'])
            ? (int) substr($tmdbData['first_air_date'], 0, 4)
            : null;

        return TvSeries::generateSlug($title, $firstAirYear);
    }

    /**
     * Queue generation job for newly created TV series.
     *
     * @return array<string, mixed> Generation result
     */
    private function queueGenerationForNewTvSeries(TvSeries $tvSeries, string $slug, array $validation, array $tmdbData): array
    {
        return $this->queueTvSeriesGenerationAction->handle(
            $tvSeries->slug,
            confidence: $validation['confidence'],
            existingTvSeries: $tvSeries,
            locale: Locale::EN_US->value,
            tmdbData: $tmdbData
        );
    }

    /**
     * Queue generation job for existing TV series without description.
     *
     * @return array<string, mixed> Generation result
     */
    private function queueGenerationForExistingTvSeries(TvSeries $tvSeries, string $slug, array $validation, array $tmdbData): array
    {
        return $this->queueTvSeriesGenerationAction->handle(
            $slug,
            confidence: $validation['confidence'],
            existingTvSeries: $tvSeries,
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
            $year = ! empty($result['first_air_date']) ? substr($result['first_air_date'], 0, 4) : null;
            $suggestedSlug = TvSeries::generateSlug($result['name'], $year ? (int) $year : null);

            return [
                'slug' => $suggestedSlug,
                'title' => $result['name'],
                'first_air_year' => $year ? (int) $year : null,
                'overview' => substr($result['overview'] ?? '', 0, 200).(strlen($result['overview'] ?? '') > 200 ? '...' : ''),
                'select_url' => url("/api/v1/tv-series/{$suggestedSlug}"),
            ];
        }, $searchResults);
    }

    /**
     * Invalidate TV series search cache when a new TV series is created.
     */
    private function invalidateTvSeriesSearchCache(): void
    {
        try {
            Cache::tags(['tv_series_search'])->flush();
            Log::debug('TvSeriesRetrievalService: invalidated tagged cache after TV series creation');
        } catch (\BadMethodCallException $e) {
            Log::debug('TvSeriesRetrievalService: tagged cache not supported, cache will expire naturally', [
                'driver' => config('cache.default'),
            ]);
        }
    }

    /**
     * Generate cache key for TV series retrieval.
     */
    private function generateCacheKey(string $slug, ?string $descriptionId = null): string
    {
        return 'tv_series:'.$slug.':desc:'.($descriptionId ?? 'default');
    }
}
