<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Movie;
use App\Models\Person;
use App\Models\TmdbSnapshot;
use App\Models\TvSeries;
use App\Models\TvShow;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Laravel\Pennant\Feature;
use LukaszZychal\TMDB\Client\TMDBClient;
use LukaszZychal\TMDB\Exception\NotFoundException;
use LukaszZychal\TMDB\Exception\RateLimitException;
use LukaszZychal\TMDB\Exception\TMDBException;

/**
 * Service for verifying entity existence in TMDb before AI generation.
 */
class TmdbVerificationService implements EntityVerificationServiceInterface
{
    private const CACHE_TTL_SECONDS = 86400; // 24 hours

    private const CACHE_PREFIX_MOVIE = 'tmdb:movie:';

    private const CACHE_PREFIX_PERSON = 'tmdb:person:';

    // TMDb API rate limits: 40 requests per 10 seconds
    private const RATE_LIMIT_REQUESTS = 40;

    private const RATE_LIMIT_WINDOW_SECONDS = 10;

    private const RATE_LIMIT_KEY = 'tmdb:rate_limit:window';

    private ?TMDBClient $client = null;

    public function __construct(
        private readonly ?string $apiKey = null
    ) {}

    /**
     * Search for movies in TMDb (returns multiple results for disambiguation).
     *
     * @return array<int, array{title: string, release_date: string, overview: string, id: int, director?: string}>
     */
    public function searchMovies(string $slug, int $limit = 5): array
    {
        // Check feature flag first - if disabled, skip TMDb search
        if (! Feature::active('tmdb_verification')) {
            Log::debug('TmdbVerificationService: TMDb verification disabled by feature flag', ['slug' => $slug]);

            return [];
        }

        $cacheKey = self::CACHE_PREFIX_MOVIE.'search:'.$slug.':'.$limit;

        // Check cache first
        if ($cached = Cache::get($cacheKey)) {
            Log::debug('TmdbVerificationService: cache hit for movie search', ['slug' => $slug]);

            return $cached === 'NOT_FOUND' ? [] : $cached;
        }

        try {
            $client = $this->getClient();
            if (! $client) {
                Log::warning('TmdbVerificationService: API key not configured', ['slug' => $slug]);

                return [];
            }

            // Check rate limit before making API call
            if (! $this->checkRateLimit()) {
                Log::warning('TmdbVerificationService: rate limit exceeded, skipping TMDb call', ['slug' => $slug]);

                return [];
            }

            // Parse slug to extract title and year
            $parsed = Movie::parseSlug($slug);
            $title = $parsed['title'];
            $year = $parsed['year'];

            // Build search query from title only (without year)
            $query = $title;

            Log::info('TmdbVerificationService: searching TMDb for movies', [
                'slug' => $slug,
                'title' => $title,
                'year' => $year,
                'query' => $query,
                'limit' => $limit,
            ]);

            // Search TMDb (library may or may not support year parameter)
            // We'll try with year parameter if available, otherwise fallback to query-only
            $data = null;
            if ($year !== null) {
                try {
                    // Try to use year parameter if library supports it
                    // If not supported, this will throw an exception and we'll fallback
                    $response = $client->search()->movies($query, ['year' => $year]);
                    $data = json_decode($response->getBody()->getContents(), true);

                    if (! empty($data['results'])) {
                        Log::info('TmdbVerificationService: found movies with year filter', [
                            'slug' => $slug,
                            'year' => $year,
                            'count' => count($data['results']),
                        ]);
                    }
                } catch (\ArgumentCountError|\TypeError $e) {
                    // Library doesn't support year parameter - fallback to query-only
                    Log::debug('TmdbVerificationService: library does not support year parameter, using query-only search', [
                        'slug' => $slug,
                        'error' => $e->getMessage(),
                    ]);
                    $data = null;
                } catch (\Throwable $e) {
                    // Other error - log and fallback
                    Log::warning('TmdbVerificationService: search with year failed, trying without year', [
                        'slug' => $slug,
                        'year' => $year,
                        'error' => $e->getMessage(),
                    ]);
                    $data = null;
                }
            }

            // Fallback: search without year filter
            if ($data === null || empty($data['results'])) {
                $response = $client->search()->movies($query);
                $data = json_decode($response->getBody()->getContents(), true);

                // If year is available, prioritize results with matching year
                if ($year !== null && ! empty($data['results'])) {
                    $matchingYear = array_filter($data['results'], function ($result) use ($year) {
                        $resultYear = ! empty($result['release_date']) ? (int) substr($result['release_date'], 0, 4) : null;

                        return $resultYear === $year;
                    });

                    if (! empty($matchingYear)) {
                        // Move matching year results to the beginning
                        $otherResults = array_filter($data['results'], function ($result) use ($year) {
                            $resultYear = ! empty($result['release_date']) ? (int) substr($result['release_date'], 0, 4) : null;

                            return $resultYear !== $year;
                        });
                        $data['results'] = array_merge(array_values($matchingYear), array_values($otherResults));

                        Log::info('TmdbVerificationService: prioritized results with matching year', [
                            'slug' => $slug,
                            'year' => $year,
                            'matching_count' => count($matchingYear),
                        ]);
                    }
                }
            }

            if (empty($data['results'])) {
                Log::info('TmdbVerificationService: no movies found in TMDb', ['slug' => $slug]);
                Cache::put($cacheKey, 'NOT_FOUND', now()->addSeconds(self::CACHE_TTL_SECONDS));

                return [];
            }

            $results = [];
            $resultsToProcess = array_slice($data['results'], 0, $limit);

            foreach ($resultsToProcess as $match) {
                $movieDetails = $this->getMovieDetails($match['id']);
                $director = $this->extractDirector($movieDetails);

                $result = [
                    'title' => $match['title'],
                    'release_date' => $match['release_date'] ?? '',
                    'overview' => $match['overview'] ?? '',
                    'id' => $match['id'],
                ];

                if ($director !== null) {
                    $result['director'] = $director;
                }

                $results[] = $result;
            }

            Log::info('TmdbVerificationService: found movies in TMDb', [
                'slug' => $slug,
                'count' => count($results),
            ]);

            // Cache the results
            Cache::put($cacheKey, $results, now()->addSeconds(self::CACHE_TTL_SECONDS));

            return $results;
        } catch (NotFoundException $e) {
            Log::info('TmdbVerificationService: no movies found in TMDb (NotFoundException)', ['slug' => $slug]);
            Cache::put($cacheKey, 'NOT_FOUND', now()->addSeconds(self::CACHE_TTL_SECONDS));

            return [];
        } catch (RateLimitException $e) {
            Log::warning('TmdbVerificationService: TMDb rate limit exceeded', [
                'slug' => $slug,
                'error' => $e->getMessage(),
            ]);

            return [];
        } catch (TMDBException $e) {
            Log::error('TmdbVerificationService: TMDb API error', [
                'slug' => $slug,
                'error' => $e->getMessage(),
            ]);

            return [];
        } catch (\Throwable $e) {
            Log::error('TmdbVerificationService: unexpected error', [
                'slug' => $slug,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return [];
        }
    }

    /**
     * Verify if movie exists in TMDb.
     *
     * @return array{title: string, release_date: string, overview: string, id: int, director?: string}|null
     */
    public function verifyMovie(string $slug): ?array
    {
        // Check feature flag first - if disabled, skip TMDb verification (fallback to AI)
        if (! Feature::active('tmdb_verification')) {
            Log::debug('TmdbVerificationService: TMDb verification disabled by feature flag', ['slug' => $slug]);

            return null;
        }

        $cacheKey = self::CACHE_PREFIX_MOVIE.$slug;

        // Check cache first
        if ($cached = Cache::get($cacheKey)) {
            Log::debug('TmdbVerificationService: cache hit for movie', ['slug' => $slug]);

            return $cached === 'NOT_FOUND' ? null : $cached;
        }

        try {
            $client = $this->getClient();
            if (! $client) {
                Log::warning('TmdbVerificationService: API key not configured', ['slug' => $slug]);

                return null;
            }

            // Check rate limit before making API call
            if (! $this->checkRateLimit()) {
                Log::warning('TmdbVerificationService: rate limit exceeded, skipping TMDb call', ['slug' => $slug]);

                // Return null to allow fallback to AI
                return null;
            }

            // Parse slug to extract title and year
            $parsed = Movie::parseSlug($slug);
            $title = $parsed['title'];
            $year = $parsed['year'];

            // Build search query from title only (without year)
            $query = $title;

            Log::info('TmdbVerificationService: searching TMDb for movie', [
                'slug' => $slug,
                'title' => $title,
                'year' => $year,
                'query' => $query,
            ]);

            // Search TMDb (library may or may not support year parameter)
            // We'll try with year parameter if available, otherwise fallback to query-only
            $data = null;
            if ($year !== null) {
                try {
                    // Try to use year parameter if library supports it
                    // If not supported, this will throw an exception and we'll fallback
                    $response = $client->search()->movies($query, ['year' => $year]);
                    $data = json_decode($response->getBody()->getContents(), true);

                    if (! empty($data['results'])) {
                        Log::info('TmdbVerificationService: found movie with year filter', [
                            'slug' => $slug,
                            'year' => $year,
                        ]);
                    }
                } catch (\ArgumentCountError|\TypeError $e) {
                    // Library doesn't support year parameter - fallback to query-only
                    Log::debug('TmdbVerificationService: library does not support year parameter, using query-only search', [
                        'slug' => $slug,
                        'error' => $e->getMessage(),
                    ]);
                    $data = null;
                } catch (\Throwable $e) {
                    // Other error - log and fallback
                    Log::warning('TmdbVerificationService: search with year failed, trying without year', [
                        'slug' => $slug,
                        'year' => $year,
                        'error' => $e->getMessage(),
                    ]);
                    $data = null;
                }
            }

            // Fallback: search without year filter
            if ($data === null || empty($data['results'])) {
                $response = $client->search()->movies($query);
                $data = json_decode($response->getBody()->getContents(), true);

                // If year is available, prioritize results with matching year
                if ($year !== null && ! empty($data['results'])) {
                    $matchingYear = array_filter($data['results'], function ($result) use ($year) {
                        $resultYear = ! empty($result['release_date']) ? (int) substr($result['release_date'], 0, 4) : null;

                        return $resultYear === $year;
                    });

                    if (! empty($matchingYear)) {
                        // Move matching year results to the beginning
                        $otherResults = array_filter($data['results'], function ($result) use ($year) {
                            $resultYear = ! empty($result['release_date']) ? (int) substr($result['release_date'], 0, 4) : null;

                            return $resultYear !== $year;
                        });
                        $data['results'] = array_merge(array_values($matchingYear), array_values($otherResults));

                        Log::info('TmdbVerificationService: prioritized result with matching year', [
                            'slug' => $slug,
                            'year' => $year,
                        ]);
                    }
                }
            }

            if (empty($data['results'])) {
                Log::info('TmdbVerificationService: movie not found in TMDb', ['slug' => $slug]);
                Cache::put($cacheKey, 'NOT_FOUND', now()->addSeconds(self::CACHE_TTL_SECONDS));

                return null;
            }

            // Get best match (first result, TMDb sorts by relevance or we prioritized by year)
            $bestMatch = $data['results'][0];

            // If year was specified in slug, verify that best match has matching year
            // This prevents returning wrong movie (e.g., "the-matrix-2003" should not return "The Matrix (1999)")
            if ($year !== null) {
                $bestMatchYear = ! empty($bestMatch['release_date']) ? (int) substr($bestMatch['release_date'], 0, 4) : null;

                if ($bestMatchYear !== $year) {
                    Log::info('TmdbVerificationService: best match year does not match requested year', [
                        'slug' => $slug,
                        'requested_year' => $year,
                        'best_match_year' => $bestMatchYear,
                        'best_match_title' => $bestMatch['title'] ?? null,
                    ]);

                    // Year doesn't match - return null to allow searchMovies to handle disambiguation
                    Cache::put($cacheKey, 'NOT_FOUND', now()->addSeconds(self::CACHE_TTL_SECONDS));

                    return null;
                }
            }

            // Get movie details to extract director
            $movieDetails = $this->getMovieDetails($bestMatch['id']);
            $director = $this->extractDirector($movieDetails);

            // Save snapshot (entity_id will be null until entity is created)
            if (! empty($movieDetails)) {
                try {
                    $this->saveSnapshot('MOVIE', null, $bestMatch['id'], 'movie', $movieDetails);
                } catch (\Throwable $e) {
                    Log::warning('TmdbVerificationService: failed to save snapshot', [
                        'slug' => $slug,
                        'tmdb_id' => $bestMatch['id'],
                        'error' => $e->getMessage(),
                    ]);
                }
            }

            $result = [
                'title' => $bestMatch['title'],
                'release_date' => $bestMatch['release_date'] ?? '',
                'overview' => $bestMatch['overview'] ?? '',
                'id' => $bestMatch['id'],
            ];

            if ($director !== null) {
                $result['director'] = $director;
            }

            Log::info('TmdbVerificationService: movie found in TMDb', [
                'slug' => $slug,
                'tmdb_id' => $bestMatch['id'],
                'title' => $bestMatch['title'],
            ]);

            // Cache the result
            Cache::put($cacheKey, $result, now()->addSeconds(self::CACHE_TTL_SECONDS));

            return $result;
        } catch (NotFoundException $e) {
            Log::info('TmdbVerificationService: movie not found in TMDb (NotFoundException)', ['slug' => $slug]);
            Cache::put($cacheKey, 'NOT_FOUND', now()->addSeconds(self::CACHE_TTL_SECONDS));

            return null;
        } catch (RateLimitException $e) {
            Log::warning('TmdbVerificationService: TMDb rate limit exceeded', [
                'slug' => $slug,
                'error' => $e->getMessage(),
            ]);

            // Return null to allow fallback to AI
            return null;
        } catch (TMDBException $e) {
            Log::error('TmdbVerificationService: TMDb API error', [
                'slug' => $slug,
                'error' => $e->getMessage(),
            ]);

            // Return null to allow fallback to AI
            return null;
        } catch (\Throwable $e) {
            Log::error('TmdbVerificationService: unexpected error', [
                'slug' => $slug,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return null;
        }
    }

    /**
     * Verify if person exists in TMDb.
     *
     * @return array{name: string, birthday: string, place_of_birth: string, id: int, biography?: string}|null
     */
    public function verifyPerson(string $slug): ?array
    {
        // Check feature flag first - if disabled, skip TMDb verification (fallback to AI)
        if (! Feature::active('tmdb_verification')) {
            Log::debug('TmdbVerificationService: TMDb verification disabled by feature flag', ['slug' => $slug]);

            return null;
        }

        $cacheKey = self::CACHE_PREFIX_PERSON.$slug;

        // Check cache first
        if ($cached = Cache::get($cacheKey)) {
            Log::debug('TmdbVerificationService: cache hit for person', ['slug' => $slug]);

            return $cached === 'NOT_FOUND' ? null : $cached;
        }

        try {
            $client = $this->getClient();
            if (! $client) {
                Log::warning('TmdbVerificationService: API key not configured', ['slug' => $slug]);

                return null;
            }

            // Check rate limit before making API call
            if (! $this->checkRateLimit()) {
                Log::warning('TmdbVerificationService: rate limit exceeded, skipping TMDb call', ['slug' => $slug]);

                // Return null to allow fallback to AI
                return null;
            }

            // Parse slug to extract name and birth year
            $parsed = Person::parseSlug($slug);
            $name = $parsed['name'];
            $birthYear = $parsed['birth_year'];

            // Build search query from name only (without year)
            $query = $name;

            Log::info('TmdbVerificationService: searching TMDb for person', [
                'slug' => $slug,
                'name' => $name,
                'birth_year' => $birthYear,
                'query' => $query,
            ]);

            // Note: TMDb API doesn't support year parameter for person search,
            // so we search by name and prioritize results with matching birth year
            $response = $client->search()->people($query);
            $data = json_decode($response->getBody()->getContents(), true);

            if (empty($data['results'])) {
                Log::info('TmdbVerificationService: person not found in TMDb', ['slug' => $slug]);
                Cache::put($cacheKey, 'NOT_FOUND', now()->addSeconds(self::CACHE_TTL_SECONDS));

                return null;
            }

            // If birth year is available, prioritize results with matching birth year
            if ($birthYear !== null) {
                // We need to get details for each result to check birth year
                // For now, use first result and verify later in getPersonDetails
                // This is a limitation - TMDb search doesn't return birth_date in search results
                Log::debug('TmdbVerificationService: birth year available, will verify in details', [
                    'slug' => $slug,
                    'birth_year' => $birthYear,
                ]);
            }

            // Get best match (first result, TMDb sorts by relevance)
            $bestMatch = $data['results'][0];

            // Get person details to extract biography
            $personDetails = $this->getPersonDetails($bestMatch['id']);

            // Save snapshot (entity_id will be null until entity is created)
            if (! empty($personDetails)) {
                try {
                    $this->saveSnapshot('PERSON', null, $bestMatch['id'], 'person', $personDetails);
                } catch (\Throwable $e) {
                    Log::warning('TmdbVerificationService: failed to save snapshot', [
                        'slug' => $slug,
                        'tmdb_id' => $bestMatch['id'],
                        'error' => $e->getMessage(),
                    ]);
                }
            }

            $result = [
                'name' => $bestMatch['name'],
                'birthday' => $bestMatch['known_for_department'] ?? '',
                'place_of_birth' => $personDetails['place_of_birth'] ?? '',
                'id' => $bestMatch['id'],
            ];

            if (! empty($personDetails['biography'])) {
                $result['biography'] = $personDetails['biography'];
            }

            if (! empty($personDetails['birthday'])) {
                $result['birthday'] = $personDetails['birthday'];
            }

            Log::info('TmdbVerificationService: person found in TMDb', [
                'slug' => $slug,
                'tmdb_id' => $bestMatch['id'],
                'name' => $bestMatch['name'],
            ]);

            // Cache the result
            Cache::put($cacheKey, $result, now()->addSeconds(self::CACHE_TTL_SECONDS));

            return $result;
        } catch (NotFoundException $e) {
            Log::info('TmdbVerificationService: person not found in TMDb (NotFoundException)', ['slug' => $slug]);
            Cache::put($cacheKey, 'NOT_FOUND', now()->addSeconds(self::CACHE_TTL_SECONDS));

            return null;
        } catch (RateLimitException $e) {
            Log::warning('TmdbVerificationService: TMDb rate limit exceeded', [
                'slug' => $slug,
                'error' => $e->getMessage(),
            ]);

            return null;
        } catch (TMDBException $e) {
            Log::error('TmdbVerificationService: TMDb API error', [
                'slug' => $slug,
                'error' => $e->getMessage(),
            ]);

            return null;
        } catch (\Throwable $e) {
            Log::error('TmdbVerificationService: unexpected error', [
                'slug' => $slug,
                'error' => $e->getMessage(),
            ]);

            return null;
        }
    }

    /**
     * Search for people in TMDb (returns multiple results for disambiguation).
     *
     * @return array<int, array{name: string, birthday?: string, place_of_birth?: string, id: int, biography?: string}>
     */
    public function searchPeople(string $slug, int $limit = 5): array
    {
        // Check feature flag first - if disabled, skip TMDb search
        if (! Feature::active('tmdb_verification')) {
            Log::debug('TmdbVerificationService: TMDb verification disabled by feature flag', ['slug' => $slug]);

            return [];
        }

        $cacheKey = self::CACHE_PREFIX_PERSON.'search:'.$slug.':'.$limit;

        // Check cache first
        if ($cached = Cache::get($cacheKey)) {
            Log::debug('TmdbVerificationService: cache hit for person search', ['slug' => $slug]);

            return $cached === 'NOT_FOUND' ? [] : $cached;
        }

        try {
            $client = $this->getClient();
            if (! $client) {
                Log::warning('TmdbVerificationService: API key not configured', ['slug' => $slug]);

                return [];
            }

            // Parse slug to extract name and birth year
            $parsed = Person::parseSlug($slug);
            $name = $parsed['name'];
            $birthYear = $parsed['birth_year'];

            // Build search query from name only (without year)
            $query = $name;

            Log::debug('TmdbVerificationService: searching people in TMDb', [
                'slug' => $slug,
                'name' => $name,
                'birth_year' => $birthYear,
                'query' => $query,
            ]);

            // Note: TMDb API doesn't support year parameter for person search
            $response = $client->search()->people($query);
            $data = json_decode($response->getBody()->getContents(), true);

            if (empty($data['results'])) {
                Log::info('TmdbVerificationService: no people found in TMDb', ['slug' => $slug]);
                Cache::put($cacheKey, 'NOT_FOUND', now()->addSeconds(self::CACHE_TTL_SECONDS));

                return [];
            }

            $results = [];
            $matches = array_slice($data['results'], 0, $limit);

            foreach ($matches as $match) {
                $result = [
                    'name' => $match['name'],
                    'id' => $match['id'],
                ];

                if (! empty($match['known_for_department'])) {
                    // Optional: add department info
                }

                $results[] = $result;
            }

            Log::info('TmdbVerificationService: found people in TMDb', [
                'slug' => $slug,
                'count' => count($results),
            ]);

            // Cache the results
            Cache::put($cacheKey, $results, now()->addSeconds(self::CACHE_TTL_SECONDS));

            return $results;
        } catch (NotFoundException $e) {
            Log::info('TmdbVerificationService: no people found in TMDb (NotFoundException)', ['slug' => $slug]);
            Cache::put($cacheKey, 'NOT_FOUND', now()->addSeconds(self::CACHE_TTL_SECONDS));

            return [];
        } catch (RateLimitException $e) {
            Log::warning('TmdbVerificationService: TMDb rate limit exceeded', [
                'slug' => $slug,
                'error' => $e->getMessage(),
            ]);

            return [];
        } catch (TMDBException $e) {
            Log::error('TmdbVerificationService: TMDb API error', [
                'slug' => $slug,
                'error' => $e->getMessage(),
            ]);

            return [];
        } catch (\Throwable $e) {
            Log::error('TmdbVerificationService: unexpected error', [
                'slug' => $slug,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return [];
        }
    }

    /**
     * Get movie details from TMDb including credits, similar movies, and collection.
     * This is a public method for use in jobs.
     *
     * @return array Movie details with credits, similar, and belongs_to_collection
     */
    public function getMovieDetails(int $tmdbId): array
    {
        try {
            $client = $this->getClient();
            if (! $client) {
                return [];
            }

            // Get details with credits, similar movies, and collection appended
            $response = $client->movies()->getDetails($tmdbId, ['append_to_response' => 'credits,similar,belongs_to_collection']);
            $data = json_decode($response->getBody()->getContents(), true);

            return $data ?? [];
        } catch (\Throwable $e) {
            Log::warning('TmdbVerificationService: failed to get movie details', [
                'tmdb_id' => $tmdbId,
                'error' => $e->getMessage(),
            ]);

            return [];
        }
    }

    /**
     * Get collection details from TMDb.
     * Uses direct HTTP call to /collection/{collection_id} endpoint since the library doesn't have a collections client.
     *
     * @return array<string, mixed>
     */
    public function getCollectionDetails(int $collectionId): array
    {
        try {
            $client = $this->getClient();
            if (! $client) {
                Log::warning('TmdbVerificationService: No client available for collection details', [
                    'collection_id' => $collectionId,
                ]);

                return [];
            }

            // Use HTTP client directly since the library doesn't have a collections() client
            // TMDB API endpoint: GET /collection/{collection_id}
            $httpClient = $client->getHttpClient();
            $response = $httpClient->get("collection/{$collectionId}");
            $statusCode = $response->getStatusCode();
            $body = $response->getBody()->getContents();
            $data = json_decode($body, true);

            if ($statusCode !== 200) {
                Log::warning('TmdbVerificationService: Non-200 status code for collection details', [
                    'collection_id' => $collectionId,
                    'status_code' => $statusCode,
                    'response_body' => substr($body, 0, 500),
                ]);

                return [];
            }

            if (empty($data)) {
                Log::warning('TmdbVerificationService: Empty response for collection details', [
                    'collection_id' => $collectionId,
                    'status_code' => $statusCode,
                ]);

                return [];
            }

            Log::debug('TmdbVerificationService: Collection details retrieved', [
                'collection_id' => $collectionId,
                'has_parts' => isset($data['parts']),
                'parts_count' => isset($data['parts']) ? count($data['parts']) : 0,
            ]);

            return $data;
        } catch (\Throwable $e) {
            Log::warning('TmdbVerificationService: failed to get collection details', [
                'collection_id' => $collectionId,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return [];
        }
    }

    /**
     * Get person details from TMDb.
     */
    private function getPersonDetails(int $tmdbId): array
    {
        try {
            $client = $this->getClient();
            if (! $client) {
                return [];
            }

            $response = $client->people()->getDetails($tmdbId);
            $data = json_decode($response->getBody()->getContents(), true);

            return $data ?? [];
        } catch (\Throwable $e) {
            Log::warning('TmdbVerificationService: failed to get person details', [
                'tmdb_id' => $tmdbId,
                'error' => $e->getMessage(),
            ]);

            return [];
        }
    }

    /**
     * Extract director from movie details.
     */
    private function extractDirector(array $movieDetails): ?string
    {
        if (empty($movieDetails['credits']['crew'])) {
            return null;
        }

        foreach ($movieDetails['credits']['crew'] as $crewMember) {
            if (($crewMember['job'] ?? '') === 'Director') {
                return $crewMember['name'] ?? null;
            }
        }

        return null;
    }

    /**
     * Check if we can make a TMDb API call (rate limiting).
     */
    private function checkRateLimit(): bool
    {
        $windowKey = self::RATE_LIMIT_KEY;
        $currentWindow = Cache::get($windowKey, []);

        $now = now()->timestamp;
        $windowStart = $now - ($now % self::RATE_LIMIT_WINDOW_SECONDS);

        // Remove old entries outside current window
        $currentWindow = array_filter($currentWindow, fn ($timestamp) => $timestamp >= $windowStart);

        // Check if we've exceeded the limit
        if (count($currentWindow) >= self::RATE_LIMIT_REQUESTS) {
            $oldestRequest = min($currentWindow);
            $nextWindowStart = $oldestRequest + self::RATE_LIMIT_WINDOW_SECONDS;
            $waitSeconds = $nextWindowStart - $now;

            Log::warning('TmdbVerificationService: rate limit exceeded', [
                'requests_in_window' => count($currentWindow),
                'limit' => self::RATE_LIMIT_REQUESTS,
                'wait_seconds' => $waitSeconds,
            ]);

            return false;
        }

        // Add current request to window
        $currentWindow[] = $now;
        Cache::put($windowKey, $currentWindow, now()->addSeconds(self::RATE_LIMIT_WINDOW_SECONDS * 2));

        return true;
    }

    /**
     * Get or create TMDb client.
     */
    /**
     * Perform a lightweight health check against the TMDb API.
     *
     * @return array{success: bool, service: string, message?: string, status?: int, error?: string}
     */
    public function health(): array
    {
        $apiKey = $this->apiKey ?? config('services.tmdb.api_key');

        if (empty($apiKey)) {
            return [
                'success' => false,
                'service' => 'tmdb',
                'error' => 'TMDb API key not configured. Set TMDB_API_KEY in .env',
            ];
        }

        try {
            $client = $this->getClient();
            if (! $client) {
                return [
                    'success' => false,
                    'service' => 'tmdb',
                    'error' => 'TMDb API key not configured. Set TMDB_API_KEY in .env',
                ];
            }

            // Perform a lightweight test request (get movie by ID 603 - The Matrix)
            // This is a simple, fast endpoint that doesn't require complex parameters
            $response = $client->movies()->getDetails(603);
            $statusCode = $response->getStatusCode();

            if ($statusCode >= 200 && $statusCode < 300) {
                return [
                    'success' => true,
                    'service' => 'tmdb',
                    'message' => 'TMDb API is accessible',
                    'status' => $statusCode,
                ];
            }

            return [
                'success' => false,
                'service' => 'tmdb',
                'error' => "TMDb API returned status {$statusCode}",
                'status' => $statusCode,
            ];
        } catch (\Throwable $e) {
            return [
                'success' => false,
                'service' => 'tmdb',
                'error' => 'TMDb API is not reachable: '.$e->getMessage(),
                'status' => 503,
            ];
        }
    }

    private function getClient(): ?TMDBClient
    {
        if ($this->client !== null) {
            return $this->client;
        }

        $apiKey = $this->apiKey ?? config('services.tmdb.api_key');

        if (empty($apiKey)) {
            return null;
        }

        $this->client = new TMDBClient($apiKey);

        return $this->client;
    }

    /**
     * Save TMDb snapshot to database.
     * If entity_id is null, snapshot will be saved and can be updated later when entity is created.
     *
     * @param  string  $entityType  MOVIE, PERSON, etc.
     * @param  string|null  $entityId  Entity ID (UUID, null if entity doesn't exist yet)
     * @param  int  $tmdbId  TMDb ID
     * @param  string  $tmdbType  movie, person, tv
     * @param  array  $rawData  Full TMDb response
     */
    public function saveSnapshot(string $entityType, ?string $entityId, int $tmdbId, string $tmdbType, array $rawData): TmdbSnapshot
    {
        return TmdbSnapshot::updateOrCreate(
            [
                'entity_type' => $entityType,
                'entity_id' => $entityId,
                'tmdb_id' => $tmdbId,
            ],
            [
                'tmdb_type' => $tmdbType,
                'raw_data' => $rawData,
                'fetched_at' => now(),
            ]
        );
    }

    /**
     * Refresh movie details from TMDb and update snapshot.
     * Note: This method does NOT include credits (cast/crew) to avoid re-syncing actors.
     * Only metadata (title, year, director, genres) is refreshed.
     *
     * @param  int  $tmdbId  TMDb movie ID
     * @return array|null Fresh movie details (without credits) or null if failed
     */
    public function refreshMovieDetails(int $tmdbId): ?array
    {
        $movieDetails = $this->getMovieDetails($tmdbId);
        if (empty($movieDetails)) {
            return null;
        }

        // Remove credits to prevent re-syncing actors/crew on refresh
        // Credits are only synced once when movie is first created
        unset($movieDetails['credits']);

        // Update snapshot if exists
        $snapshot = TmdbSnapshot::where('tmdb_id', $tmdbId)
            ->where('tmdb_type', 'movie')
            ->where('entity_type', 'MOVIE')
            ->first();

        if ($snapshot) {
            // Preserve existing credits in snapshot if they exist
            $existingData = $snapshot->raw_data ?? [];
            $existingCredits = $existingData['credits'] ?? null;

            $updatedData = $movieDetails;
            if ($existingCredits !== null) {
                $updatedData['credits'] = $existingCredits;
            }

            $snapshot->update([
                'raw_data' => $updatedData,
                'fetched_at' => now(),
            ]);
        }

        return $movieDetails;
    }

    /**
     * Refresh person details from TMDb and update snapshot.
     *
     * @param  int  $tmdbId  TMDb person ID
     * @return array|null Fresh person details or null if failed
     */
    public function refreshPersonDetails(int $tmdbId): ?array
    {
        $personDetails = $this->getPersonDetails($tmdbId);
        if (empty($personDetails)) {
            return null;
        }

        // Update snapshot if exists
        $snapshot = TmdbSnapshot::where('tmdb_id', $tmdbId)
            ->where('tmdb_type', 'person')
            ->where('entity_type', 'PERSON')
            ->first();

        if ($snapshot) {
            $snapshot->update([
                'raw_data' => $personDetails,
                'fetched_at' => now(),
            ]);
        }

        return $personDetails;
    }

    /**
     * Verify if TV series exists in TMDb.
     * Distinguishes TV Series (scripted) from TV Shows (unscripted) based on genres.
     *
     * @return array{name: string, first_air_date: string, overview: string, id: int}|null
     */
    public function verifyTvSeries(string $slug): ?array
    {
        // Check feature flag first
        if (! Feature::active('tmdb_verification')) {
            Log::debug('TmdbVerificationService: TMDb verification disabled by feature flag', ['slug' => $slug]);

            return null;
        }

        $cacheKey = 'tmdb:tv_series:'.$slug;

        // Check cache first
        if ($cached = Cache::get($cacheKey)) {
            Log::debug('TmdbVerificationService: cache hit for TV series', ['slug' => $slug]);

            return $cached === 'NOT_FOUND' ? null : $cached;
        }

        try {
            $client = $this->getClient();
            if (! $client) {
                Log::warning('TmdbVerificationService: API key not configured', ['slug' => $slug]);

                return null;
            }

            // Check rate limit
            if (! $this->checkRateLimit()) {
                Log::warning('TmdbVerificationService: rate limit exceeded, skipping TMDb call', ['slug' => $slug]);

                return null;
            }

            // Parse slug to extract title and year
            $parsed = TvSeries::parseSlug($slug);
            $title = $parsed['title'];
            $year = $parsed['year'];

            // Search TMDb TV
            $query = $title;
            $response = $client->search()->tv($query);
            $data = json_decode($response->getBody()->getContents(), true);

            if (empty($data['results'])) {
                Log::info('TmdbVerificationService: TV series not found in TMDb', ['slug' => $slug]);
                Cache::put($cacheKey, 'NOT_FOUND', now()->addSeconds(self::CACHE_TTL_SECONDS));

                return null;
            }

            // Filter for scripted TV series (exclude unscripted shows)
            $scriptedResults = array_filter($data['results'], function ($result) {
                return $this->isScriptedTv($result);
            });

            if (empty($scriptedResults)) {
                Log::info('TmdbVerificationService: no scripted TV series found (only unscripted shows)', ['slug' => $slug]);
                Cache::put($cacheKey, 'NOT_FOUND', now()->addSeconds(self::CACHE_TTL_SECONDS));

                return null;
            }

            // Prioritize by year if available
            $bestMatch = null;
            if ($year !== null) {
                $matchingYear = array_filter($scriptedResults, function ($result) use ($year) {
                    $resultYear = ! empty($result['first_air_date']) ? (int) substr($result['first_air_date'], 0, 4) : null;

                    return $resultYear === $year;
                });

                if (! empty($matchingYear)) {
                    $bestMatch = reset($matchingYear);
                }
            }

            if ($bestMatch === null) {
                $bestMatch = reset($scriptedResults);
            }

            // Get TV details
            $tvDetails = $this->getTvDetails($bestMatch['id']);

            if (empty($tvDetails)) {
                Cache::put($cacheKey, 'NOT_FOUND', now()->addSeconds(self::CACHE_TTL_SECONDS));

                return null;
            }

            $result = [
                'name' => $tvDetails['name'] ?? $bestMatch['name'],
                'first_air_date' => $tvDetails['first_air_date'] ?? $bestMatch['first_air_date'] ?? '',
                'overview' => $tvDetails['overview'] ?? $bestMatch['overview'] ?? '',
                'id' => $bestMatch['id'],
            ];

            // Save snapshot
            try {
                TmdbSnapshot::updateOrCreate(
                    [
                        'tmdb_id' => $bestMatch['id'],
                        'tmdb_type' => 'tv',
                        'entity_type' => 'TV_SERIES',
                    ],
                    [
                        'entity_id' => null, // Will be set when entity is created
                        'raw_data' => $tvDetails,
                        'fetched_at' => now(),
                    ]
                );
            } catch (\Throwable $e) {
                Log::warning('TmdbVerificationService: failed to save TV series snapshot', [
                    'slug' => $slug,
                    'tmdb_id' => $bestMatch['id'],
                    'error' => $e->getMessage(),
                ]);
            }

            Log::info('TmdbVerificationService: TV series found in TMDb', [
                'slug' => $slug,
                'tmdb_id' => $bestMatch['id'],
                'name' => $result['name'],
            ]);

            // Cache the result
            Cache::put($cacheKey, $result, now()->addSeconds(self::CACHE_TTL_SECONDS));

            return $result;
        } catch (NotFoundException $e) {
            Log::info('TmdbVerificationService: TV series not found in TMDb (NotFoundException)', ['slug' => $slug]);
            Cache::put($cacheKey, 'NOT_FOUND', now()->addSeconds(self::CACHE_TTL_SECONDS));

            return null;
        } catch (RateLimitException $e) {
            Log::warning('TmdbVerificationService: TMDb rate limit exceeded', [
                'slug' => $slug,
                'error' => $e->getMessage(),
            ]);

            return null;
        } catch (TMDBException $e) {
            Log::error('TmdbVerificationService: TMDb API error', [
                'slug' => $slug,
                'error' => $e->getMessage(),
            ]);

            return null;
        } catch (\Throwable $e) {
            Log::error('TmdbVerificationService: unexpected error', [
                'slug' => $slug,
                'error' => $e->getMessage(),
            ]);

            return null;
        }
    }

    /**
     * Search for TV series in TMDb (returns multiple results for disambiguation).
     * Filters for scripted TV series only.
     *
     * @return array<int, array{name: string, first_air_date: string, overview: string, id: int}>
     */
    public function searchTvSeries(string $slug, int $limit = 5): array
    {
        // Check feature flag first
        if (! Feature::active('tmdb_verification')) {
            Log::debug('TmdbVerificationService: TMDb verification disabled by feature flag', ['slug' => $slug]);

            return [];
        }

        $cacheKey = 'tmdb:tv_series:search:'.$slug.':'.$limit;

        // Check cache first
        if ($cached = Cache::get($cacheKey)) {
            Log::debug('TmdbVerificationService: cache hit for TV series search', ['slug' => $slug]);

            return $cached === 'NOT_FOUND' ? [] : $cached;
        }

        try {
            $client = $this->getClient();
            if (! $client) {
                Log::warning('TmdbVerificationService: API key not configured', ['slug' => $slug]);

                return [];
            }

            // Check rate limit
            if (! $this->checkRateLimit()) {
                Log::warning('TmdbVerificationService: rate limit exceeded, skipping TMDb call', ['slug' => $slug]);

                return [];
            }

            // Parse slug to extract title and year
            $parsed = TvSeries::parseSlug($slug);
            $title = $parsed['title'];
            $year = $parsed['year'];

            // Search TMDb TV
            $query = $title;
            $response = $client->search()->tv($query);
            $data = json_decode($response->getBody()->getContents(), true);

            if (empty($data['results'])) {
                Log::info('TmdbVerificationService: no TV series found in TMDb', ['slug' => $slug]);
                Cache::put($cacheKey, 'NOT_FOUND', now()->addSeconds(self::CACHE_TTL_SECONDS));

                return [];
            }

            // Filter for scripted TV series only
            $scriptedResults = array_filter($data['results'], function ($result) {
                return $this->isScriptedTv($result);
            });

            // Limit results
            $scriptedResults = array_slice($scriptedResults, 0, $limit);

            // Transform results
            $results = array_map(function ($result) {
                return [
                    'name' => $result['name'] ?? '',
                    'first_air_date' => $result['first_air_date'] ?? '',
                    'overview' => $result['overview'] ?? '',
                    'id' => $result['id'] ?? 0,
                ];
            }, $scriptedResults);

            // Cache the results
            Cache::put($cacheKey, $results, now()->addSeconds(self::CACHE_TTL_SECONDS));

            return $results;
        } catch (\Throwable $e) {
            Log::error('TmdbVerificationService: error searching TV series', [
                'slug' => $slug,
                'error' => $e->getMessage(),
            ]);

            return [];
        }
    }

    /**
     * Verify if TV show exists in TMDb.
     * Distinguishes TV Shows (unscripted) from TV Series (scripted) based on genres.
     *
     * @return array{name: string, first_air_date: string, overview: string, id: int}|null
     */
    public function verifyTvShow(string $slug): ?array
    {
        // Check feature flag first
        if (! Feature::active('tmdb_verification')) {
            Log::debug('TmdbVerificationService: TMDb verification disabled by feature flag', ['slug' => $slug]);

            return null;
        }

        $cacheKey = 'tmdb:tv_show:'.$slug;

        // Check cache first
        if ($cached = Cache::get($cacheKey)) {
            Log::debug('TmdbVerificationService: cache hit for TV show', ['slug' => $slug]);

            return $cached === 'NOT_FOUND' ? null : $cached;
        }

        try {
            $client = $this->getClient();
            if (! $client) {
                Log::warning('TmdbVerificationService: API key not configured', ['slug' => $slug]);

                return null;
            }

            // Check rate limit
            if (! $this->checkRateLimit()) {
                Log::warning('TmdbVerificationService: rate limit exceeded, skipping TMDb call', ['slug' => $slug]);

                return null;
            }

            // Parse slug to extract title and year
            $parsed = TvShow::parseSlug($slug);
            $title = $parsed['title'];
            $year = $parsed['year'];

            // Search TMDb TV
            $query = $title;
            $response = $client->search()->tv($query);
            $data = json_decode($response->getBody()->getContents(), true);

            if (empty($data['results'])) {
                Log::info('TmdbVerificationService: TV show not found in TMDb', ['slug' => $slug]);
                Cache::put($cacheKey, 'NOT_FOUND', now()->addSeconds(self::CACHE_TTL_SECONDS));

                return null;
            }

            // Filter for unscripted TV shows (exclude scripted series)
            $unscriptedResults = array_filter($data['results'], function ($result) {
                return ! $this->isScriptedTv($result);
            });

            if (empty($unscriptedResults)) {
                Log::info('TmdbVerificationService: no unscripted TV show found (only scripted series)', ['slug' => $slug]);
                Cache::put($cacheKey, 'NOT_FOUND', now()->addSeconds(self::CACHE_TTL_SECONDS));

                return null;
            }

            // Prioritize by year if available
            $bestMatch = null;
            if ($year !== null) {
                $matchingYear = array_filter($unscriptedResults, function ($result) use ($year) {
                    $resultYear = ! empty($result['first_air_date']) ? (int) substr($result['first_air_date'], 0, 4) : null;

                    return $resultYear === $year;
                });

                if (! empty($matchingYear)) {
                    $bestMatch = reset($matchingYear);
                }
            }

            if ($bestMatch === null) {
                $bestMatch = reset($unscriptedResults);
            }

            // Get TV details
            $tvDetails = $this->getTvDetails($bestMatch['id']);

            if (empty($tvDetails)) {
                Cache::put($cacheKey, 'NOT_FOUND', now()->addSeconds(self::CACHE_TTL_SECONDS));

                return null;
            }

            $result = [
                'name' => $tvDetails['name'] ?? $bestMatch['name'],
                'first_air_date' => $tvDetails['first_air_date'] ?? $bestMatch['first_air_date'] ?? '',
                'overview' => $tvDetails['overview'] ?? $bestMatch['overview'] ?? '',
                'id' => $bestMatch['id'],
            ];

            // Save snapshot
            try {
                TmdbSnapshot::updateOrCreate(
                    [
                        'tmdb_id' => $bestMatch['id'],
                        'tmdb_type' => 'tv',
                        'entity_type' => 'TV_SHOW',
                    ],
                    [
                        'entity_id' => null, // Will be set when entity is created
                        'raw_data' => $tvDetails,
                        'fetched_at' => now(),
                    ]
                );
            } catch (\Throwable $e) {
                Log::warning('TmdbVerificationService: failed to save TV show snapshot', [
                    'slug' => $slug,
                    'tmdb_id' => $bestMatch['id'],
                    'error' => $e->getMessage(),
                ]);
            }

            Log::info('TmdbVerificationService: TV show found in TMDb', [
                'slug' => $slug,
                'tmdb_id' => $bestMatch['id'],
                'name' => $result['name'],
            ]);

            // Cache the result
            Cache::put($cacheKey, $result, now()->addSeconds(self::CACHE_TTL_SECONDS));

            return $result;
        } catch (NotFoundException $e) {
            Log::info('TmdbVerificationService: TV show not found in TMDb (NotFoundException)', ['slug' => $slug]);
            Cache::put($cacheKey, 'NOT_FOUND', now()->addSeconds(self::CACHE_TTL_SECONDS));

            return null;
        } catch (RateLimitException $e) {
            Log::warning('TmdbVerificationService: TMDb rate limit exceeded', [
                'slug' => $slug,
                'error' => $e->getMessage(),
            ]);

            return null;
        } catch (TMDBException $e) {
            Log::error('TmdbVerificationService: TMDb API error', [
                'slug' => $slug,
                'error' => $e->getMessage(),
            ]);

            return null;
        } catch (\Throwable $e) {
            Log::error('TmdbVerificationService: unexpected error', [
                'slug' => $slug,
                'error' => $e->getMessage(),
            ]);

            return null;
        }
    }

    /**
     * Search for TV shows in TMDb (returns multiple results for disambiguation).
     * Filters for unscripted TV shows only.
     *
     * @return array<int, array{name: string, first_air_date: string, overview: string, id: int}>
     */
    public function searchTvShows(string $slug, int $limit = 5): array
    {
        // Check feature flag first
        if (! Feature::active('tmdb_verification')) {
            Log::debug('TmdbVerificationService: TMDb verification disabled by feature flag', ['slug' => $slug]);

            return [];
        }

        $cacheKey = 'tmdb:tv_show:search:'.$slug.':'.$limit;

        // Check cache first
        if ($cached = Cache::get($cacheKey)) {
            Log::debug('TmdbVerificationService: cache hit for TV show search', ['slug' => $slug]);

            return $cached === 'NOT_FOUND' ? [] : $cached;
        }

        try {
            $client = $this->getClient();
            if (! $client) {
                Log::warning('TmdbVerificationService: API key not configured', ['slug' => $slug]);

                return [];
            }

            // Check rate limit
            if (! $this->checkRateLimit()) {
                Log::warning('TmdbVerificationService: rate limit exceeded, skipping TMDb call', ['slug' => $slug]);

                return [];
            }

            // Parse slug to extract title and year
            $parsed = TvShow::parseSlug($slug);
            $title = $parsed['title'];
            $year = $parsed['year'];

            // Search TMDb TV
            $query = $title;
            $response = $client->search()->tv($query);
            $data = json_decode($response->getBody()->getContents(), true);

            if (empty($data['results'])) {
                Log::info('TmdbVerificationService: no TV shows found in TMDb', ['slug' => $slug]);
                Cache::put($cacheKey, 'NOT_FOUND', now()->addSeconds(self::CACHE_TTL_SECONDS));

                return [];
            }

            // Filter for unscripted TV shows only
            $unscriptedResults = array_filter($data['results'], function ($result) {
                return ! $this->isScriptedTv($result);
            });

            // Limit results
            $unscriptedResults = array_slice($unscriptedResults, 0, $limit);

            // Transform results
            $results = array_map(function ($result) {
                return [
                    'name' => $result['name'] ?? '',
                    'first_air_date' => $result['first_air_date'] ?? '',
                    'overview' => $result['overview'] ?? '',
                    'id' => $result['id'] ?? 0,
                ];
            }, $unscriptedResults);

            // Cache the results
            Cache::put($cacheKey, $results, now()->addSeconds(self::CACHE_TTL_SECONDS));

            return $results;
        } catch (\Throwable $e) {
            Log::error('TmdbVerificationService: error searching TV shows', [
                'slug' => $slug,
                'error' => $e->getMessage(),
            ]);

            return [];
        }
    }

    /**
     * Check if TV result from TMDb is scripted (TV Series) or unscripted (TV Show).
     * Scripted genres: Drama, Comedy, Sci-Fi, Crime, Thriller, Action, Adventure, Fantasy, Horror, Mystery, Romance
     * Unscripted genres: Talk, Reality, News, Documentary, Variety, Game Show
     *
     * @param  array<string, mixed>  $result  TMDb TV result
     */
    private function isScriptedTv(array $result): bool
    {
        $genres = $result['genre_ids'] ?? [];
        $genreNames = $result['genres'] ?? [];

        // Scripted genres (TV Series)
        $scriptedGenres = [
            'Drama', 'Comedy', 'Sci-Fi', 'Crime', 'Thriller', 'Action', 'Adventure',
            'Fantasy', 'Horror', 'Mystery', 'Romance', 'Animation', 'War', 'Western',
        ];

        // Unscripted genres (TV Show)
        $unscriptedGenres = [
            'Talk', 'Reality', 'News', 'Documentary', 'Variety', 'Game Show',
        ];

        // Check genre names if available
        if (! empty($genreNames)) {
            foreach ($genreNames as $genre) {
                $genreName = is_array($genre) ? ($genre['name'] ?? '') : (string) $genre;
                if (in_array($genreName, $unscriptedGenres, true)) {
                    return false; // Unscripted
                }
                if (in_array($genreName, $scriptedGenres, true)) {
                    return true; // Scripted
                }
            }
        }

        // Default: if no clear genre match, assume scripted (most TV content is scripted)
        // This can be refined based on TMDb genre IDs if needed
        return true;
    }

    /**
     * Get TV details from TMDb by ID.
     *
     * @param  int  $tmdbId  TMDb TV ID
     * @return array<string, mixed> TV details
     */
    private function getTvDetails(int $tmdbId): array
    {
        try {
            $client = $this->getClient();
            if (! $client) {
                return [];
            }

            // Get TV details (similar to getMovieDetails)
            $response = $client->tv()->getDetails($tmdbId, ['append_to_response' => 'credits,similar']);
            $data = json_decode($response->getBody()->getContents(), true);

            return $data ?? [];
        } catch (\Throwable $e) {
            Log::warning('TmdbVerificationService: failed to get TV details', [
                'tmdb_id' => $tmdbId,
                'error' => $e->getMessage(),
            ]);

            return [];
        }
    }
}
