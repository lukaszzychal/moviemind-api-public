<?php

namespace App\Http\Controllers\Api;

use App\Actions\QueueMovieGenerationAction;
use App\Enums\Locale;
use App\Enums\RelationshipType;
use App\Helpers\SlugValidator;
use App\Http\Controllers\Controller;
use App\Http\Requests\SearchMovieRequest;
use App\Http\Resources\MovieResource;
use App\Http\Responses\MovieResponseFormatter;
use App\Models\Movie;
use App\Models\MovieRelationship;
use App\Repositories\MovieRepository;
use App\Services\EntityVerificationServiceInterface;
use App\Services\HateoasService;
use App\Services\MovieRetrievalService;
use App\Services\MovieSearchService;
use App\Services\TmdbVerificationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

class MovieController extends Controller
{
    private const CACHE_TTL_SECONDS = 3600;

    public function __construct(
        private readonly MovieRepository $movieRepository,
        private readonly HateoasService $hateoas,
        private readonly QueueMovieGenerationAction $queueMovieGenerationAction,
        private readonly EntityVerificationServiceInterface $tmdbVerificationService,
        private readonly MovieSearchService $movieSearchService,
        private readonly MovieRetrievalService $movieRetrievalService,
        private readonly MovieResponseFormatter $responseFormatter
    ) {}

    public function index(Request $request): JsonResponse
    {
        $q = $request->query('q');
        $movies = $this->movieRepository->searchMovies($q, 50);
        $data = $movies->map(function (Movie $movie) {
            $resource = MovieResource::make($movie)->additional([
                '_links' => $this->hateoas->movieLinks($movie),
            ]);

            return $resource->resolve();
        });

        return $this->responseFormatter->formatMovieList($data->toArray());
    }

    /**
     * Search for movies with advanced criteria (local + external).
     */
    public function search(SearchMovieRequest $request): JsonResponse
    {
        $criteria = $request->getSearchCriteria();
        $searchResult = $this->movieSearchService->search($criteria);

        // If searchResult has results (found, partial, or ambiguous), return it immediately
        // For search endpoint, ambiguous results should return 200 (not 300) with normal structure
        // Don't try fallback logic if we already have results
        if (! $searchResult->isEmpty()) {
            // For search endpoint, always return 200 with normal structure (even if ambiguous)
            // Use toArray() directly to get consistent structure
            return response()->json($searchResult->toArray(), 200);
        }

        // Fallback: If no results found and query looks like a slug, try to verify it in TMDB and queue generation
        if (! empty($criteria['q'])) {
            $query = $criteria['q'];

            // Check if query is already in slug format (contains hyphens, no spaces, lowercase)
            // or try converting it to slug format
            $potentialSlug = preg_match('/^[a-z0-9-]+$/', $query) ? $query : \Illuminate\Support\Str::slug($query);

            $validation = SlugValidator::validateMovieSlug($potentialSlug);

            if ($validation['valid']) {
                // First try: use retrieveMovie (which uses verifyMovie - exact match)
                $result = $this->movieRetrievalService->retrieveMovie($potentialSlug, null);

                // Only queue generation if it's a valid single match, not disambiguation
                if ($result->isGenerationQueued() && ! $result->isDisambiguation()) {
                    return $this->responseFormatter->formatGenerationQueued($result->getAdditionalData() ?? []);
                }

                // Second try: if verifyMovie didn't find it (e.g., wrong year in slug),
                // try searching TMDB and matching by generated slug
                $parsed = Movie::parseSlug($potentialSlug);
                $title = $parsed['title'];

                // Search TMDB with title (without year, as year in slug might be wrong)
                $tmdbResults = $this->tmdbVerificationService->searchMovies($title, 10);

                foreach ($tmdbResults as $tmdbMovie) {
                    $year = ! empty($tmdbMovie['release_date']) ? (int) substr($tmdbMovie['release_date'], 0, 4) : null;
                    $director = $tmdbMovie['director'] ?? null;
                    $generatedSlug = Movie::generateSlug($tmdbMovie['title'], $year, $director);

                    // If generated slug matches potential slug, queue generation
                    if ($generatedSlug === $potentialSlug) {
                        $generationResult = $this->queueMovieGenerationAction->handle(
                            $potentialSlug,
                            confidence: $validation['confidence'],
                            locale: \App\Enums\Locale::EN_US->value,
                            tmdbData: $tmdbMovie
                        );

                        return $this->responseFormatter->formatGenerationQueued($generationResult);
                    }
                }
            }
        }

        return $this->responseFormatter->formatSearchResult($searchResult);
    }

