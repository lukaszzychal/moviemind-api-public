<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Movie;
use App\Repositories\MovieRepository;
use App\Support\SearchResult;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Laravel\Pennant\Feature;

/**
 * Service for searching movies locally and in TMDB, then merging results.
 */
class MovieSearchService
{
    /**
     * Cache TTL in seconds.
     * Short timeout for local environment (testing), longer for production.
     */
    private const CACHE_TTL_SECONDS_LOCAL = 10; // 10 seconds for local testing

    private const CACHE_TTL_SECONDS_PRODUCTION = 3600; // 1 hour for production

    /**
     * Max items to fetch from local/external when pagination is used, so that
     * cached result set can serve multiple pages (cache stores full list, we slice per request).
     */
    private const PAGINATION_FETCH_LIMIT = 100;

    private function getCacheTtl(): int
    {
        return app()->environment('local')
            ? self::CACHE_TTL_SECONDS_LOCAL
            : self::CACHE_TTL_SECONDS_PRODUCTION;
    }

    public function __construct(
        private readonly MovieRepository $movieRepository,
        private readonly EntityVerificationServiceInterface $tmdbVerificationService
    ) {}

    /**
     * Search for movies using various criteria.
     *
     * @param  array{q?: string, year?: int, director?: string, actor?: string|array, limit?: int, page?: int, per_page?: int, sort?: string, order?: string, local_limit?: int, external_limit?: int}  $criteria
     */
    public function search(array $criteria): SearchResult
    {
        $searchQuery = $criteria['q'] ?? null;
        $searchYear = $criteria['year'] ?? null;
        $searchDirector = $criteria['director'] ?? null;
        $searchActor = $criteria['actor'] ?? null;

        $paginationInfo = $this->extractPaginationInfo($criteria);
        $itemsPerPage = $paginationInfo['items_per_page'];
        $currentPageNumber = $paginationInfo['current_page'];
        $isPaginationRequested = $paginationInfo['is_pagination_requested'];

        // When pagination is requested, fetch enough items so the requested page has data.
        // Cache stores the full list; we slice per request.
        $fetchLimit = $this->resolveFetchLimit($criteria, $itemsPerPage, $currentPageNumber, $isPaginationRequested);
        $localLimit = $criteria['local_limit'] ?? $fetchLimit;
        $externalLimit = $criteria['external_limit'] ?? $fetchLimit;

        $sourceFilter = $criteria['source'] ?? null;

        $cacheKey = $this->generateCacheKey($criteria);

        // Try to get from tagged cache first (for Redis/Memcached)
        $cached = $this->getFromTaggedCache($cacheKey);
        if ($cached !== null) {
            Log::debug('MovieSearchService: cache hit', ['criteria' => $criteria]);

            return $this->buildSearchResultFromCached(
                $cached,
                $currentPageNumber,
                $itemsPerPage,
                $isPaginationRequested
            );
        }

        $localResult = ($sourceFilter === null || $sourceFilter === 'local')
            ? $this->searchLocal($searchQuery, $searchYear, $searchDirector, $searchActor, $localLimit)
            : ['items' => [], 'total' => 0];

        $localMovies = $localResult['items'];
        $localTotalCount = $localResult['total'];

        $externalMovies = ($sourceFilter === null || $sourceFilter === 'external')
            ? $this->searchTmdbIfEnabled($searchQuery, $searchYear, $searchDirector, $externalLimit)
            : [];

        // Generate unique slugs for external results, considering local results context
        if (! empty($externalMovies)) {
            $externalMovies = $this->generateUniqueSlugsForSearchResults($externalMovies, $localMovies);
        }

        $allMovies = $this->mergeResults($localMovies, $externalMovies);

        // Apply sorting if specified
        $sortField = $criteria['sort'] ?? null;
        $sortOrder = $criteria['order'] ?? null;
        if ($sortField !== null) {
            $allMovies = $this->sortResults($allMovies, $sortField, $sortOrder);
        }

        // Total count should reflect all matches, not just the currently fetched subset.
        // We use true local count as base and add new external items found.
        $externalItemsInMergedCount = count($allMovies) - count($localMovies);
        $totalMoviesCount = $localTotalCount + $externalItemsInMergedCount;
        $matchType = $this->determineMatchType($allMovies, $localMovies, $externalMovies);
        $confidenceScore = $this->calculateConfidence($allMovies, $matchType);

        // Cache full list so any page can be served from cache
        $cachePayload = [
            'all_movies' => $allMovies,
            'total' => $totalMoviesCount,
            'local_count' => count($localMovies),
            'external_count' => count($externalMovies),
            'match_type' => $matchType,
            'confidence' => $confidenceScore,
        ];
        $this->putInTaggedCache($cacheKey, $cachePayload);

        $resolved = $this->resolveEffectivePage($totalMoviesCount, $currentPageNumber, $itemsPerPage, $isPaginationRequested);
        $paginatedMovies = $this->applyPagination($allMovies, $resolved['effective_page'], $itemsPerPage, $isPaginationRequested);
        $paginationMetadata = $this->calculatePaginationMetadataWithResolved($resolved, $itemsPerPage, $isPaginationRequested);

        return new SearchResult(
            results: $paginatedMovies,
            total: $totalMoviesCount,
            localCount: count($localMovies),
            externalCount: count($externalMovies),
            matchType: $matchType,
            confidence: $confidenceScore,
            currentPage: $paginationMetadata['current_page'],
            perPage: $paginationMetadata['per_page'],
            totalPages: $paginationMetadata['total_pages']
        );
    }

