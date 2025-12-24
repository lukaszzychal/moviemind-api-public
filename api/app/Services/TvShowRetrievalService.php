<?php

declare(strict_types=1);

namespace App\Services;

use App\Actions\QueueTvShowGenerationAction;
use App\Enums\Locale;
use App\Helpers\SlugValidator;
use App\Models\TvShow;
use App\Models\TvShowDescription;
use App\Repositories\TvShowRepository;
use App\Support\TvShowRetrievalResult;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Laravel\Pennant\Feature;

/**
 * Service for retrieving TV shows from local database or TMDB.
 * Handles caching, local lookup, TMDB search, and TV show creation.
 */
class TvShowRetrievalService
{
    public function __construct(
        private readonly TvShowRepository $tvShowRepository,
        private readonly EntityVerificationServiceInterface $tmdbVerificationService,
        private readonly TmdbTvShowCreationService $tmdbTvShowCreationService,
        private readonly QueueTvShowGenerationAction $queueTvShowGenerationAction
    ) {}

    /**
     * Retrieve TV show by slug, optionally with specific description.
     *
     * @param  string  $slug  TV Show slug
     * @param  string|null  $descriptionId  Description ID (UUID) or null
     */
    public function retrieveTvShow(string $slug, ?string $descriptionId): TvShowRetrievalResult
    {
        // Parse slug to check if it contains year
        $parsed = TvShow::parseSlug($slug);
        $titleSlug = \Illuminate\Support\Str::slug($parsed['title']);

        // Check cache first for exact slug match
        $cacheKey = $this->generateCacheKey($slug, $descriptionId);
        if ($cachedData = Cache::get($cacheKey)) {
            return TvShowRetrievalResult::fromCache($cachedData);
        }

        // Check if slug is ambiguous (no year) - check for multiple matches
        if ($parsed['year'] === null) {
            $allMatches = $this->tvShowRepository->findAllByTitleSlug($titleSlug);
            if ($allMatches->count() > 1) {
                // Multiple TV shows found - return most recent one
                $tvShow = $allMatches->first();
            } elseif ($allMatches->count() === 1) {
                // Only one match found - use it
                $tvShow = $allMatches->first();
            } else {
                // No matches found - try exact match
                $tvShow = $this->tvShowRepository->findBySlugWithRelations($slug);
            }
        } else {
            // Slug contains year - check for exact match
            $tvShow = $this->tvShowRepository->findBySlugWithRelations($slug);
        }

        if ($tvShow === null) {
            return $this->handleTvShowNotFound($slug, $descriptionId);
        }

        $selectedDescription = $this->findSelectedDescription($tvShow, $descriptionId);

        if ($selectedDescription === false) {
            return TvShowRetrievalResult::descriptionNotFound();
        }

        return TvShowRetrievalResult::found($tvShow, $selectedDescription);
    }

    /**
     * Find selected description by ID (UUID).
     *
     * @param  TvShow  $tvShow  TV Show instance
     * @param  string|null  $descriptionId  Description ID (UUID) or null
     * @return TvShowDescription|null|false Returns description if found, null if not provided, false if invalid
     */
    private function findSelectedDescription(TvShow $tvShow, ?string $descriptionId): TvShowDescription|null|false
    {
        if ($descriptionId === null) {
            return null;
        }

        $candidate = $tvShow->descriptions->firstWhere('id', $descriptionId);

        return $candidate ?? false;
    }

    /**
     * Handle TV show not found case.
     */
    private function handleTvShowNotFound(string $slug, ?string $descriptionId): TvShowRetrievalResult
    {
        if (! Feature::active('ai_description_generation')) {
            return TvShowRetrievalResult::notFound();
        }

        $validation = SlugValidator::validateTvShowSlug($slug);
        if (! $validation['valid']) {
            return TvShowRetrievalResult::invalidSlug($slug, $validation);
        }

        return $this->attemptToFindOrCreateTvShowFromTmdb($slug, $descriptionId, $validation);
    }