    public function show(Request $request, string $slug): JsonResponse
    {
        $descriptionId = $this->normalizeDescriptionId($request->query('description_id'));
        if ($descriptionId === false) {
            return $this->responseFormatter->formatError('Invalid description_id parameter', 422);
        }

        // Handle disambiguation selection (special case - user selects specific slug from disambiguation)
        // Note: Disambiguation now uses slugs instead of tmdb_id
        $selectedSlug = $request->query('slug');
        if ($selectedSlug !== null) {
            return $this->handleDisambiguationSelection($slug, (string) $selectedSlug);
        }

        $result = $this->movieRetrievalService->retrieveMovie($slug, $descriptionId);

        $response = $this->responseFormatter->formatFromResult($result, $slug);

        // Cache successful responses (but not disambiguation - they should be fresh)
        if ($result->isFound() && ! $result->isCached() && ! $result->isDisambiguation()) {
            $cacheKey = $this->cacheKey($slug, $descriptionId);
            $responseData = json_decode($response->getContent(), true);
            Cache::put($cacheKey, $responseData, now()->addSeconds(self::CACHE_TTL_SECONDS));
        }

        return $response;
    }

    /**
     * Handle disambiguation selection when user chooses specific movie by slug.
     * This method is called when user selects a slug from disambiguation options.
     */
    private function handleDisambiguationSelection(string $originalSlug, string $selectedSlug): JsonResponse
    {
        // Find movie by selected slug
        $movie = $this->movieRepository->findBySlugWithRelations($selectedSlug);

        if (! $movie) {
            // Movie doesn't exist yet - need to find it in TMDb and create it
            // Search for movies matching the original slug
            $searchResults = $this->tmdbVerificationService->searchMovies($originalSlug, 10);

            // Find the one that matches the selected slug
            $selectedMovie = null;
            foreach ($searchResults as $result) {
                $year = ! empty($result['release_date']) ? substr($result['release_date'], 0, 4) : null;
                $director = $result['director'] ?? null;
                $generatedSlug = Movie::generateSlug($result['title'], $year ? (int) $year : null, $director);

                if ($generatedSlug === $selectedSlug) {
                    $selectedMovie = $result;
                    break;
                }
            }

            if (! $selectedMovie) {
                return $this->responseFormatter->formatDisambiguationSelectionNotFound();
            }

            // Re-validate slug for confidence score
            $validation = SlugValidator::validateMovieSlug($selectedSlug);
            $generationResult = $this->queueMovieGenerationAction->handle(
                $selectedSlug,
                confidence: $validation['confidence'],
                locale: Locale::EN_US->value,
                tmdbData: $selectedMovie
            );

            return $this->responseFormatter->formatGenerationQueued($generationResult);
        }

        // Movie exists - return it directly
        $result = $this->movieRetrievalService->retrieveMovie($selectedSlug, null);

        return $this->responseFormatter->formatFromResult($result, $selectedSlug);
    }

    /**
     * Generate cache key for movie response.
     *
     * @param  string  $slug  Movie slug
     * @param  string|null  $descriptionId  Description ID (UUID) or null
     * @return string Cache key
     */
    private function cacheKey(string $slug, ?string $descriptionId = null): string
    {
        $suffix = $descriptionId !== null ? 'desc:'.$descriptionId : 'desc:default';

        return 'movie:'.$slug.':'.$suffix;
    }

    /**
     * Normalize description ID from request (UUID string or null).
     *
     * @param  mixed  $descriptionId  Description ID from query parameter (UUID string or null)
     * @return null|string|false Returns UUID string, null if not provided, or false if invalid
     */
    private function normalizeDescriptionId(mixed $descriptionId): null|string|false
    {
        if ($descriptionId === null || $descriptionId === '') {
            return null;
        }

        $descriptionId = (string) $descriptionId;

        // Validate UUID format (UUIDv7 format: xxxxxxxx-xxxx-xxxx-xxxx-xxxxxxxxxxxx)
        if (! preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i', $descriptionId)) {
            return false;
        }

        return $descriptionId;
    }

