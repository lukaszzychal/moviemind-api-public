<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\TvSeries;
use App\Repositories\TvSeriesRepository;
use App\Support\SearchResult;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Laravel\Pennant\Feature;

/**
 * Service for searching TV series locally and in TMDB, then merging results.
 */
class TvSeriesSearchService
{
    /**
     * Cache TTL in seconds.
     * Short timeout for local environment (testing), longer for production.
     */
    private const CACHE_TTL_SECONDS_LOCAL = 10; // 10 seconds for local testing

    private const CACHE_TTL_SECONDS_PRODUCTION = 3600; // 1 hour for production

    private function getCacheTtl(): int
    {
        return app()->environment('local')
            ? self::CACHE_TTL_SECONDS_LOCAL
            : self::CACHE_TTL_SECONDS_PRODUCTION;
    }

    public function __construct(
        private readonly TvSeriesRepository $tvSeriesRepository
    ) {}

    /**
     * Search for TV series using various criteria.
     *
     * @param  array{q?: string, year?: int, limit?: int, page?: int, per_page?: int, sort?: string, order?: string, local_limit?: int, external_limit?: int}  $criteria
     */
    public function search(array $criteria): SearchResult
    {
        $searchQuery = $criteria['q'] ?? null;
        $searchYear = $criteria['year'] ?? null;

        $paginationInfo = $this->extractPaginationInfo($criteria);
        $itemsPerPage = $paginationInfo['items_per_page'];
        $currentPageNumber = $paginationInfo['current_page'];
        $isPaginationRequested = $paginationInfo['is_pagination_requested'];

        // Determine limits for each source
        $localLimit = $criteria['local_limit'] ?? $itemsPerPage;
        $externalLimit = $criteria['external_limit'] ?? $itemsPerPage;

        $cacheKey = $this->generateCacheKey($criteria);

        // Try to get from tagged cache first (for Redis/Memcached)
        $cachedResult = $this->getFromTaggedCache($cacheKey);
        if ($cachedResult !== null) {
            Log::debug('TvSeriesSearchService: cache hit', ['criteria' => $criteria]);

            return $cachedResult;
        }

        $localTvSeries = $this->searchLocal($searchQuery, $searchYear, $localLimit);
        $externalTvSeries = $this->searchTmdbIfEnabled($searchQuery, $searchYear, $externalLimit);

        // Generate unique slugs for external results, considering local results context
        if (! empty($externalTvSeries)) {
            $externalTvSeries = $this->generateUniqueSlugsForSearchResults($externalTvSeries, $localTvSeries);
        }

        $allTvSeries = $this->mergeResults($localTvSeries, $externalTvSeries);

        // Apply sorting if specified
        $sortField = $criteria['sort'] ?? null;
        $sortOrder = $criteria['order'] ?? null;
        if ($sortField !== null) {
            $allTvSeries = $this->sortResults($allTvSeries, $sortField, $sortOrder);
        }

        $totalTvSeriesCount = count($allTvSeries);

        $paginatedTvSeries = $this->applyPagination($allTvSeries, $currentPageNumber, $itemsPerPage, $isPaginationRequested);
        $paginationMetadata = $this->calculatePaginationMetadata($totalTvSeriesCount, $currentPageNumber, $itemsPerPage, $isPaginationRequested);

        $matchType = $this->determineMatchType($allTvSeries, $localTvSeries, $externalTvSeries);
        $confidenceScore = $this->calculateConfidence($allTvSeries, $matchType);

        $searchResult = new SearchResult(
            results: $paginatedTvSeries,
            total: $totalTvSeriesCount,
            localCount: count($localTvSeries),
            externalCount: count($externalTvSeries),
            matchType: $matchType,
            confidence: $confidenceScore,
            currentPage: $paginationMetadata['current_page'],
            perPage: $paginationMetadata['per_page'],
            totalPages: $paginationMetadata['total_pages']
        );

        // Store in tagged cache (for Redis/Memcached) or regular cache (fallback)
        $this->putInTaggedCache($cacheKey, $searchResult);

        return $searchResult;
    }

    /**
     * Search for TV series in local database.
     *
     * @return array<int, array<string, mixed>>
     */
    private function searchLocal(?string $query, ?int $year, int $limit): array
    {
        $tvSeries = $this->tvSeriesRepository->searchTvSeries($query, $limit);

        $filteredTvSeries = $tvSeries->filter(function (TvSeries $tvSeries) use ($year) {
            if ($year === null) {
                return true;
            }

            return $tvSeries->first_air_date?->year === $year;
        });

        return $filteredTvSeries->map(function (TvSeries $tvSeries) {
            return $this->transformTvSeriesToSearchResult($tvSeries);
        })->values()->toArray();
    }