    /**
     * Attempt to find or create TV show from TMDB.
     *
     * @param  string  $slug  TV Show slug
     * @param  string|null  $descriptionId  Description ID (UUID) or null
     * @param  array  $validation  Validation result
     */
    private function attemptToFindOrCreateTvShowFromTmdb(string $slug, ?string $descriptionId, array $validation): TvShowRetrievalResult
    {
        // If tmdb_verification is disabled, allow generation without TMDB check
        if (! Feature::active('tmdb_verification')) {
            return $this->queueGenerationWithoutTmdb($slug, $validation);
        }

        $tmdbData = $this->tmdbVerificationService->verifyTvShow($slug);

        if ($tmdbData !== null) {
            return $this->handleTmdbExactMatch($tmdbData, $slug, $descriptionId, $validation);
        }

        return $this->handleTmdbSearch($slug, $descriptionId, $validation);
    }

    /**
     * Queue generation without TMDB verification (when feature flag is disabled).
     */
    private function queueGenerationWithoutTmdb(string $slug, array $validation): TvShowRetrievalResult
    {
        // Check if TV show already exists locally
        $tvShow = $this->tvShowRepository->findBySlugWithRelations($slug);

        if ($tvShow !== null) {
            // TV show exists - check if it has descriptions
            if ($tvShow->descriptions()->exists()) {
                $selectedDescription = $this->findSelectedDescription($tvShow, null);

                return TvShowRetrievalResult::found($tvShow, $selectedDescription);
            }

            // TV show exists but no descriptions - queue generation
            $generationResult = $this->queueTvShowGenerationAction->handle(
                $slug,
                confidence: $validation['confidence'],
                locale: Locale::EN_US->value,
                existingTvShow: $tvShow
            );

            return TvShowRetrievalResult::generationQueued($generationResult);
        }

        // TV show doesn't exist - create it from slug and queue generation
        $parsedSlug = TvShow::parseSlug($slug);
        $tvShow = TvShow::create([
            'title' => $parsedSlug['title'] ?? 'Unknown',
            'slug' => $slug,
            'first_air_date' => $parsedSlug['year'] ? $parsedSlug['year'].'-01-01' : null,
        ]);

        // Invalidate TV show search cache when new TV show is created
        $this->invalidateTvShowSearchCache();

        $generationResult = $this->queueTvShowGenerationAction->handle(
            $slug,
            confidence: $validation['confidence'],
            locale: Locale::EN_US->value,
            existingTvShow: $tvShow
        );

        return TvShowRetrievalResult::generationQueued($generationResult);
    }

    /**
     * Handle exact match from TMDB.
     *
     * @param  array  $tmdbData  TMDB TV show data
     * @param  string  $slug  TV Show slug
     * @param  string|null  $descriptionId  Description ID (UUID) or null
     * @param  array  $validation  Validation result
     */
    private function handleTmdbExactMatch(array $tmdbData, string $slug, ?string $descriptionId, array $validation): TvShowRetrievalResult
    {
        $createdTvShow = $this->tmdbTvShowCreationService->createFromTmdb($tmdbData, $slug);

        if ($createdTvShow !== null) {
            $generationResult = $this->queueGenerationForNewTvShow($createdTvShow, $slug, $validation, $tmdbData);

            return TvShowRetrievalResult::generationQueued($generationResult);
        }

        return $this->handleExistingTvShowFromTmdb($tmdbData, $descriptionId, $validation);
    }

    /**
     * Handle existing TV show found from TMDB.
     *
     * @param  array  $tmdbData  TMDB TV show data
     * @param  string|null  $descriptionId  Description ID (UUID) or null
     * @param  array  $validation  Validation result
     */
    private function handleExistingTvShowFromTmdb(array $tmdbData, ?string $descriptionId, array $validation): TvShowRetrievalResult
    {
        $tvShowSlug = $this->generateSlugFromTmdbData($tmdbData);
        $existingTvShow = $this->tvShowRepository->findBySlugWithRelations($tvShowSlug);

        if ($existingTvShow === null) {
            return TvShowRetrievalResult::notFound();
        }

        if ($existingTvShow->descriptions()->exists()) {
            $selectedDescription = $this->findSelectedDescription($existingTvShow, $descriptionId);
            if ($selectedDescription === false) {
                return TvShowRetrievalResult::descriptionNotFound();
            }

            return TvShowRetrievalResult::found($existingTvShow, $selectedDescription);
        }

        $generationResult = $this->queueGenerationForExistingTvShow($existingTvShow, $tvShowSlug, $validation, $tmdbData);

        return TvShowRetrievalResult::generationQueued($generationResult);
    }

