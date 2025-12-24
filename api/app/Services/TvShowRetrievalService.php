<?php

declare(strict_types=1);

namespace App\Services;

use App\Actions\QueueTvShowGenerationAction;
use App\Helpers\SlugValidator;
use App\Models\TvShow;
use App\Models\TvShowDescription;
use App\Repositories\TvShowRepository;
use App\Support\TvShowRetrievalResult;
use Illuminate\Support\Facades\Cache;
use Laravel\Pennant\Feature;

/**
 * Service for retrieving TV shows from local database or TMDB.
 * Handles caching, local lookup, TMDB search, and TV show creation.
 */
class TvShowRetrievalService
{
    public function __construct(
        private readonly TvShowRepository $tvShowRepository,
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
        $validation = SlugValidator::validateTvShowSlug($slug);

        if (! $validation['valid']) {
            return TvShowRetrievalResult::invalidSlug($slug, $validation);
        }

        if (Feature::active('ai_description_generation')) {
            $generationResult = $this->queueTvShowGenerationAction->handle(
                $slug,
                $validation['confidence'],
                locale: null,
                contextTag: null
            );

            return TvShowRetrievalResult::generationQueued($generationResult);
        }

        return TvShowRetrievalResult::notFound();
    }

    /**
     * Generate cache key for TV show retrieval.
     */
    private function generateCacheKey(string $slug, ?string $descriptionId = null): string
    {
        return 'tv_show:'.$slug.':desc:'.($descriptionId ?? 'default');
    }
}
