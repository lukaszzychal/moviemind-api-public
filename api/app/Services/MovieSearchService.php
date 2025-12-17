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
     * @param  array{q?: string, year?: int, director?: string, actor?: string|array, limit?: int, page?: int, per_page?: int}  $criteria
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

        $cacheKey = $this->generateCacheKey($criteria);

        // Try to get from tagged cache first (for Redis/Memcached)
        $cachedResult = $this->getFromTaggedCache($cacheKey);
        if ($cachedResult !== null) {
            Log::debug('MovieSearchService: cache hit', ['criteria' => $criteria]);

            return $cachedResult;
        }

        $localMovies = $this->searchLocal($searchQuery, $searchYear, $searchDirector, $searchActor, $itemsPerPage);
        $externalMovies = $this->searchTmdbIfEnabled($searchQuery, $searchYear, $searchDirector, $itemsPerPage);

        // Generate unique slugs for external results, considering local results context
        if (! empty($externalMovies)) {
            $externalMovies = $this->generateUniqueSlugsForSearchResults($externalMovies, $localMovies);
        }

        $allMovies = $this->mergeResults($localMovies, $externalMovies);
        $totalMoviesCount = count($allMovies);

        $paginatedMovies = $this->applyPagination($allMovies, $currentPageNumber, $itemsPerPage, $isPaginationRequested);
        $paginationMetadata = $this->calculatePaginationMetadata($totalMoviesCount, $currentPageNumber, $itemsPerPage, $isPaginationRequested);

        $matchType = $this->determineMatchType($allMovies, $localMovies, $externalMovies);
        $confidenceScore = $this->calculateConfidence($allMovies, $matchType);

        $searchResult = new SearchResult(
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

        // Store in tagged cache (for Redis/Memcached) or regular cache (fallback)
        $this->putInTaggedCache($cacheKey, $searchResult);

        return $searchResult;
    }

    /**
     * Search for movies in local database.
     *
     * @return array<int, array<string, mixed>>
     */
    private function searchLocal(?string $query, ?int $year, ?string $director, string|array|null $actor, int $limit): array
    {
        $movies = $this->movieRepository->searchMovies($query, $limit);

        $filteredMovies = $movies->filter(function (Movie $movie) use ($year, $director, $actor) {
            if ($this->doesMovieMatchYearFilter($movie, $year) === false) {
                return false;
            }

            if ($this->doesMovieMatchDirectorFilter($movie, $director) === false) {
                return false;
            }

            if ($this->doesMovieMatchActorFilter($movie, $actor) === false) {
                return false;
            }

            return true;
        });

        return $filteredMovies->map(function (Movie $movie) {
            return $this->transformMovieToSearchResult($movie);
        })->values()->toArray();
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

        return strcasecmp($movieDirector, $filterDirector) === 0;
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

        return [
            'source' => 'local',
            'slug' => $movie->slug,
            'title' => $movie->title,
            'release_year' => $movie->release_year,
            'director' => $movie->director,
            'has_description' => $hasDescription,
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

            // Note: Slug generation will be done in search() method after getting local results
            // to ensure proper context-aware slug generation
            return $transformedResults;
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
                ? count(array_filter($groupedByTitleYear[$key], fn ($v) => $v !== 'local' && is_int($v)))
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
        if (empty($mergedResults)) {
            return 'none';
        }

        if (count($mergedResults) === 1) {
            return 'exact';
        }

        if (count($mergedResults) > 1) {
            return 'ambiguous';
        }

        return 'partial';
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
        // Use Movie::generateSlug() logic but without checking for duplicates
        $baseSlug = \Illuminate\Support\Str::slug($query);

        if ($year !== null) {
            $baseSlug .= "-{$year}";
        }

        if ($director !== null) {
            $directorSlug = \Illuminate\Support\Str::slug($director);
            $baseSlug .= "-{$directorSlug}";
        }

        return $baseSlug;
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
            return Cache::tags(['movie_search'])->get($cacheKey);
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
            Cache::tags(['movie_search'])->put($cacheKey, $searchResult, now()->addSeconds($this->getCacheTtl()));
        } catch (\BadMethodCallException $e) {
            // Fallback to regular cache if tags not supported (database, file drivers)
            Cache::put($cacheKey, $searchResult, now()->addSeconds($this->getCacheTtl()));
        }
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
        $directorHash = md5($criteria['director'] ?? '');
        $actorString = is_array($criteria['actor'] ?? null)
            ? implode(',', $criteria['actor'])
            : ($criteria['actor'] ?? '');
        $actorHash = md5($actorString);
        $itemsPerPage = $criteria['per_page'] ?? $criteria['limit'] ?? 20;

        $cacheKeyParts = [
            'movie:search',
            $queryHash,
            $criteria['year'] ?? '',
            $directorHash,
            $actorHash,
            $itemsPerPage,
        ];

        return implode(':', $cacheKeyParts);
    }
}
