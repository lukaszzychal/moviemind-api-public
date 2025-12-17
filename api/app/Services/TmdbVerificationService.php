<?php

declare(strict_types=1);

namespace App\Services;

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

            // Convert slug to search query (replace hyphens with spaces)
            $query = str_replace('-', ' ', $slug);

            Log::info('TmdbVerificationService: searching TMDb for movies', [
                'slug' => $slug,
                'query' => $query,
                'limit' => $limit,
            ]);

            $response = $client->search()->movies($query);
            $data = json_decode($response->getBody()->getContents(), true);

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

            // Convert slug to search query (replace hyphens with spaces)
            $query = str_replace('-', ' ', $slug);

            Log::info('TmdbVerificationService: searching TMDb for movie', [
                'slug' => $slug,
                'query' => $query,
            ]);

            $response = $client->search()->movies($query);
            $data = json_decode($response->getBody()->getContents(), true);

            if (empty($data['results'])) {
                Log::info('TmdbVerificationService: movie not found in TMDb', ['slug' => $slug]);
                Cache::put($cacheKey, 'NOT_FOUND', now()->addSeconds(self::CACHE_TTL_SECONDS));

                return null;
            }

            // Get best match (first result, TMDb sorts by relevance)
            $bestMatch = $data['results'][0];

            // Get movie details to extract director
            $movieDetails = $this->getMovieDetails($bestMatch['id']);
            $director = $this->extractDirector($movieDetails);

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

            // Convert slug to search query (replace hyphens with spaces)
            $query = str_replace('-', ' ', $slug);

            Log::info('TmdbVerificationService: searching TMDb for person', [
                'slug' => $slug,
                'query' => $query,
            ]);

            $response = $client->search()->people($query);
            $data = json_decode($response->getBody()->getContents(), true);

            if (empty($data['results'])) {
                Log::info('TmdbVerificationService: person not found in TMDb', ['slug' => $slug]);
                Cache::put($cacheKey, 'NOT_FOUND', now()->addSeconds(self::CACHE_TTL_SECONDS));

                return null;
            }

            // Get best match (first result, TMDb sorts by relevance)
            $bestMatch = $data['results'][0];

            // Get person details to extract biography
            $personDetails = $this->getPersonDetails($bestMatch['id']);

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

            // Extract name from slug for search
            $query = str_replace('-', ' ', $slug);

            Log::debug('TmdbVerificationService: searching people in TMDb', [
                'slug' => $slug,
                'query' => $query,
            ]);

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
     * Get movie details from TMDb including credits.
     */
    private function getMovieDetails(int $tmdbId): array
    {
        try {
            $client = $this->getClient();
            if (! $client) {
                return [];
            }

            // Get details with credits appended
            $response = $client->movies()->getDetails($tmdbId, ['append_to_response' => 'credits']);
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
}