    /**
     * Refresh movie data from TMDb.
     */
    public function refresh(string $slug): JsonResponse
    {
        $movie = $this->movieRepository->findBySlugWithRelations($slug);
        if (! $movie) {
            return $this->responseFormatter->formatNotFound();
        }

        // Find existing snapshot
        $snapshot = \App\Models\TmdbSnapshot::where('entity_type', 'MOVIE')
            ->where('entity_id', $movie->id)
            ->first();

        if (! $snapshot) {
            return $this->responseFormatter->formatRefreshNoSnapshot();
        }

        // Refresh movie details from TMDb
        /** @var \App\Services\TmdbVerificationService $tmdbService */
        $tmdbService = $this->tmdbVerificationService;
        $freshData = $tmdbService->refreshMovieDetails($snapshot->tmdb_id);
        if (! $freshData) {
            return $this->responseFormatter->formatRefreshFailed();
        }

        // Update snapshot with fresh data
        $snapshot->update([
            'raw_data' => $freshData,
            'fetched_at' => now(),
        ]);

        // Invalidate cache
        Cache::forget($this->cacheKey($slug, null));

        return $this->responseFormatter->formatRefreshSuccess($slug, $movie->id);
    }

    /**
     * Get related movies for a given movie.
     *
     * Supports filtering by relationship type:
     * - ?type=collection - Only collection relationships (sequels, prequels, etc.)
     * - ?type=similar - Only similar movies (from TMDB API, cached)
     * - ?type=all or no filter - Both collection and similar movies
     *
     * @author MovieMind API Team
     */
    public function related(Request $request, string $slug): JsonResponse
    {
        $movie = $this->movieRepository->findBySlugWithRelations($slug);
        if (! $movie) {
            return $this->responseFormatter->formatNotFound();
        }

        // Parse type filter: collection, similar, or all (default)
        $typeFilter = $request->query('type', 'all');
        $typeFilter = strtolower((string) $typeFilter);

        $collectionMovies = [];
        $similarMovies = [];

        // Collection relationships - from database (SEQUEL, PREQUEL, SERIES, SPINOFF, REMAKE)
        if ($typeFilter === 'all' || $typeFilter === 'collection') {
            $collectionTypes = [
                RelationshipType::SEQUEL->value,
                RelationshipType::PREQUEL->value,
                RelationshipType::SERIES->value,
                RelationshipType::SPINOFF->value,
                RelationshipType::REMAKE->value,
            ];
            $collectionMovies = $movie->getRelatedMovies($collectionTypes)->map(function (Movie $relatedMovie) use ($movie) {
                $relationship = MovieRelationship::where(function ($query) use ($movie, $relatedMovie) {
                    $query->where('movie_id', $movie->id)
                        ->where('related_movie_id', $relatedMovie->id);
                })->orWhere(function ($query) use ($movie, $relatedMovie) {
                    $query->where('movie_id', $relatedMovie->id)
                        ->where('related_movie_id', $movie->id);
                })->first();

                $resource = MovieResource::make($relatedMovie)->additional([
                    '_links' => $this->hateoas->movieLinks($relatedMovie),
                ]);

                $movieData = $resource->resolve();
                $movieData['relationship_type'] = $relationship?->relationship_type->value ?? null;
                $movieData['relationship_label'] = $relationship?->relationship_type->label() ?? null;
                $movieData['relationship_order'] = $relationship?->order;

                return $movieData;
            })->values()->toArray();
        }

        // Similar movies - from TMDB API (cached, not stored in database)
        if ($typeFilter === 'all' || $typeFilter === 'similar') {
            $similarMovies = $this->getSimilarMoviesFromTmdb($movie, limit: 10);
        }

        // Combine results
        $allRelatedMovies = array_merge($collectionMovies, $similarMovies);

        return response()->json([
            'movie' => [
                'id' => $movie->id,
                'slug' => $movie->slug,
                'title' => $movie->title,
            ],
            'related_movies' => $allRelatedMovies,
            'count' => count($allRelatedMovies),
            'filters' => [
                'type' => $typeFilter,
                'collection_count' => count($collectionMovies),
                'similar_count' => count($similarMovies),
            ],
            '_links' => [
                'self' => [
                    'href' => url("/api/v1/movies/{$slug}/related"),
                ],
                'movie' => [
                    'href' => url("/api/v1/movies/{$slug}"),
                ],
                'collection' => [
                    'href' => url("/api/v1/movies/{$slug}/related?type=collection"),
                ],
                'similar' => [
                    'href' => url("/api/v1/movies/{$slug}/related?type=similar"),
                ],
            ],
        ]);
    }