    /**
     * Transform TvSeries model to search result array.
     *
     * @return array<string, mixed>
     */
    private function transformTvSeriesToSearchResult(TvSeries $tvSeries): array
    {
        $hasDescription = isset($tvSeries->descriptions_count)
            ? $tvSeries->descriptions_count > 0
            : $tvSeries->descriptions()->exists();

        return [
            'source' => 'local',
            'slug' => $tvSeries->slug,
            'title' => $tvSeries->title,
            'first_air_date' => $tvSeries->first_air_date?->format('Y-m-d'),
            'first_air_year' => $tvSeries->first_air_date?->year,
            'has_description' => $hasDescription,
        ];
    }

    /**
     * Search TMDB if feature flag is enabled and query is provided.
     *
     * @return array<int, array<string, mixed>>
     */
    private function searchTmdbIfEnabled(?string $query, ?int $year, int $limit): array
    {
        $isTmdbVerificationEnabled = Feature::active('tmdb_verification');
        $isQueryProvided = $query !== null;

        if (! $isTmdbVerificationEnabled || ! $isQueryProvided) {
            return [];
        }

        // Note: TMDb search for TV series will be implemented in Phase 4
        // For now, return empty array
        return [];
    }

    /**
     * Generate unique slugs for search results, considering context of all results.
     *
     * @param  array<int, array<string, mixed>>  $results  External (TMDb) results
     * @param  array<int, array<string, mixed>>  $localResults  Local results for context
     * @return array<int, array<string, mixed>>
     */
    private function generateUniqueSlugsForSearchResults(array $results, array $localResults = []): array
    {
        if (empty($results)) {
            return $results;
        }

        // Generate slugs for each result
        foreach ($results as &$result) {
            $title = $result['title'] ?? '';
            $year = $result['first_air_year'] ?? null;

            $suggestedSlug = TvSeries::generateSlug($title, $year);
            $result['suggested_slug'] = $suggestedSlug;
        }
        unset($result); // Break reference

        return $results;
    }

    /**
     * Merge local and TMDB results, removing duplicates.
     *
     * @param  array<int, array<string, mixed>>  $localResults
     * @param  array<int, array<string, mixed>>  $tmdbResults
     * @return array<int, array<string, mixed>>
     */
    private function mergeResults(array $localResults, array $tmdbResults): array
    {
        $mergedTvSeries = $localResults;

        foreach ($tmdbResults as $tmdbTvSeries) {
            $isDuplicate = $this->isTvSeriesDuplicate($tmdbTvSeries, $localResults);

            if (! $isDuplicate) {
                $mergedTvSeries[] = $tmdbTvSeries;
            }
        }

        return $mergedTvSeries;
    }

    /**
     * Check if TMDB TV series already exists in local results (by title and year).
     */
    private function isTvSeriesDuplicate(array $tmdbTvSeries, array $localResults): bool
    {
        $tmdbTitle = $tmdbTvSeries['title'] ?? '';
        $tmdbYear = $tmdbTvSeries['first_air_year'] ?? null;

        foreach ($localResults as $localTvSeries) {
            $localTitle = $localTvSeries['title'] ?? '';
            $localYear = $localTvSeries['first_air_year'] ?? null;

            $isTitleMatch = strcasecmp($localTitle, $tmdbTitle) === 0;
            $isYearMatch = $localYear === $tmdbYear;

            if ($isTitleMatch && $isYearMatch) {
                return true;
            }
        }

        return false;
    }

    /**
     * Determine match type based on results.
     *
     * @param  array<int, array<string, mixed>>  $mergedResults
     * @param  array<int, array<string, mixed>>  $localResults
     * @param  array<int, array<string, mixed>>  $tmdbResults
     */
    private function determineMatchType(array $mergedResults, array $localResults, array $tmdbResults): string
    {
        $count = count($mergedResults);

        if ($count === 0) {
            return 'none';
        }

        if ($count === 1) {
            return 'exact';
        }

        // $count > 1
        return 'ambiguous';
    }

    /**
     * Calculate confidence score (0.0 - 1.0).
     *
     * @param  array<int, array<string, mixed>>  $mergedResults
     */
    private function calculateConfidence(array $mergedResults, string $matchType): ?float
    {
        return match ($matchType) {
            'none' => 0.0,
            'exact' => 1.0,
            'ambiguous' => $this->calculateAmbiguousConfidence($mergedResults),
            'partial' => 0.7,
            default => null,
        };
    }

    /**
     * Calculate confidence for ambiguous matches (decreases with more results).
     */
    private function calculateAmbiguousConfidence(array $mergedResults): float
    {
        $resultsCount = count($mergedResults);
        $confidencePenalty = ($resultsCount - 1) * 0.1;
        $minimumConfidence = 0.5;

        return max($minimumConfidence, 1.0 - $confidencePenalty);
    }

    /**
     * Extract pagination information from criteria.
     *
     * @param  array<string, mixed>  $criteria
     * @return array{items_per_page: int, current_page: int|null, is_pagination_requested: bool}
     */
    private function extractPaginationInfo(array $criteria): array
    {
        $currentPage = $criteria['page'] ?? null;
        $itemsPerPage = $criteria['per_page'] ?? $criteria['limit'] ?? 20;
        $isPaginationRequested = $currentPage !== null;

        return [
            'items_per_page' => $itemsPerPage,
            'current_page' => $currentPage,
            'is_pagination_requested' => $isPaginationRequested,
        ];
    }

