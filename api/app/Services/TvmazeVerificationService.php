<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\TvSeries;
use App\Models\TvShow;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Laravel\Pennant\Feature;

/**
 * Service for verifying TV Series and TV Shows existence in TVmaze before AI generation.
 *
 * Note: TVmaze API is free and allows commercial use under CC BY-SA license.
 * Attribution required: Link to TVmaze (https://www.tvmaze.com) in your application.
 *
 * @see https://www.tvmaze.com/api
 * @see docs/LEGAL_TVMAZE_LICENSE.md for licensing details
 */
class TvmazeVerificationService implements EntityVerificationServiceInterface
{
    private const BASE_URL = 'https://api.tvmaze.com';

    private const CACHE_TTL_SECONDS = 86400; // 24 hours

    private const CACHE_PREFIX_TV_SERIES = 'tvmaze:tv_series:';

    private const CACHE_PREFIX_TV_SHOW = 'tvmaze:tv_show:';

    // TVmaze API rate limits: 20 requests per 10 seconds
    private const RATE_LIMIT_REQUESTS = 20;

    private const RATE_LIMIT_WINDOW_SECONDS = 10;

    private const RATE_LIMIT_KEY = 'tvmaze:rate_limit:window';

    /**
     * Verify if TV series exists in TVmaze.
     *
     * @return array{name: string, first_air_date: string, overview: string, id: int}|null
     */
    public function verifyTvSeries(string $slug): ?array
    {
        // Check feature flag first
        if (! Feature::active('tvmaze_verification')) {
            Log::debug('TvmazeVerificationService: TVmaze verification disabled by feature flag', ['slug' => $slug]);

            return null;
        }

        $cacheKey = self::CACHE_PREFIX_TV_SERIES.$slug;

        // Check cache first
        if ($cached = Cache::get($cacheKey)) {
            Log::debug('TvmazeVerificationService: cache hit for TV series', ['slug' => $slug]);

            return $cached === 'NOT_FOUND' ? null : $cached;
        }

        try {
            // Check rate limit before making API call
            if (! $this->checkRateLimit()) {
                Log::warning('TvmazeVerificationService: rate limit exceeded, skipping TVmaze call', ['slug' => $slug]);

                return null;
            }

            // Parse slug to extract title and year
            $parsed = TvSeries::parseSlug($slug);
            $title = $parsed['title'];
            $year = $parsed['year'];

            Log::info('TvmazeVerificationService: searching TVmaze for TV series', [
                'slug' => $slug,
                'title' => $title,
                'year' => $year,
            ]);

            // Use single search endpoint for best match
            $response = Http::timeout(10)->get(self::BASE_URL.'/singlesearch/shows', [
                'q' => $title,
            ]);

            if (! $response->successful()) {
                Log::warning('TvmazeVerificationService: TVmaze API error', [
                    'slug' => $slug,
                    'status' => $response->status(),
                    'body' => substr($response->body(), 0, 500),
                ]);
                Cache::put($cacheKey, 'NOT_FOUND', now()->addSeconds(self::CACHE_TTL_SECONDS));

                return null;
            }

            $data = $response->json();

            if (empty($data)) {
                Log::info('TvmazeVerificationService: TV series not found in TVmaze', ['slug' => $slug]);
                Cache::put($cacheKey, 'NOT_FOUND', now()->addSeconds(self::CACHE_TTL_SECONDS));

                return null;
            }

            // Verify year match if year was specified in slug
            if ($year !== null) {
                $showYear = ! empty($data['premiered']) ? (int) substr($data['premiered'], 0, 4) : null;

                if ($showYear !== $year) {
                    Log::info('TvmazeVerificationService: show year does not match requested year', [
                        'slug' => $slug,
                        'requested_year' => $year,
                        'show_year' => $showYear,
                        'show_name' => $data['name'] ?? null,
                    ]);
                    Cache::put($cacheKey, 'NOT_FOUND', now()->addSeconds(self::CACHE_TTL_SECONDS));

                    return null;
                }
            }

            $result = [
                'name' => $data['name'] ?? '',
                'first_air_date' => $data['premiered'] ?? '',
                'overview' => $data['summary'] ?? '',
                'id' => $data['id'] ?? 0,
            ];

            Log::info('TvmazeVerificationService: TV series found in TVmaze', [
                'slug' => $slug,
                'tvmaze_id' => $result['id'],
                'name' => $result['name'],
            ]);

            // Cache the result
            Cache::put($cacheKey, $result, now()->addSeconds(self::CACHE_TTL_SECONDS));

            return $result;
        } catch (\Throwable $e) {
            Log::error('TvmazeVerificationService: unexpected error', [
                'slug' => $slug,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return null;
        }
    }

    /**
     * Search for TV series in TVmaze (returns multiple results for disambiguation).
     *
     * @return array<int, array{name: string, first_air_date: string, overview: string, id: int}>
     */
    public function searchTvSeries(string $slug, int $limit = 5): array
    {
        // Check feature flag first
        if (! Feature::active('tvmaze_verification')) {
            Log::debug('TvmazeVerificationService: TVmaze verification disabled by feature flag', ['slug' => $slug]);

            return [];
        }

        $cacheKey = self::CACHE_PREFIX_TV_SERIES.'search:'.$slug.':'.$limit;

        // Check cache first
        if ($cached = Cache::get($cacheKey)) {
            Log::debug('TvmazeVerificationService: cache hit for TV series search', ['slug' => $slug]);

            return $cached === 'NOT_FOUND' ? [] : $cached;
        }

        try {
            // Check rate limit
            if (! $this->checkRateLimit()) {
                Log::warning('TvmazeVerificationService: rate limit exceeded, skipping TVmaze call', ['slug' => $slug]);

                return [];
            }

            // Parse slug to extract title and year
            $parsed = TvSeries::parseSlug($slug);
            $title = $parsed['title'];
            $year = $parsed['year'];

            Log::debug('TvmazeVerificationService: searching TV series in TVmaze', [
                'slug' => $slug,
                'title' => $title,
                'year' => $year,
            ]);

            // Use search endpoint for multiple results
            $response = Http::timeout(10)->get(self::BASE_URL.'/search/shows', [
                'q' => $title,
            ]);

            if (! $response->successful()) {
                Log::warning('TvmazeVerificationService: TVmaze API error', [
                    'slug' => $slug,
                    'status' => $response->status(),
                ]);
                Cache::put($cacheKey, 'NOT_FOUND', now()->addSeconds(self::CACHE_TTL_SECONDS));

                return [];
            }

            $data = $response->json();

            if (empty($data) || ! is_array($data)) {
                Log::info('TvmazeVerificationService: no TV series found in TVmaze', ['slug' => $slug]);
                Cache::put($cacheKey, 'NOT_FOUND', now()->addSeconds(self::CACHE_TTL_SECONDS));

                return [];
            }

            // Extract show data from search results (format: [['show' => {...}, 'score' => ...], ...])
            $shows = [];
            foreach ($data as $item) {
                if (isset($item['show'])) {
                    $shows[] = $item['show'];
                }
            }

            if (empty($shows)) {
                Log::info('TvmazeVerificationService: no TV series found in TVmaze', ['slug' => $slug]);
                Cache::put($cacheKey, 'NOT_FOUND', now()->addSeconds(self::CACHE_TTL_SECONDS));

                return [];
            }

            // Prioritize by year if available
            if ($year !== null) {
                $matchingYear = array_filter($shows, function ($show) use ($year) {
                    $showYear = ! empty($show['premiered']) ? (int) substr($show['premiered'], 0, 4) : null;

                    return $showYear === $year;
                });

                if (! empty($matchingYear)) {
                    // Move matching year results to the beginning
                    $otherResults = array_filter($shows, function ($show) use ($year) {
                        $showYear = ! empty($show['premiered']) ? (int) substr($show['premiered'], 0, 4) : null;

                        return $showYear !== $year;
                    });
                    $shows = array_merge(array_values($matchingYear), array_values($otherResults));

                    Log::info('TvmazeVerificationService: prioritized results with matching year', [
                        'slug' => $slug,
                        'year' => $year,
                        'matching_count' => count($matchingYear),
                    ]);
                }
            }

            // Limit results
            $shows = array_slice($shows, 0, $limit);

            // Transform results
            $results = array_map(function ($show) {
                return [
                    'name' => $show['name'] ?? '',
                    'first_air_date' => $show['premiered'] ?? '',
                    'overview' => $show['summary'] ?? '',
                    'id' => $show['id'] ?? 0,
                ];
            }, $shows);

            Log::info('TvmazeVerificationService: found TV series in TVmaze', [
                'slug' => $slug,
                'count' => count($results),
            ]);

            // Cache the results
            Cache::put($cacheKey, $results, now()->addSeconds(self::CACHE_TTL_SECONDS));

            return $results;
        } catch (\Throwable $e) {
            Log::error('TvmazeVerificationService: error searching TV series', [
                'slug' => $slug,
                'error' => $e->getMessage(),
            ]);

            return [];
        }
    }

    /**
     * Verify if TV show exists in TVmaze.
     *
     * @return array{name: string, first_air_date: string, overview: string, id: int}|null
     */
    public function verifyTvShow(string $slug): ?array
    {
        // Check feature flag first
        if (! Feature::active('tvmaze_verification')) {
            Log::debug('TvmazeVerificationService: TVmaze verification disabled by feature flag', ['slug' => $slug]);

            return null;
        }

        $cacheKey = self::CACHE_PREFIX_TV_SHOW.$slug;

        // Check cache first
        if ($cached = Cache::get($cacheKey)) {
            Log::debug('TvmazeVerificationService: cache hit for TV show', ['slug' => $slug]);

            return $cached === 'NOT_FOUND' ? null : $cached;
        }

        try {
            // Check rate limit before making API call
            if (! $this->checkRateLimit()) {
                Log::warning('TvmazeVerificationService: rate limit exceeded, skipping TVmaze call', ['slug' => $slug]);

                return null;
            }

            // Parse slug to extract title and year
            $parsed = TvShow::parseSlug($slug);
            $title = $parsed['title'];
            $year = $parsed['year'];

            Log::info('TvmazeVerificationService: searching TVmaze for TV show', [
                'slug' => $slug,
                'title' => $title,
                'year' => $year,
            ]);

            // Use single search endpoint for best match
            $response = Http::timeout(10)->get(self::BASE_URL.'/singlesearch/shows', [
                'q' => $title,
            ]);

            if (! $response->successful()) {
                Log::warning('TvmazeVerificationService: TVmaze API error', [
                    'slug' => $slug,
                    'status' => $response->status(),
                    'body' => substr($response->body(), 0, 500),
                ]);
                Cache::put($cacheKey, 'NOT_FOUND', now()->addSeconds(self::CACHE_TTL_SECONDS));

                return null;
            }

            $data = $response->json();

            if (empty($data)) {
                Log::info('TvmazeVerificationService: TV show not found in TVmaze', ['slug' => $slug]);
                Cache::put($cacheKey, 'NOT_FOUND', now()->addSeconds(self::CACHE_TTL_SECONDS));

                return null;
            }

            // Verify year match if year was specified in slug
            if ($year !== null) {
                $showYear = ! empty($data['premiered']) ? (int) substr($data['premiered'], 0, 4) : null;

                if ($showYear !== $year) {
                    Log::info('TvmazeVerificationService: show year does not match requested year', [
                        'slug' => $slug,
                        'requested_year' => $year,
                        'show_year' => $showYear,
                        'show_name' => $data['name'] ?? null,
                    ]);
                    Cache::put($cacheKey, 'NOT_FOUND', now()->addSeconds(self::CACHE_TTL_SECONDS));

                    return null;
                }
            }

            $result = [
                'name' => $data['name'] ?? '',
                'first_air_date' => $data['premiered'] ?? '',
                'overview' => $data['summary'] ?? '',
                'id' => $data['id'] ?? 0,
            ];

            Log::info('TvmazeVerificationService: TV show found in TVmaze', [
                'slug' => $slug,
                'tvmaze_id' => $result['id'],
                'name' => $result['name'],
            ]);

            // Cache the result
            Cache::put($cacheKey, $result, now()->addSeconds(self::CACHE_TTL_SECONDS));

            return $result;
        } catch (\Throwable $e) {
            Log::error('TvmazeVerificationService: unexpected error', [
                'slug' => $slug,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return null;
        }
    }

    /**
     * Search for TV shows in TVmaze (returns multiple results for disambiguation).
     *
     * @return array<int, array{name: string, first_air_date: string, overview: string, id: int}>
     */
    public function searchTvShows(string $slug, int $limit = 5): array
    {
        // Check feature flag first
        if (! Feature::active('tvmaze_verification')) {
            Log::debug('TvmazeVerificationService: TVmaze verification disabled by feature flag', ['slug' => $slug]);

            return [];
        }

        $cacheKey = self::CACHE_PREFIX_TV_SHOW.'search:'.$slug.':'.$limit;

        // Check cache first
        if ($cached = Cache::get($cacheKey)) {
            Log::debug('TvmazeVerificationService: cache hit for TV show search', ['slug' => $slug]);

            return $cached === 'NOT_FOUND' ? [] : $cached;
        }

        try {
            // Check rate limit
            if (! $this->checkRateLimit()) {
                Log::warning('TvmazeVerificationService: rate limit exceeded, skipping TVmaze call', ['slug' => $slug]);

                return [];
            }

            // Parse slug to extract title and year
            $parsed = TvShow::parseSlug($slug);
            $title = $parsed['title'];
            $year = $parsed['year'];

            Log::debug('TvmazeVerificationService: searching TV shows in TVmaze', [
                'slug' => $slug,
                'title' => $title,
                'year' => $year,
            ]);

            // Use search endpoint for multiple results
            $response = Http::timeout(10)->get(self::BASE_URL.'/search/shows', [
                'q' => $title,
            ]);

            if (! $response->successful()) {
                Log::warning('TvmazeVerificationService: TVmaze API error', [
                    'slug' => $slug,
                    'status' => $response->status(),
                ]);
                Cache::put($cacheKey, 'NOT_FOUND', now()->addSeconds(self::CACHE_TTL_SECONDS));

                return [];
            }

            $data = $response->json();

            if (empty($data) || ! is_array($data)) {
                Log::info('TvmazeVerificationService: no TV shows found in TVmaze', ['slug' => $slug]);
                Cache::put($cacheKey, 'NOT_FOUND', now()->addSeconds(self::CACHE_TTL_SECONDS));

                return [];
            }

            // Extract show data from search results (format: [['show' => {...}, 'score' => ...], ...])
            $shows = [];
            foreach ($data as $item) {
                if (isset($item['show'])) {
                    $shows[] = $item['show'];
                }
            }

            if (empty($shows)) {
                Log::info('TvmazeVerificationService: no TV shows found in TVmaze', ['slug' => $slug]);
                Cache::put($cacheKey, 'NOT_FOUND', now()->addSeconds(self::CACHE_TTL_SECONDS));

                return [];
            }

            // Prioritize by year if available
            if ($year !== null) {
                $matchingYear = array_filter($shows, function ($show) use ($year) {
                    $showYear = ! empty($show['premiered']) ? (int) substr($show['premiered'], 0, 4) : null;

                    return $showYear === $year;
                });

                if (! empty($matchingYear)) {
                    // Move matching year results to the beginning
                    $otherResults = array_filter($shows, function ($show) use ($year) {
                        $showYear = ! empty($show['premiered']) ? (int) substr($show['premiered'], 0, 4) : null;

                        return $showYear !== $year;
                    });
                    $shows = array_merge(array_values($matchingYear), array_values($otherResults));

                    Log::info('TvmazeVerificationService: prioritized results with matching year', [
                        'slug' => $slug,
                        'year' => $year,
                        'matching_count' => count($matchingYear),
                    ]);
                }
            }

            // Limit results
            $shows = array_slice($shows, 0, $limit);

            // Transform results
            $results = array_map(function ($show) {
                return [
                    'name' => $show['name'] ?? '',
                    'first_air_date' => $show['premiered'] ?? '',
                    'overview' => $show['summary'] ?? '',
                    'id' => $show['id'] ?? 0,
                ];
            }, $shows);

            Log::info('TvmazeVerificationService: found TV shows in TVmaze', [
                'slug' => $slug,
                'count' => count($results),
            ]);

            // Cache the results
            Cache::put($cacheKey, $results, now()->addSeconds(self::CACHE_TTL_SECONDS));

            return $results;
        } catch (\Throwable $e) {
            Log::error('TvmazeVerificationService: error searching TV shows', [
                'slug' => $slug,
                'error' => $e->getMessage(),
            ]);

            return [];
        }
    }

    /**
     * Check if we can make a TVmaze API call (rate limiting).
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

            Log::warning('TvmazeVerificationService: rate limit exceeded', [
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
     * Perform a lightweight health check against the TVmaze API.
     *
     * @return array{success: bool, service: string, message?: string, status?: int, error?: string}
     */
    public function health(): array
    {
        try {
            // Perform a lightweight test request (get show by ID 1 - Under the Dome)
            // This is a simple, fast endpoint that doesn't require complex parameters
            $response = Http::timeout(5)->get(self::BASE_URL.'/shows/1');

            $statusCode = $response->status();

            if ($statusCode >= 200 && $statusCode < 300) {
                return [
                    'success' => true,
                    'service' => 'tvmaze',
                    'message' => 'TVmaze API is accessible',
                    'status' => $statusCode,
                ];
            }

            return [
                'success' => false,
                'service' => 'tvmaze',
                'error' => "TVmaze API returned status {$statusCode}",
                'status' => $statusCode,
            ];
        } catch (\Throwable $e) {
            return [
                'success' => false,
                'service' => 'tvmaze',
                'error' => 'TVmaze API is not reachable: '.$e->getMessage(),
                'status' => 503,
            ];
        }
    }

    // Methods required by interface but not applicable to TVmaze (Movies/People are handled by TMDB)

    /**
     * Verify if movie exists in external database.
     * Not applicable to TVmaze (TVmaze only handles TV shows).
     *
     * @return null
     */
    public function verifyMovie(string $slug): ?array
    {
        return null;
    }

    /**
     * Search for movies in external database.
     * Not applicable to TVmaze (TVmaze only handles TV shows).
     *
     * @return array<int, array{title: string, release_date: string, overview: string, id: int, director?: string}>
     */
    public function searchMovies(string $slug, int $limit = 5): array
    {
        return [];
    }

    /**
     * Verify if person exists in external database.
     * Not applicable to TVmaze (TVmaze only handles TV shows).
     *
     * @return null
     */
    public function verifyPerson(string $slug): ?array
    {
        return null;
    }

    /**
     * Search for people in external database.
     * Not applicable to TVmaze (TVmaze only handles TV shows).
     *
     * @return array<int, array{name: string, birthday?: string, place_of_birth?: string, id: int, biography?: string}>
     */
    public function searchPeople(string $slug, int $limit = 5): array
    {
        return [];
    }
}