    /**
     * Get similar movies from TMDB API (cached for 24 hours).
     * Similar movies are NOT stored in database to prevent cascade effect.
     *
     * @return array<int, array<string, mixed>>
     */
    private function getSimilarMoviesFromTmdb(Movie $movie, int $limit = 10): array
    {
        /** @var \App\Models\TmdbSnapshot|null $snapshot */
        $snapshot = $movie->tmdbSnapshot;
        if (! $snapshot) {
            return [];
        }

        // Cache similar movies for 24 hours (they can change, but not frequently)
        return Cache::remember(
            "movie_similar_{$movie->id}_{$limit}",
            now()->addHours(24),
            function () use ($movie, $snapshot, $limit) {
                try {
                    // Similar movies are only available from TmdbVerificationService, not from interface
                    if (! $this->tmdbVerificationService instanceof TmdbVerificationService) {
                        return [];
                    }

                    $movieDetails = $this->tmdbVerificationService->getMovieDetails($snapshot->tmdb_id);
                    $similarResults = $movieDetails['similar']['results'] ?? [];

                    // Limit to top N similar movies
                    $similarResults = array_slice($similarResults, 0, $limit);

                    // Transform TMDB results to API format
                    $transformed = [];
                    foreach ($similarResults as $similarMovie) {
                        $tmdbId = $similarMovie['id'] ?? null;
                        if (! $tmdbId) {
                            continue;
                        }

                        // Try to find existing movie in our database
                        /** @var Movie|null $existingMovie */
                        $existingMovie = Movie::where('tmdb_id', $tmdbId)->first();

                        if ($existingMovie) {
                            // Movie exists locally - return full movie data
                            $resource = MovieResource::make($existingMovie)->additional([
                                '_links' => $this->hateoas->movieLinks($existingMovie),
                            ]);

                            $movieData = $resource->resolve();
                            $movieData['relationship_type'] = 'SAME_UNIVERSE';
                            $movieData['relationship_label'] = 'Similar Movie';
                            $movieData['relationship_order'] = null;

                            $transformed[] = $movieData;
                        } else {
                            // Movie doesn't exist locally - return minimal TMDB data
                            $transformed[] = [
                                'id' => null,
                                'slug' => null,
                                'title' => $similarMovie['title'] ?? 'Unknown',
                                'release_year' => ! empty($similarMovie['release_date'])
                                    ? (int) substr($similarMovie['release_date'], 0, 4)
                                    : null,
                                'director' => null,
                                'genres' => [],
                                'description' => null,
                                'descriptions' => [],
                                'people' => [],
                                'relationship_type' => 'SAME_UNIVERSE',
                                'relationship_label' => 'Similar Movie',
                                'relationship_order' => null,
                                '_links' => [
                                    'self' => null, // Movie doesn't exist locally
                                    'generate' => [
                                        'href' => url('/api/v1/generate'),
                                        'method' => 'POST',
                                        'body' => [
                                            'entity_type' => 'MOVIE',
                                            'slug' => Movie::generateSlug(
                                                $similarMovie['title'] ?? 'Unknown',
                                                ! empty($similarMovie['release_date'])
                                                    ? (int) substr($similarMovie['release_date'], 0, 4)
                                                    : null
                                            ),
                                        ],
                                    ],
                                ],
                                '_meta' => [
                                    'exists_locally' => false,
                                    'tmdb_id' => $tmdbId,
                                    'source' => 'tmdb_similar',
                                ],
                            ];
                        }
                    }

                    return $transformed;
                } catch (\Throwable $e) {
                    \Illuminate\Support\Facades\Log::warning('Failed to fetch similar movies from TMDB', [
                        'movie_id' => $movie->id,
                        'tmdb_id' => $snapshot->tmdb_id,
                        'error' => $e->getMessage(),
                    ]);

                    return [];
                }
            }
        );
    }
}
