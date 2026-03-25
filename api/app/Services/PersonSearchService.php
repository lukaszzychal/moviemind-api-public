<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Person;
use App\Repositories\PersonRepository;
use App\Support\SearchResult;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Laravel\Pennant\Feature;

/**
 * Service for searching people locally and in TMDB, then merging results.
 */
class PersonSearchService
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
        private readonly PersonRepository $personRepository,
        private readonly EntityVerificationServiceInterface $tmdbVerificationService
    ) {}

    /**
     * Search for people using various criteria.
     *
     * @param  array{q?: string, birth_year?: int, birthplace?: string, role?: string|array, movie?: string|array, limit?: int, page?: int, per_page?: int, sort?: string, order?: string, local_limit?: int, external_limit?: int}  $criteria
     */
    public function search(array $criteria): SearchResult
    {
        $searchQuery = $criteria['q'] ?? null;
        $searchBirthYear = $criteria['birth_year'] ?? null;
        $searchBirthplace = $criteria['birthplace'] ?? null;
        $searchRole = $criteria['role'] ?? null;
        $searchMovie = $criteria['movie'] ?? null;

        $paginationInfo = $this->extractPaginationInfo($criteria);
        $itemsPerPage = $paginationInfo['items_per_page'];
        $currentPageNumber = $paginationInfo['current_page'];
        $isPaginationRequested = $paginationInfo['is_pagination_requested'];

        // When pagination is requested, fetch enough items so any page can be served from cache.
        $localLimit = $this->resolveLocalFetchLimit($criteria, $itemsPerPage, $currentPageNumber, $isPaginationRequested);
        $externalLimit = $this->resolveExternalFetchLimit($criteria, $itemsPerPage, $currentPageNumber, $isPaginationRequested);

        $cacheKey = $this->generateCacheKey($criteria);

        // Cache stores the full (unpaginated) result set so any page can be served from a single entry
        $cachedPayload = $this->getFromTaggedCache($cacheKey);
        if ($cachedPayload !== null) {
            Log::debug('PersonSearchService: cache hit', ['criteria' => $criteria]);

            return $this->buildSearchResultFromCached($cachedPayload, $currentPageNumber, $itemsPerPage, $isPaginationRequested);
        }

        $localResult = $this->searchLocal($searchQuery, $searchBirthYear, $searchBirthplace, $searchRole, $searchMovie, $localLimit);
        $localPeople = $localResult['items'];
        $localTotalCount = $localResult['total'];

        $externalPeople = $this->searchTmdbIfEnabled($searchQuery, $searchBirthYear, $externalLimit);

        // Generate unique slugs for external results, considering local results context
        if (! empty($externalPeople)) {
            $externalPeople = $this->generateUniqueSlugsForSearchResults($externalPeople, $localPeople);
        }

        $allPeople = $this->mergeResults($localPeople, $externalPeople);

        // Apply sorting if specified
        $sortField = $criteria['sort'] ?? null;
        $sortOrder = $criteria['order'] ?? null;
        if ($sortField !== null) {
            $allPeople = $this->sortResults($allPeople, $sortField, $sortOrder);
        }

        // Total count should reflect all matches, not just the currently fetched subset.
        $externalItemsInMergedCount = count($allPeople) - count($localPeople);
        $totalPeopleCount = $localTotalCount + $externalItemsInMergedCount;

        $matchType = $this->determineMatchType($allPeople, $localPeople, $externalPeople);
        $confidenceScore = $this->calculateConfidence($allPeople, $matchType);

        // Cache the full list so any page can be served from one cache entry
        $cachePayload = [
            'all_items' => $allPeople,
            'total' => $totalPeopleCount,
            'local_count' => count($localPeople),
            'external_count' => count($externalPeople),
            'match_type' => $matchType,
            'confidence' => $confidenceScore,
        ];
        $this->putInTaggedCache($cacheKey, $cachePayload);

        $paginatedPeople = $this->applyPagination($allPeople, $currentPageNumber, $itemsPerPage, $isPaginationRequested);
        $paginationMetadata = $this->calculatePaginationMetadata($totalPeopleCount, $currentPageNumber, $itemsPerPage, $isPaginationRequested);

        return new SearchResult(
            results: $paginatedPeople,
            total: $totalPeopleCount,
            localCount: count($localPeople),
            externalCount: count($externalPeople),
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
     * Search for people in local database.
     *
     * @return array{items: array<int, array<string, mixed>>, total: int}
     */
    private function searchLocal(?string $query, ?int $birthYear, ?string $birthplace, string|array|null $role, string|array|null $movie, int $limit): array
    {
        $peoplePaginator = $this->personRepository->searchPeople($query, $limit);

        $filteredPeople = $peoplePaginator->filter(function (Person $person) use ($birthYear, $birthplace, $role, $movie) {
            if ($this->doesPersonMatchBirthYearFilter($person, $birthYear) === false) {
                return false;
            }

            if ($this->doesPersonMatchBirthplaceFilter($person, $birthplace) === false) {
                return false;
            }

            if ($this->doesPersonMatchRoleFilter($person, $role) === false) {
                return false;
            }

            if ($this->doesPersonMatchMovieFilter($person, $movie) === false) {
                return false;
            }

            return true;
        });

        return [
            'items' => $filteredPeople->map(function (Person $person) {
                return $this->transformPersonToSearchResult($person);
            })->values()->toArray(),
            'total' => $peoplePaginator->total(),
        ];
    }

    /**
     * Check if person matches birth year filter.
     */
    private function doesPersonMatchBirthYearFilter(Person $person, ?int $filterBirthYear): bool
    {
        if ($filterBirthYear === null) {
            return true;
        }

        if ($person->birth_date === null) {
            return false;
        }

        return (int) $person->birth_date->format('Y') === $filterBirthYear;
    }

    /**
     * Check if person matches birthplace filter.
     */
    private function doesPersonMatchBirthplaceFilter(Person $person, ?string $filterBirthplace): bool
    {
        if ($filterBirthplace === null) {
            return true;
        }

        $personBirthplace = $person->birthplace ?? '';

        return strcasecmp($personBirthplace, $filterBirthplace) === 0;
    }

    /**
     * Check if person matches role filter.
     */
    private function doesPersonMatchRoleFilter(Person $person, string|array|null $filterRole): bool
    {
        if ($filterRole === null) {
            return true;
        }

        $searchRoles = is_array($filterRole) ? $filterRole : [$filterRole];
        $personRoles = $this->getPersonRoles($person);

        foreach ($searchRoles as $searchRole) {
            if (in_array(strtoupper($searchRole), $personRoles, true)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Check if person matches movie filter.
     */
    private function doesPersonMatchMovieFilter(Person $person, string|array|null $filterMovie): bool
    {
        if ($filterMovie === null) {
            return true;
        }

        $searchMovieSlugs = is_array($filterMovie) ? $filterMovie : [$filterMovie];
        $personMovieSlugs = $this->getPersonMovieSlugs($person);

        foreach ($searchMovieSlugs as $searchMovieSlug) {
            if (in_array($searchMovieSlug, $personMovieSlugs, true)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Get roles from person (from movie_person pivot).
     *
     * @return array<int, string>
     */
    private function getPersonRoles(Person $person): array
    {
        return $person->movies
            ->pluck('pivot.role')
            ->filter()
            ->map(fn (string $role) => strtoupper($role))
            ->unique()
            ->values()
            ->toArray();
    }

    /**
     * Get movie slugs from person.
     *
     * @return array<int, string>
     */
    private function getPersonMovieSlugs(Person $person): array
    {
        return $person->movies
            ->pluck('slug')
            ->toArray();
    }

    /**
     * Transform Person model to search result array.
     *
     * @return array<string, mixed>
     */
    private function transformPersonToSearchResult(Person $person): array
    {
        $hasBio = isset($person->bios_count)
            ? $person->bios_count > 0
            : $person->bios->isNotEmpty();

        $birthYear = $person->birth_date?->format('Y');

        $overviewText = $person->defaultBio?->text ?? '';
        $overviewPreview = mb_substr($overviewText, 0, 200);

        return [
            'source' => 'local',
            'slug' => $person->slug,
            'name' => $person->name,
            'birth_year' => $birthYear ? (int) $birthYear : null,
            'birthplace' => $person->birthplace,
            'has_bio' => $hasBio,
            'overview' => $overviewPreview,
        ];
    }

    /**
     * Search TMDB if feature flag is enabled and query is provided.
     *
     * @return array<int, array<string, mixed>>
     */
    private function searchTmdbIfEnabled(?string $query, ?int $birthYear, int $limit): array
    {
        $isTmdbVerificationEnabled = Feature::active('tmdb_verification');
        $isQueryProvided = $query !== null;

        if (! $isTmdbVerificationEnabled || ! $isQueryProvided) {
            return [];
        }

        return $this->searchTmdb($query, $birthYear, $limit);
    }

    /**
     * Search for people in TMDB.
     *
     * @return array<int, array<string, mixed>>
     */
    private function searchTmdb(string $query, ?int $birthYear, int $limit): array
    {
        try {
            $tmdbResults = $this->tmdbVerificationService->searchPeople($query, $limit);

            // Transform all results first (without slugs)
            $transformedResults = array_map(
                fn (array $tmdbPerson) => $this->transformTmdbPersonToSearchResult($tmdbPerson, false),
                $tmdbResults
            );

            // Filter by birth year if specified (same logic as for local results)
            if ($birthYear !== null) {
                $transformedResults = array_filter($transformedResults, function (array $result) use ($birthYear) {
                    return ($result['birth_year'] ?? null) === $birthYear;
                });
            }

            // Note: Slug generation will be done in search() method after getting local results
            // to ensure proper context-aware slug generation
            return array_values($transformedResults); // Re-index array after filtering
        } catch (\Throwable $e) {
            Log::warning('PersonSearchService: TMDB search failed', [
                'query' => $query,
                'error' => $e->getMessage(),
            ]);

            return [];
        }
    }

    /**
     * Transform TMDB person data to search result array.
     *
     * @param  array<string, mixed>  $tmdbPerson
     * @param  bool  $generateSlug  Whether to generate slug (false when generating slugs in batch)
     * @return array<string, mixed>
     */
    private function transformTmdbPersonToSearchResult(array $tmdbPerson, bool $generateSlug = true): array
    {
        $birthYear = $this->extractYearFromBirthDate($tmdbPerson['birthday'] ?? '');

        $biography = $tmdbPerson['biography'] ?? '';
        $biographyPreview = substr($biography, 0, 200);

        $result = [
            'source' => 'external',
            'name' => $tmdbPerson['name'],
            'birth_year' => $birthYear,
            'birthplace' => $tmdbPerson['place_of_birth'] ?? null,
            'biography' => $biographyPreview,
            'needs_creation' => true,
            // NOTE: tmdb_id is NOT included - hidden from API
        ];

        if ($generateSlug) {
            $result['suggested_slug'] = Person::generateSlug(
                $tmdbPerson['name'],
                $tmdbPerson['birthday'] ?? null,
                $tmdbPerson['place_of_birth'] ?? null
            );
        }

        return $result;
    }

    /**
     * Generate unique slugs for search results, considering context of all results.
     * If multiple people have same name+year but different birthplaces, use birthplace in slug.
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

        // Group external results by name+year to detect duplicates
        $groupedByNameYear = [];
        foreach ($results as $index => $result) {
            $key = strtolower(($result['name'] ?? '').'|'.($result['birth_year'] ?? ''));
            if (! isset($groupedByNameYear[$key])) {
                $groupedByNameYear[$key] = [];
            }
            $groupedByNameYear[$key][] = $index;
        }

        // Also check if any local results have same name+year
        foreach ($localResults as $localResult) {
            $key = strtolower(($localResult['name'] ?? '').'|'.($localResult['birth_year'] ?? ''));
            if (isset($groupedByNameYear[$key])) {
                // Add a marker to indicate local duplicate exists
                $groupedByNameYear[$key][] = 'local';
            }
        }

        // Generate slugs for each result
        foreach ($results as $index => &$result) {
            $name = $result['name'] ?? '';
            $birthDate = $result['birth_year'] ? sprintf('%d-01-01', $result['birth_year']) : null;
            $birthplace = $result['birthplace'] ?? null;

            $key = strtolower($name.'|'.($result['birth_year'] ?? ''));
            $externalDuplicatesCount = isset($groupedByNameYear[$key])
                ? count(array_filter($groupedByNameYear[$key], fn (int|string $v): bool => $v !== 'local'))
                : 0;
            $hasLocalDuplicate = isset($groupedByNameYear[$key])
                && in_array('local', $groupedByNameYear[$key], true);
            $hasDuplicatesInResults = $externalDuplicatesCount > 1 || $hasLocalDuplicate;

            // If there are duplicates in search results, always use birthplace if available
            if ($hasDuplicatesInResults && $birthplace !== null && $birthplace !== '') {
                $baseSlug = Str::slug($name);
                $birthplaceSlug = Str::slug($birthplace);
                $suggestedSlug = $result['birth_year'] !== null
                    ? "{$baseSlug}-{$result['birth_year']}-{$birthplaceSlug}"
                    : "{$baseSlug}-{$birthplaceSlug}";
            } else {
                // Use standard slug generation (checks database for duplicates)
                $suggestedSlug = Person::generateSlug($name, $birthDate, $birthplace);
            }

            $result['suggested_slug'] = $suggestedSlug;
        }
        unset($result); // Break reference

        return $results;
    }

    /**
     * Extract year from birth date string (YYYY-MM-DD format).
     */
    private function extractYearFromBirthDate(string $birthDate): ?int
    {
        if (empty($birthDate)) {
            return null;
        }

        $yearString = substr($birthDate, 0, 4);

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
        $mergedPeople = $localResults;

        foreach ($tmdbResults as $tmdbPerson) {
            $isDuplicate = $this->isPersonDuplicate($tmdbPerson, $localResults);

            if (! $isDuplicate) {
                $mergedPeople[] = $tmdbPerson;
            }
        }

        return $mergedPeople;
    }

    /**
     * Check if TMDB person already exists in local results (by name and birth year).
     */
    private function isPersonDuplicate(array $tmdbPerson, array $localResults): bool
    {
        $tmdbName = $tmdbPerson['name'] ?? '';
        $tmdbYear = $tmdbPerson['birth_year'] ?? null;

        foreach ($localResults as $localPerson) {
            $localName = $localPerson['name'] ?? '';
            $localYear = $localPerson['birth_year'] ?? null;

            $isNameMatch = strcasecmp($localName, $tmdbName) === 0;
            $isYearMatch = $localYear === $tmdbYear;

            if ($isNameMatch && $isYearMatch) {
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
    /**
     * Get from tagged cache if supported, otherwise from regular cache.
     *
     * @return array<string, mixed>|null
     */
    private function getFromTaggedCache(string $cacheKey): ?array
    {
        try {
            // Try tagged cache first (works with Redis, Memcached, DynamoDB)
            return Cache::tags(['person_search'])->get($cacheKey);
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
            Cache::tags(['person_search'])->put($cacheKey, $payload, now()->addSeconds($this->getCacheTtl()));
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
                'name' => strcasecmp((string) $valueA, (string) $valueB),
                'birth_year', 'created_at' => $valueA <=> $valueB,
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
            'name' => $result['name'] ?? '',
            'birth_year' => $result['birth_year'] ?? 0,
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
            'name' => 'asc',
            'birth_year', 'created_at' => 'desc',
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
        $birthplaceHash = md5($criteria['birthplace'] ?? '');
        $roleString = is_array($criteria['role'] ?? null)
            ? implode(',', $criteria['role'])
            : ($criteria['role'] ?? '');
        $roleHash = md5($roleString);
        $movieString = is_array($criteria['movie'] ?? null)
            ? implode(',', $criteria['movie'])
            : ($criteria['movie'] ?? '');
        $movieHash = md5($movieString);
        $page = $criteria['page'] ?? 1;
        $itemsPerPage = $criteria['per_page'] ?? $criteria['limit'] ?? 20;
        $sortField = $criteria['sort'] ?? '';
        $sortOrder = $criteria['order'] ?? '';
        $localLimit = $criteria['local_limit'] ?? '';
        $externalLimit = $criteria['external_limit'] ?? '';

        $cacheKeyParts = [
            'person:search',
            $queryHash,
            $criteria['birth_year'] ?? '',
            $birthplaceHash,
            $roleHash,
            $movieHash,
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