    /**
     * Handle TMDB search (multiple or no results).
     *
     * @param  string  $slug  TV Show slug
     * @param  string|null  $descriptionId  Description ID (UUID) or null
     * @param  array  $validation  Validation result
     */
    private function handleTmdbSearch(string $slug, ?string $descriptionId, array $validation): TvShowRetrievalResult
    {
        $searchResults = $this->tmdbVerificationService->searchTvShows($slug, 5);

        if (count($searchResults) > 1) {
            $options = $this->buildDisambiguationOptions($slug, $searchResults);

            return TvShowRetrievalResult::disambiguation($slug, $options);
        }

        if (count($searchResults) === 1) {
            return $this->handleSingleTmdbResult($searchResults[0], $slug, $descriptionId, $validation);
        }

        return TvShowRetrievalResult::notFound();
    }

    /**
     * Handle single TMDB search result.
     *
     * @param  array  $tmdbResult  Single TMDB TV show result
     * @param  string  $slug  TV Show slug
     * @param  string|null  $descriptionId  Description ID (UUID) or null
     * @param  array  $validation  Validation result
     */
    private function handleSingleTmdbResult(array $tmdbResult, string $slug, ?string $descriptionId, array $validation): TvShowRetrievalResult
    {
        if ($this->doesYearMatchRequest($tmdbResult, $slug) === false) {
            return TvShowRetrievalResult::notFound();
        }

        $createdTvShow = $this->tmdbTvShowCreationService->createFromTmdb($tmdbResult, $slug);

        if ($createdTvShow !== null) {
            $generationResult = $this->queueGenerationForNewTvShow($createdTvShow, $slug, $validation, $tmdbResult);

            return TvShowRetrievalResult::generationQueued($generationResult);
        }

        return $this->handleExistingTvShowFromTmdb($tmdbResult, $descriptionId, $validation);
    }

    /**
     * Check if TMDB result year matches the year in request slug.
     */
    private function doesYearMatchRequest(array $tmdbResult, string $slug): bool
    {
        $parsedSlug = TvShow::parseSlug($slug);
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

        return TvShow::generateSlug($title, $firstAirYear);
    }

    /**
     * Queue generation job for newly created TV show.
     *
     * @return array<string, mixed> Generation result
     */
    private function queueGenerationForNewTvShow(TvShow $tvShow, string $slug, array $validation, array $tmdbData): array
    {
        return $this->queueTvShowGenerationAction->handle(
            $tvShow->slug,
            confidence: $validation['confidence'],
            existingTvShow: $tvShow,
            locale: Locale::EN_US->value,
            tmdbData: $tmdbData
        );
    }

    /**
     * Queue generation job for existing TV show without description.
     *
     * @return array<string, mixed> Generation result
     */
    private function queueGenerationForExistingTvShow(TvShow $tvShow, string $slug, array $validation, array $tmdbData): array
    {
        return $this->queueTvShowGenerationAction->handle(
            $slug,
            confidence: $validation['confidence'],
            existingTvShow: $tvShow,
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
            $suggestedSlug = TvShow::generateSlug($result['name'], $year ? (int) $year : null);

            return [
                'slug' => $suggestedSlug,
                'title' => $result['name'],
                'first_air_year' => $year ? (int) $year : null,
                'overview' => substr($result['overview'] ?? '', 0, 200).(strlen($result['overview'] ?? '') > 200 ? '...' : ''),
                'select_url' => url("/api/v1/tv-shows/{$suggestedSlug}"),
            ];
        }, $searchResults);
    }

    /**
     * Invalidate TV show search cache when a new TV show is created.
     */
    private function invalidateTvShowSearchCache(): void
    {
        try {
            Cache::tags(['tv_show_search'])->flush();
            Log::debug('TvShowRetrievalService: invalidated tagged cache after TV show creation');
        } catch (\BadMethodCallException $e) {
            Log::debug('TvShowRetrievalService: tagged cache not supported, cache will expire naturally', [
                'driver' => config('cache.default'),
            ]);
        }
    }

    /**
     * Generate cache key for TV show retrieval.
     */
    private function generateCacheKey(string $slug, ?string $descriptionId = null): string
    {
        return 'tv_show:'.$slug.':desc:'.($descriptionId ?? 'default');
    }
}
