<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\TvShow;
use App\Repositories\TvShowRepository;
use App\Support\SearchResult;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Laravel\Pennant\Feature;

/**
 * Service for searching TV shows locally and in TMDB, then merging results.
 */
class TvShowSearchService
{
    /**
     * Cache TTL in seconds.
     * Short timeout for local environment (testing), longer for production.
     */
    private const CACHE_TTL_SECONDS_LOCAL = 10; // 10 seconds for local testing

    private const CACHE_TTL_SECONDS_PRODUCTION = 3600; // 1 hour for production

    /**
     * Max items to fetch from local database when pagination is used.
     */
    private const LOCAL_PAGINATION_FETCH_LIMIT = 100;

    /**
     * Max items to fetch from external TMDB when pagination is used.
     */
    private const EXTERNAL_PAGINATION_FETCH_LIMIT = 20;

    private function getCacheTtl(): int
    {
        return app()->environment('local')
            ? self::CACHE_TTL_SECONDS_LOCAL
            : self::CACHE_TTL_SECONDS_PRODUCTION;
    }

    public function __construct(
        private readonly TvShowRepository $tvShowRepository,
        private readonly EntityVerificationServiceInterface $tmdbVerificationService
    ) {}

    /**
     * Search for TV shows using various criteria.
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

        // When pagination is requested, fetch enough items so any page can be served from cache.
        $localLimit = $this->resolveLocalFetchLimit($criteria, $itemsPerPage, $currentPageNumber, $isPaginationRequested);
        $externalLimit = $this->resolveExternalFetchLimit($criteria, $itemsPerPage, $currentPageNumber, $isPaginationRequested);

        $cacheKey = $this->generateCacheKey($criteria);

        // Try to get from tagged cache first (for Redis/Memcached)
        // Cache stores the full (unpaginated) result set so any page can be served from a single entry
        $cachedPayload = $this->getFromTaggedCache($cacheKey);
        if ($cachedPayload !== null) {
            Log::debug('TvShowSearchService: cache hit', ['criteria' => $criteria]);

            return $this->buildSearchResultFromCached($cachedPayload, $currentPageNumber, $itemsPerPage, $isPaginationRequested);
        }

        $localResult = $this->searchLocal($searchQuery, $searchYear, $localLimit);
        $localTvShows = $localResult['items'];
        $localTotalCount = $localResult['total'];

        $externalTvShows = $this->searchTmdbIfEnabled($searchQuery, $searchYear, $externalLimit);

        // Generate unique slugs for external results, considering local results context
        if (! empty($externalTvShows)) {
            $externalTvShows = $this->generateUniqueSlugsForSearchResults($externalTvShows, $localTvShows);
        }

        $allTvShows = $this->mergeResults($localTvShows, $externalTvShows);

        // Apply sorting if specified
        $sortField = $criteria['sort'] ?? null;
        $sortOrder = $criteria['order'] ?? null;
        if ($sortField !== null) {
            $allTvShows = $this->sortResults($allTvShows, $sortField, $sortOrder);
        }

        // Total count should reflect all matches, not just the currently fetched subset.
        $externalItemsInMergedCount = count($allTvShows) - count($localTvShows);
        $totalTvShowsCount = $localTotalCount + $externalItemsInMergedCount;

        $matchType = $this->determineMatchType($allTvShows, $localTvShows, $externalTvShows);
        $confidenceScore = $this->calculateConfidence($allTvShows, $matchType);

        // Cache the full list so any page can be served from one cache entry
        $cachePayload = [
            'all_items' => $allTvShows,
            'total' => $totalTvShowsCount,
            'local_count' => count($localTvShows),
            'external_count' => count($externalTvShows),
            'match_type' => $matchType,
            'confidence' => $confidenceScore,
        ];
        $this->putInTaggedCache($cacheKey, $cachePayload);

        $paginatedTvShows = $this->applyPagination($allTvShows, $currentPageNumber, $itemsPerPage, $isPaginationRequested);
        $paginationMetadata = $this->calculatePaginationMetadata($totalTvShowsCount, $currentPageNumber, $itemsPerPage, $isPaginationRequested);

        return new SearchResult(
            results: $paginatedTvShows,
            total: $totalTvShowsCount,
            localCount: count($localTvShows),
            externalCount: count($externalTvShows),
            matchType: $matchType,
            confidence: $confidenceScore,
            currentPage: $paginationMetadata['current_page'],
            perPage: $paginationMetadata['per_page'],
            totalPages: $paginationMetadata['total_pages']
        );
    }

    /**
     * Re-build SearchResult from a cached full payload, applying pagination for this request.
     *
     * @param  array{all_items: array<int, array<string, mixed>>, total: int, local_count: int, external_count: int, match_type: string, confidence: float}  $cached
     */
    private function buildSearchResultFromCached(
        array $cached,
        ?int $currentPageNumber,
        int $itemsPerPage,
        bool $isPaginationRequested
    ): SearchResult {
        $allItems = $cached['all_items'];
        $total = $cached['total'];

        $paginatedItems = $this->applyPagination($allItems, $currentPageNumber, $itemsPerPage, $isPaginationRequested);
        $paginationMetadata = $this->calculatePaginationMetadata($total, $currentPageNumber, $itemsPerPage, $isPaginationRequested);

        return new SearchResult(
            results: $paginatedItems,
            total: $total,
            localCount: $cached['local_count'],
            externalCount: $cached['external_count'],
            matchType: $cached['match_type'],
            confidence: $cached['confidence'],
            currentPage: $paginationMetadata['current_page'],
            perPage: $paginationMetadata['per_page'],
            totalPages: $paginationMetadata['total_pages']
        );
    }

