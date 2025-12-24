<?php

declare(strict_types=1);

namespace App\Services;

use App\Actions\QueueTvSeriesGenerationAction;
use App\Helpers\SlugValidator;
use App\Models\TvSeries;
use App\Models\TvSeriesDescription;
use App\Repositories\TvSeriesRepository;
use App\Support\TvSeriesRetrievalResult;
use Illuminate\Support\Facades\Cache;
use Laravel\Pennant\Feature;

/**
 * Service for retrieving TV series from local database or TMDB.
 * Handles caching, local lookup, TMDB search, and TV series creation.
 */
class TvSeriesRetrievalService
{
    public function __construct(
        private readonly TvSeriesRepository $tvSeriesRepository,
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
        $validation = SlugValidator::validateTvSeriesSlug($slug);

        if (! $validation['valid']) {
            return TvSeriesRetrievalResult::invalidSlug($slug, $validation);
        }

        if (Feature::active('ai_description_generation')) {
            $generationResult = $this->queueTvSeriesGenerationAction->handle(
                $slug,
                $validation['confidence'],
                locale: null,
                contextTag: null
            );

            return TvSeriesRetrievalResult::generationQueued($generationResult);
        }

        return TvSeriesRetrievalResult::notFound();
    }

    /**
     * Generate cache key for TV series retrieval.
     */
    private function generateCacheKey(string $slug, ?string $descriptionId = null): string
    {
        return 'tv_series:'.$slug.':desc:'.($descriptionId ?? 'default');
    }
}