    /**
     * Search for movies in local database.
     * Repository filters by actor/director/year in DB when provided.
     *
     * @return array{items: array<int, array<string, mixed>>, total: int}
     */
    private function searchLocal(?string $query, ?int $year, ?string $director, string|array|null $actor, int $limit): array
    {
        $moviesPaginator = $this->movieRepository->searchMovies($query, $limit, $actor, $director, $year);

        return [
            'items' => $moviesPaginator->map(fn (Movie $movie) => $this->transformMovieToSearchResult($movie))
                ->values()
                ->toArray(),
            'total' => $moviesPaginator->total(),
        ];
    }

    /**
     * Check if movie matches year filter.
     */
    private function doesMovieMatchYearFilter(Movie $movie, ?int $filterYear): bool
    {
        if ($filterYear === null) {
            return true;
        }

        return $movie->release_year === $filterYear;
    }

    /**
     * Check if movie matches director filter.
     */
    private function doesMovieMatchDirectorFilter(Movie $movie, ?string $filterDirector): bool
    {
        if ($filterDirector === null) {
            return true;
        }

        $movieDirector = $movie->director ?? '';

        return stripos($movieDirector, $filterDirector) !== false;
    }

    /**
     * Check if movie matches actor filter.
     */
    private function doesMovieMatchActorFilter(Movie $movie, string|array|null $filterActor): bool
    {
        if ($filterActor === null) {
            return true;
        }

        $searchActorNames = is_array($filterActor) ? $filterActor : [$filterActor];
        $movieActorNames = $this->getMovieActorNames($movie);

        foreach ($searchActorNames as $searchActorName) {
            $searchActorNameLower = strtolower($searchActorName);

            foreach ($movieActorNames as $movieActorName) {
                $isMatch = str_contains($movieActorName, $searchActorNameLower)
                    || str_contains($searchActorNameLower, $movieActorName);

                if ($isMatch) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * Get actor names from movie (lowercase for comparison).
     *
     * @return array<int, string>
     */
    private function getMovieActorNames(Movie $movie): array
    {
        return $movie->people()
            ->wherePivot('role', 'ACTOR')
            ->pluck('name')
            ->map(fn (string $name) => strtolower($name))
            ->toArray();
    }

    /**
     * Transform Movie model to search result array.
     *
     * @return array<string, mixed>
     */
    private function transformMovieToSearchResult(Movie $movie): array
    {
        $hasDescription = isset($movie->descriptions_count)
            ? $movie->descriptions_count > 0
            : $movie->descriptions()->exists();

        $overviewText = $movie->defaultDescription?->text ?? '';
        $overviewPreview = mb_substr($overviewText, 0, 200);

        return [
            'source' => 'local',
            'slug' => $movie->slug,
            'title' => $movie->title,
            'release_year' => $movie->release_year,
            'director' => $movie->director,
            'has_description' => $hasDescription,
            'overview' => $overviewPreview,
        ];
    }

    /**
     * Search TMDB if feature flag is enabled and query is provided.
     *
     * @return array<int, array<string, mixed>>
     */
    private function searchTmdbIfEnabled(?string $query, ?int $year, ?string $director, int $limit): array
    {
        $isTmdbVerificationEnabled = Feature::active('tmdb_verification');
        $isQueryProvided = $query !== null;

        if (! $isTmdbVerificationEnabled || ! $isQueryProvided) {
            return [];
        }

        return $this->searchTmdb($query, $year, $director, $limit);
    }

    /**
     * Search for movies in TMDB.
     *
     * @return array<int, array<string, mixed>>
     */
    private function searchTmdb(string $query, ?int $year, ?string $director, int $limit): array
    {
        $searchSlug = $this->createSlugFromCriteria($query, $year, $director);

        try {
            $tmdbResults = $this->tmdbVerificationService->searchMovies($searchSlug, $limit);

            // Transform all results first (without slugs)
            $transformedResults = array_map(
                fn (array $tmdbMovie) => $this->transformTmdbMovieToSearchResult($tmdbMovie, false),
                $tmdbResults
            );

            // Filter by year if specified (same logic as for local results)
            if ($year !== null) {
                $transformedResults = array_filter($transformedResults, function (array $result) use ($year) {
                    return ($result['release_year'] ?? null) === $year;
                });
            }

            // Filter by director if specified
            if ($director !== null) {
                $transformedResults = array_filter($transformedResults, function (array $result) use ($director) {
                    $movieDirector = $result['director'] ?? '';

                    return stripos($movieDirector, $director) !== false;
                });
            }

            // Note: Slug generation will be done in search() method after getting local results
            // to ensure proper context-aware slug generation
            return array_values($transformedResults); // Re-index array after filtering
        } catch (\Throwable $e) {
            Log::warning('MovieSearchService: TMDB search failed', [
                'query' => $query,
                'error' => $e->getMessage(),
            ]);

            return [];
        }
    }

    /**
     * Transform TMDB movie data to search result array.
     *
     * @param  array<string, mixed>  $tmdbMovie
     * @param  bool  $generateSlug  Whether to generate slug (false when generating slugs in batch)
     * @return array<string, mixed>
     */
    private function transformTmdbMovieToSearchResult(array $tmdbMovie, bool $generateSlug = true): array
    {
        $releaseYear = $this->extractYearFromReleaseDate($tmdbMovie['release_date'] ?? '');

        $overview = $tmdbMovie['overview'] ?? '';
        $overviewPreview = substr($overview, 0, 200);

        $result = [
            'source' => 'external',
            'title' => $tmdbMovie['title'],
            'release_year' => $releaseYear,
            'director' => $tmdbMovie['director'] ?? null,
            'overview' => $overviewPreview,
            'needs_creation' => true,
            // NOTE: tmdb_id is NOT included - hidden from API
        ];

        if ($generateSlug) {
            $result['suggested_slug'] = Movie::generateSlug(
                $tmdbMovie['title'],
                $releaseYear,
                $tmdbMovie['director'] ?? null
            );
        }

        return $result;
    }

    /**
     * Generate unique slugs for search results, considering context of all results.
     * If multiple movies have same title+year but different directors, use director in slug.
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

        // Group external results by title+year to detect duplicates
        $groupedByTitleYear = [];
        foreach ($results as $index => $result) {
            $key = strtolower(($result['title'] ?? '').'|'.($result['release_year'] ?? ''));
            if (! isset($groupedByTitleYear[$key])) {
                $groupedByTitleYear[$key] = [];
            }
            $groupedByTitleYear[$key][] = $index;
        }

        // Also check if any local results have same title+year
        foreach ($localResults as $localResult) {
            $key = strtolower(($localResult['title'] ?? '').'|'.($localResult['release_year'] ?? ''));
            if (isset($groupedByTitleYear[$key])) {
                // Add a marker to indicate local duplicate exists
                $groupedByTitleYear[$key][] = 'local';
            }
        }

        // Generate slugs for each result
        foreach ($results as $index => &$result) {
            $title = $result['title'] ?? '';
            $year = $result['release_year'] ?? null;
            $director = $result['director'] ?? null;

            $key = strtolower($title.'|'.($year ?? ''));
            $externalDuplicatesCount = isset($groupedByTitleYear[$key])
                ? count(array_filter($groupedByTitleYear[$key], fn (int|string $v): bool => $v !== 'local'))
                : 0;
            $hasLocalDuplicate = isset($groupedByTitleYear[$key])
                && in_array('local', $groupedByTitleYear[$key], true);
            $hasDuplicatesInResults = $externalDuplicatesCount > 1 || $hasLocalDuplicate;

            // If there are duplicates in search results, always use director if available
            if ($hasDuplicatesInResults && $director !== null && $director !== '') {
                $baseSlug = Str::slug($title);
                $directorSlug = Str::slug($director);
                $suggestedSlug = $year !== null
                    ? "{$baseSlug}-{$year}-{$directorSlug}"
                    : "{$baseSlug}-{$directorSlug}";
            } else {
                // Use standard slug generation (checks database for duplicates)
                // BUT: if we have duplicates in search results, we should still use director
                // even if Movie::generateSlug() doesn't detect them (because they're not in DB yet)
                if ($externalDuplicatesCount > 1 && $director !== null && $director !== '') {
                    $baseSlug = Str::slug($title);
                    $directorSlug = Str::slug($director);
                    $suggestedSlug = $year !== null
                        ? "{$baseSlug}-{$year}-{$directorSlug}"
                        : "{$baseSlug}-{$directorSlug}";
                } else {
                    $suggestedSlug = Movie::generateSlug($title, $year, $director);
                }
            }

            $result['suggested_slug'] = $suggestedSlug;
        }
        unset($result); // Break reference

        return $results;
    }

    /**
     * Extract year from release date string (YYYY-MM-DD format).
     */
    private function extractYearFromReleaseDate(string $releaseDate): ?int
    {
        if (empty($releaseDate)) {
            return null;
        }

        $yearString = substr($releaseDate, 0, 4);

        return (int) $yearString;
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
        $mergedMovies = $localResults;

        foreach ($tmdbResults as $tmdbMovie) {
            $isDuplicate = $this->isMovieDuplicate($tmdbMovie, $localResults);

            if (! $isDuplicate) {
                $mergedMovies[] = $tmdbMovie;
            }
        }

        return $mergedMovies;
    }

    /**
     * Check if TMDB movie already exists in local results (by title and year).
     */
    private function isMovieDuplicate(array $tmdbMovie, array $localResults): bool
    {
        $tmdbTitle = $tmdbMovie['title'] ?? '';
        $tmdbYear = $tmdbMovie['release_year'] ?? null;

        foreach ($localResults as $localMovie) {
            $localTitle = $localMovie['title'] ?? '';
            $localYear = $localMovie['release_year'] ?? null;

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
     * Create slug from search criteria for TMDB search.
     */
    private function createSlugFromCriteria(string $query, ?int $year, ?string $director): string
    {
        $baseSlug = \Illuminate\Support\Str::slug($query);

        if ($year !== null) {
            $baseSlug .= "-{$year}";
        }

        return $baseSlug;
    }

    /**
     * Resolve how many items to fetch so the requested page can be served.
     * When pagination is requested we fetch at least current_page * per_page (capped).
     */
    private function resolveFetchLimit(
        array $criteria,
        int $itemsPerPage,
        ?int $currentPageNumber,
        bool $isPaginationRequested
    ): int {
        $explicitLocal = array_key_exists('local_limit', $criteria);
        $explicitExternal = array_key_exists('external_limit', $criteria);
        if ($explicitLocal || $explicitExternal) {
            return $itemsPerPage;
        }
        if (! $isPaginationRequested || $currentPageNumber === null || $currentPageNumber <= 1) {
            return $itemsPerPage;
        }

        return (int) min($currentPageNumber * $itemsPerPage, self::PAGINATION_FETCH_LIMIT);
    }

    /**
     * Build SearchResult from cached payload (full list + metadata), applying pagination for this request.
     *
     * @param  array{all_movies: array<int, array<string, mixed>>, total: int, local_count: int, external_count: int, match_type: string, confidence: float}  $cached
     */
    private function buildSearchResultFromCached(
        array $cached,
        ?int $currentPageNumber,
        int $itemsPerPage,
        bool $isPaginationRequested
    ): SearchResult {
        $allMovies = $cached['all_movies'];
        $total = $cached['total'];
        $resolved = $this->resolveEffectivePage($total, $currentPageNumber, $itemsPerPage, $isPaginationRequested);
        $paginatedMovies = $this->applyPagination($allMovies, $resolved['effective_page'], $itemsPerPage, $isPaginationRequested);
        $paginationMetadata = $this->calculatePaginationMetadataWithResolved($resolved, $itemsPerPage, $isPaginationRequested);

        return new SearchResult(
            results: $paginatedMovies,
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
     * Resolve effective page number (clamp to 1..total_pages) so out-of-range requests return last page.
     *
     * @return array{effective_page: int, total_pages: int}
     */
    private function resolveEffectivePage(int $totalCount, ?int $currentPage, int $itemsPerPage, bool $isPaginationRequested): array
    {
        $totalPages = $totalCount > 0 ? (int) ceil($totalCount / $itemsPerPage) : 1;
        if (! $isPaginationRequested || $currentPage === null) {
            return ['effective_page' => 1, 'total_pages' => $totalPages];
        }

        $effectivePage = min($currentPage, max(1, $totalPages));

        return ['effective_page' => $effectivePage, 'total_pages' => $totalPages];
    }

    /**
     * Apply pagination to results. Caller must pass effective page (e.g. from resolveEffectivePage).
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
        if (! $isPaginationRequested || $currentPage === null) {
            return $allResults;
        }

        $offset = ($currentPage - 1) * $itemsPerPage;

        return array_slice($allResults, $offset, $itemsPerPage);
    }

    /**
     * Calculate pagination metadata from resolved page (effective page + total pages).
     *
     * @param  array{effective_page: int, total_pages: int}  $resolved
     * @return array{current_page: int|null, per_page: int|null, total_pages: int|null}
     */
    private function calculatePaginationMetadataWithResolved(
        array $resolved,
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

        return [
            'current_page' => $resolved['effective_page'],
            'per_page' => $itemsPerPage,
            'total_pages' => $resolved['total_pages'],
        ];
    }

    /**
     * Calculate pagination metadata (used when resolved page is not precomputed).
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

        $resolved = $this->resolveEffectivePage($totalCount, $currentPage, $itemsPerPage, true);

        return $this->calculatePaginationMetadataWithResolved($resolved, $itemsPerPage, true);
    }

    /**
     * Get from tagged cache if supported, otherwise from regular cache.
     * Returns cache payload (array with all_movies, total, ...) or null. Old SearchResult entries are ignored.
     *
     * @return array{all_movies: array<int, array<string, mixed>>, total: int, local_count: int, external_count: int, match_type: string, confidence: float}|null
     */
    private function getFromTaggedCache(string $cacheKey): ?array
    {
        try {
            $value = Cache::tags(['movie_search'])->get($cacheKey);
        } catch (\BadMethodCallException $e) {
            $value = Cache::get($cacheKey);
        }
        if ($value === null || ! is_array($value) || ! isset($value['all_movies'], $value['total'])) {
            return null;
        }

        return $value;
    }

    /**
     * Store in tagged cache if supported, otherwise in regular cache.
     *
     * @param  array{all_movies: array<int, array<string, mixed>>, total: int, local_count: int, external_count: int, match_type: string, confidence: float}  $payload
     */
    private function putInTaggedCache(string $cacheKey, array $payload): void
    {
        try {
            Cache::tags(['movie_search'])->put($cacheKey, $payload, now()->addSeconds($this->getCacheTtl()));
        } catch (\BadMethodCallException $e) {
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
                'release_year', 'created_at' => $valueA <=> $valueB,
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
            'release_year' => $result['release_year'] ?? 0,
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
            'release_year', 'created_at' => 'desc',
            default => 'asc',
        };
    }

    /**
     * Generate cache key from search criteria.
     *
     * @param  array<string, mixed>  $criteria
     */
    private function generateCacheKey(array $criteria): string
    {
        $queryHash = md5($criteria['q'] ?? '');
        $directorHash = md5($criteria['director'] ?? '');
        $actorString = is_array($criteria['actor'] ?? null)
            ? implode(',', $criteria['actor'])
            : ($criteria['actor'] ?? '');
        $actorHash = md5($actorString);
        $itemsPerPage = $criteria['per_page'] ?? $criteria['limit'] ?? 20;
        $sortField = $criteria['sort'] ?? '';
        $sortOrder = $criteria['order'] ?? '';
        $localLimit = $criteria['local_limit'] ?? '';
        $externalLimit = $criteria['external_limit'] ?? '';
        $sourceFilter = $criteria['source'] ?? '';

        $cacheKeyParts = [
            'movie:search',
            $queryHash,
            $criteria['year'] ?? '',
            $directorHash,
            $actorHash,
            $itemsPerPage,
            $sortField,
            $sortOrder,
            $localLimit,
            $externalLimit,
            $sourceFilter,
        ];

        return implode(':', $cacheKeyParts);
    }
}