    /**
     * Search for TV shows in local database.
     *
     * @return array{items: array<int, array<string, mixed>>, total: int}
     */
    private function searchLocal(?string $query, ?int $year, int $limit): array
    {
        $tvShowsPaginator = $this->tvShowRepository->searchTvShows($query, $limit);

        $filteredTvShows = $tvShowsPaginator->filter(function (TvShow $tvShow) use ($year) {
            if ($year === null) {
                return true;
            }

            return $tvShow->first_air_date?->year === $year;
        });

        return [
            'items' => $filteredTvShows->map(function (TvShow $tvShow) {
                return $this->transformTvShowToSearchResult($tvShow);
            })->values()->toArray(),
            'total' => $tvShowsPaginator->total(),
        ];
    }

    /**
     * Transform TvShow model to search result array.
     *
     * @return array<string, mixed>
     */
    private function transformTvShowToSearchResult(TvShow $tvShow): array
    {
        $hasDescription = isset($tvShow->descriptions_count)
            ? $tvShow->descriptions_count > 0
            : $tvShow->descriptions->isNotEmpty();

        $overviewText = $tvShow->defaultDescription?->text ?? '';
        $overviewPreview = mb_substr($overviewText, 0, 200);

        return [
            'source' => 'local',
            'slug' => $tvShow->slug,
            'title' => $tvShow->title,
            'first_air_date' => $tvShow->first_air_date?->format('Y-m-d'),
            'first_air_year' => $tvShow->first_air_date?->year,
            'show_type' => $tvShow->show_type,
            'has_description' => $hasDescription,
            'overview' => $overviewPreview,
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

        return $this->searchTmdb($query, $year, $limit);
    }

    /**
     * Search for TV shows in TMDB.
     *
     * @return array<int, array<string, mixed>>
     */
    private function searchTmdb(string $query, ?int $year, int $limit): array
    {
        try {
            $tmdbResults = $this->tmdbVerificationService->searchTvShows($query, $limit);

            // Transform all results first (without slugs)
            $transformedResults = array_map(
                fn (array $tmdbTvShow) => $this->transformTmdbTvShowToSearchResult($tmdbTvShow, false),
                $tmdbResults
            );

            // Filter by year if specified (same logic as for local results)
            if ($year !== null) {
                $transformedResults = array_filter($transformedResults, function (array $result) use ($year) {
                    return ($result['first_air_year'] ?? null) === $year;
                });
            }

            // Note: Slug generation will be done in search() method after getting local results
            // to ensure proper context-aware slug generation
            return array_values($transformedResults); // Re-index array after filtering
        } catch (\Throwable $e) {
            Log::warning('TvShowSearchService: TMDB search failed', [
                'query' => $query,
                'error' => $e->getMessage(),
            ]);

            return [];
        }
    }

    /**
     * Transform TMDB TV show data to search result array.
     *
     * @param  array<string, mixed>  $tmdbTvShow
     * @param  bool  $generateSlug  Whether to generate slug (false when generating slugs in batch)
     * @return array<string, mixed>
     */
    private function transformTmdbTvShowToSearchResult(array $tmdbTvShow, bool $generateSlug = true): array
    {
        $firstAirYear = $this->extractYearFromDate($tmdbTvShow['first_air_date'] ?? '');

        $overview = $tmdbTvShow['overview'] ?? '';
        $overviewPreview = substr($overview, 0, 200);

        $result = [
            'source' => 'external',
            'title' => $tmdbTvShow['name'],
            'first_air_year' => $firstAirYear,
            'overview' => $overviewPreview,
            'needs_creation' => true,
        ];

        if ($generateSlug) {
            $result['suggested_slug'] = TvShow::generateSlug(
                $tmdbTvShow['name'],
                $firstAirYear
            );
        }

        return $result;
    }

    /**
     * Extract year from date string (YYYY-MM-DD format).
     */
    private function extractYearFromDate(string $date): ?int
    {
        if (empty($date)) {
            return null;
        }

        $yearString = substr($date, 0, 4);

        return (int) $yearString;
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

            $suggestedSlug = TvShow::generateSlug($title, $year);
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
        $mergedTvShows = $localResults;

        foreach ($tmdbResults as $tmdbTvShow) {
            $isDuplicate = $this->isTvShowDuplicate($tmdbTvShow, $localResults);

            if (! $isDuplicate) {
                $mergedTvShows[] = $tmdbTvShow;
            }
        }

        return $mergedTvShows;
    }

    /**
     * Check if TMDB TV show already exists in local results (by title and year).
     */
    private function isTvShowDuplicate(array $tmdbTvShow, array $localResults): bool
    {
        $tmdbTitle = $tmdbTvShow['title'] ?? '';
        $tmdbYear = $tmdbTvShow['first_air_year'] ?? null;

        foreach ($localResults as $localTvShow) {
            $localTitle = $localTvShow['title'] ?? '';
            $localYear = $localTvShow['first_air_year'] ?? null;

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
     *
     * @return array<string, mixed>|null
     */
    private function getFromTaggedCache(string $cacheKey): ?array
    {
        try {
            // Try tagged cache first (works with Redis, Memcached, DynamoDB)
            return Cache::tags(['tv_show_search'])->get($cacheKey);
        } catch (\BadMethodCallException $e) {
            // Fallback to regular cache if tags not supported (database, file drivers)
            return Cache::get($cacheKey);
        }
    }

    /**
     * Store in tagged cache if supported, otherwise in regular cache.
     *
     * @param  array<string, mixed>  $payload
     */
    private function putInTaggedCache(string $cacheKey, array $payload): void
    {
        try {
            // Try tagged cache first (works with Redis, Memcached, DynamoDB)
            Cache::tags(['tv_show_search'])->put($cacheKey, $payload, now()->addSeconds($this->getCacheTtl()));
        } catch (\BadMethodCallException $e) {
            // Fallback to regular cache if tags not supported (database, file drivers)
            Cache::put($cacheKey, $payload, now()->addSeconds($this->getCacheTtl()));
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
     * Resolve how many items to fetch from local database.
     */
    private function resolveLocalFetchLimit(
        array $criteria,
        int $itemsPerPage,
        ?int $currentPageNumber,
        bool $isPaginationRequested
    ): int {
        if (array_key_exists('local_limit', $criteria)) {
            return (int) $criteria['local_limit'];
        }
        if (! $isPaginationRequested) {
            return $itemsPerPage;
        }

        return self::LOCAL_PAGINATION_FETCH_LIMIT;
    }

    /**
     * Resolve how many items to fetch from external TMDB.
     */
    private function resolveExternalFetchLimit(
        array $criteria,
        int $itemsPerPage,
        ?int $currentPageNumber,
        bool $isPaginationRequested
    ): int {
        if (array_key_exists('external_limit', $criteria)) {
            return (int) $criteria['external_limit'];
        }
        if (! $isPaginationRequested) {
            return min($itemsPerPage, self::EXTERNAL_PAGINATION_FETCH_LIMIT);
        }

        return self::EXTERNAL_PAGINATION_FETCH_LIMIT;
    }

    /**
     * Generate cache key from search criteria.
     *
     * @param  array<string, mixed>  $criteria
     */
    private function generateCacheKey(array $criteria): string
    {
        $queryHash = md5($criteria['q'] ?? '');
        $page = $criteria['page'] ?? 1;
        $itemsPerPage = $criteria['per_page'] ?? $criteria['limit'] ?? 20;
        $sortField = $criteria['sort'] ?? '';
        $sortOrder = $criteria['order'] ?? '';
        $localLimit = $criteria['local_limit'] ?? '';
        $externalLimit = $criteria['external_limit'] ?? '';

        $cacheKeyParts = [
            'tv_show:search',
            $queryHash,
            $criteria['year'] ?? '',
            // NOTE: page excluded intentionally – cache stores full result set, pagination is applied in memory
            $itemsPerPage,
            $sortField,
            $sortOrder,
            $localLimit,
            $externalLimit,
        ];

        return implode(':', $cacheKeyParts);
    }
}