    /**
     * Apply pagination to results if requested.
     *
     * @param  array<int, array<string, mixed>>  $allResults
     * @return array<int, array<string, mixed>>
     */
    private function applyPagination(
        array $allResults,
        ?int $currentPage,
        int $itemsPerPage,
        bool $isPaginationRequested
    ): array {
        if (! $isPaginationRequested) {
            return $allResults;
        }

        $offset = ($currentPage - 1) * $itemsPerPage;

        return array_slice($allResults, $offset, $itemsPerPage);
    }

    /**
     * Calculate pagination metadata.
     *
     * @return array{current_page: int|null, per_page: int|null, total_pages: int|null}
     */
    private function calculatePaginationMetadata(
        int $totalCount,
        ?int $currentPage,
        int $itemsPerPage,
        bool $isPaginationRequested
    ): array {
        if (! $isPaginationRequested) {
            return [
                'current_page' => null,
                'per_page' => null,
                'total_pages' => null,
            ];
        }

        $totalPages = (int) ceil($totalCount / $itemsPerPage);

        return [
            'current_page' => $currentPage,
            'per_page' => $itemsPerPage,
            'total_pages' => $totalPages,
        ];
    }

    /**
     * Get from tagged cache if supported, otherwise from regular cache.
     */
    private function getFromTaggedCache(string $cacheKey): ?SearchResult
    {
        try {
            // Try tagged cache first (works with Redis, Memcached, DynamoDB)
            return Cache::tags(['tv_series_search'])->get($cacheKey);
        } catch (\BadMethodCallException $e) {
            // Fallback to regular cache if tags not supported (database, file drivers)
            return Cache::get($cacheKey);
        }
    }

    /**
     * Store in tagged cache if supported, otherwise in regular cache.
     */
    private function putInTaggedCache(string $cacheKey, SearchResult $searchResult): void
    {
        try {
            // Try tagged cache first (works with Redis, Memcached, DynamoDB)
            Cache::tags(['tv_series_search'])->put($cacheKey, $searchResult, now()->addSeconds($this->getCacheTtl()));
        } catch (\BadMethodCallException $e) {
            // Fallback to regular cache if tags not supported (database, file drivers)
            Cache::put($cacheKey, $searchResult, now()->addSeconds($this->getCacheTtl()));
        }
    }

    /**
     * Sort search results by specified field and order.
     *
     * @param  array<int, array<string, mixed>>  $results
     * @return array<int, array<string, mixed>>
     */
    private function sortResults(array $results, string $sortField, ?string $order): array
    {
        if (empty($results)) {
            return $results;
        }

        $sortOrder = $order ?? $this->getDefaultSortOrder($sortField);

        usort($results, function (array $a, array $b) use ($sortField, $sortOrder) {
            $valueA = $this->getSortValue($a, $sortField);
            $valueB = $this->getSortValue($b, $sortField);

            $comparison = match ($sortField) {
                'title' => strcasecmp((string) $valueA, (string) $valueB),
                'first_air_year', 'created_at' => $valueA <=> $valueB,
                default => 0,
            };

            return $sortOrder === 'desc' ? -$comparison : $comparison;
        });

        return $results;
    }

    /**
     * Get sort value from result array for specified field.
     *
     * @param  array<string, mixed>  $result
     */
    private function getSortValue(array $result, string $sortField): mixed
    {
        return match ($sortField) {
            'title' => $result['title'] ?? '',
            'first_air_year' => $result['first_air_year'] ?? 0,
            'created_at' => isset($result['created_at']) ? strtotime($result['created_at']) : 0,
            default => null,
        };
    }

    /**
     * Get default sort order for field.
     */
    private function getDefaultSortOrder(string $sortField): string
    {
        return match ($sortField) {
            'title' => 'asc',
            'first_air_year', 'created_at' => 'desc',
            default => 'asc',
        };
    }

    /**
     * Generate cache key from search criteria.
     * Note: Cache key does NOT include page number - we cache all results and paginate in memory.
     *
     * @param  array<string, mixed>  $criteria
     */
    private function generateCacheKey(array $criteria): string
    {
        $queryHash = md5($criteria['q'] ?? '');
        $itemsPerPage = $criteria['per_page'] ?? $criteria['limit'] ?? 20;
        $sortField = $criteria['sort'] ?? '';
        $sortOrder = $criteria['order'] ?? '';
        $localLimit = $criteria['local_limit'] ?? '';
        $externalLimit = $criteria['external_limit'] ?? '';

        $cacheKeyParts = [
            'tv_series:search',
            $queryHash,
            $criteria['year'] ?? '',
            $itemsPerPage,
            $sortField,
            $sortOrder,
            $localLimit,
            $externalLimit,
        ];

        return implode(':', $cacheKeyParts);
    }
}
