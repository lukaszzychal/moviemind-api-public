<?php

namespace App\Http\Controllers\Api;

use App\Actions\GetRelatedMoviesAction;
use App\Actions\QueueMovieGenerationAction;
use App\Enums\Locale as LocaleEnum;
use App\Helpers\SlugValidator;
use App\Http\Controllers\Controller;
use App\Http\Requests\BulkMoviesRequest;
use App\Http\Requests\CompareMoviesRequest;
use App\Http\Requests\ReportMovieRequest;
use App\Http\Requests\SearchMovieRequest;
use App\Http\Resources\MovieResource;
use App\Http\Responses\MovieResponseFormatter;
use App\Models\Movie;
use App\Models\MovieReport;
use App\Repositories\MovieRepository;
use App\Services\BulkRetrievalService;
use App\Services\EntityVerificationServiceInterface;
use App\Services\HateoasService;
use App\Services\MovieCollectionService;
use App\Services\MovieComparisonService;
use App\Services\MovieReportService;
use App\Services\MovieRetrievalService;
use App\Services\MovieSearchService;
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
        private readonly MovieResponseFormatter $responseFormatter,
        private readonly MovieReportService $movieReportService,
        private readonly MovieCollectionService $movieCollectionService,
        private readonly MovieComparisonService $movieComparisonService,
        private readonly BulkRetrievalService $bulkRetrievalService,
        private readonly GetRelatedMoviesAction $getRelatedMoviesAction
    ) {}

    public function index(Request $request): JsonResponse
    {
        // Check if slugs parameter is provided (bulk retrieve)
        $slugsParam = $request->query('slugs');
        if ($slugsParam !== null) {
            return $this->handleBulkRetrieve($request);
        }

        // Normal search
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
     * Handle bulk retrieve via GET /movies?slugs=...
     */
    /**
     * Handle bulk retrieve via GET /movies?slugs=...
     */
    private function handleBulkRetrieve(Request $request): JsonResponse
    {
        // Use BulkMoviesRequest logic for validation manually since we are in a private method called by index
        // Ideally index should just assume search and we separate bulk to another endpoint,
        // but for backward compatibility we keep logic here but use the Service.

        $slugsParam = $request->query('slugs');
        if ($slugsParam === null || $slugsParam === '') {
            return response()->json(['errors' => ['slugs' => ['The slugs field is required and cannot be empty.']]], 422);
        }

        $slugs = is_array($slugsParam) ? $slugsParam : explode(',', (string) $slugsParam);
        $slugs = array_map('trim', $slugs);
        $slugs = array_filter($slugs, fn ($slug) => $slug !== '');

        if (empty($slugs)) {
            return response()->json(['errors' => ['slugs' => ['The slugs field is required and cannot be empty.']]], 422);
        }

        if (count($slugs) > 50) {
            return response()->json(['errors' => ['slugs' => ['The slugs field must not have more than 50 items.']]], 422);
        }

        foreach ($slugs as $slug) {
            if (! preg_match('/^[a-z0-9-]+$/i', $slug) || strlen($slug) > 255) {
                return response()->json(['errors' => ['slugs' => ['Each slug must match the pattern: /^[a-z0-9-]+$/i and be max 255 characters.']]], 422);
            }
        }

        // Parse include
        $includeParam = $request->query('include');
        $include = is_array($includeParam) ? $includeParam : ($includeParam !== null ? explode(',', (string) $includeParam) : []);
        $include = array_map('trim', $include);
        $allowedInclude = ['descriptions', 'people', 'genres'];
        foreach ($include as $item) {
            if (! in_array($item, $allowedInclude, true)) {
                return response()->json(['errors' => ['include' => ['The include field must contain only: '.implode(', ', $allowedInclude).'.']]], 422);
            }
        }

        // Use Service
        $result = $this->bulkRetrievalService->retrieve(
            $this->movieRepository,
            $slugs,
            $include,
            function (Movie $movie) {
                return MovieResource::make($movie)->additional([
                    '_links' => $this->hateoas->movieLinks($movie),
                ])->resolve();
            }
        );

        return response()->json($result, 200);
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
                            locale: LocaleEnum::EN_US->value,
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

        // Extract and validate locale parameter (null if not provided, 'en-US' if invalid)
        $localeParam = $request->query('locale');
        $locale = $localeParam !== null ? $this->normalizeLocale($localeParam) : null;

        // Handle disambiguation selection (special case - user selects specific slug from disambiguation)
        // Note: Disambiguation now uses slugs instead of tmdb_id
        $selectedSlug = $request->query('slug');
        if ($selectedSlug !== null) {
            return $this->handleDisambiguationSelection($slug, (string) $selectedSlug);
        }

        $result = $this->movieRetrievalService->retrieveMovie($slug, $descriptionId);

        $response = $this->responseFormatter->formatFromResult($result, $slug, $locale);

        // Cache successful responses (but not disambiguation - they should be fresh)
        if ($result->isFound() && ! $result->isCached() && ! $result->isDisambiguation()) {
            $cacheKey = $this->cacheKey($slug, $descriptionId, $locale);
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
                locale: LocaleEnum::EN_US->value,
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
    private function cacheKey(string $slug, ?string $descriptionId = null, ?string $locale = null): string
    {
        $suffix = $descriptionId !== null ? 'desc:'.$descriptionId : 'desc:default';
        $localeSuffix = $locale !== null ? ':locale:'.$locale : '';

        return 'movie:'.$slug.':'.$suffix.$localeSuffix;
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
     * Normalize and validate locale parameter.
     * Returns default 'en-US' if not provided or invalid.
     *
     * @return string Valid locale code or 'en-US' as default
     */
    private function normalizeLocale(mixed $locale): string
    {
        if ($locale === null || $locale === '') {
            return 'en-US';
        }

        $locale = (string) $locale;

        // Validate locale format using LocaleEnum
        if (LocaleEnum::isValid($locale)) {
            return $locale;
        }

        // Invalid locale - fallback to en-US
        return 'en-US';
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

        $result = $this->getRelatedMoviesAction->handle($movie, $request);

        return response()->json([
            'movie' => [
                'id' => $movie->id,
                'slug' => $movie->slug,
                'title' => $movie->title,
            ],
            'related_movies' => array_merge($result['collection'], $result['similar']),
            'count' => count($result['collection']) + count($result['similar']),
            'filters' => [
                'type' => $result['type_filter'],
                'collection_count' => count($result['collection']),
                'similar_count' => count($result['similar']),
            ],
            '_links' => [
                'self' => ['href' => url("/api/v1/movies/{$slug}/related")],
                'movie' => ['href' => url("/api/v1/movies/{$slug}")],
                'collection' => ['href' => url("/api/v1/movies/{$slug}/related?type=collection")],
                'similar' => ['href' => url("/api/v1/movies/{$slug}/related?type=similar")],
            ],
        ]);
    }

    /**
     * Get collection for a movie (all movies in the same TMDb collection).
     *
     * @return JsonResponse Collection data with movies or 404 if not found
     */
    public function collection(string $slug): JsonResponse
    {
        $collectionData = $this->movieCollectionService->getCollectionByMovieSlug($slug);

        if (! $collectionData) {
            return $this->responseFormatter->formatNotFound();
        }

        // Format response with HATEOAS links
        $response = response()->json([
            'collection' => $collectionData['collection'],
            'movies' => $collectionData['movies'],
            '_links' => [
                'self' => url('/api/v1/movies/'.$slug.'/collection'),
            ],
        ], 200);

        return $response;
    }

    /**
     * Report an issue with a movie or its description.
     */
    public function report(ReportMovieRequest $request, string $slug): JsonResponse
    {
        $movie = $this->movieRepository->findBySlugWithRelations($slug);

        if ($movie === null) {
            return $this->responseFormatter->formatNotFound();
        }

        $validated = $request->validated();

        // Create report
        $report = MovieReport::create([
            'movie_id' => $movie->id,
            'description_id' => $validated['description_id'] ?? null,
            'type' => $validated['type'],
            'message' => $validated['message'],
            'suggested_fix' => $validated['suggested_fix'] ?? null,
            'status' => \App\Enums\ReportStatus::PENDING,
            'priority_score' => 0.0, // Will be calculated below
        ]);

        // Calculate and update priority score
        $priorityScore = $this->movieReportService->calculatePriorityScore($report);
        $report->update(['priority_score' => $priorityScore]);

        // Also update priority scores for other pending reports of same type
        MovieReport::where('movie_id', $movie->id)
            ->where('type', $report->type)
            ->where('status', \App\Enums\ReportStatus::PENDING)
            ->where('id', '!=', $report->id)
            ->update(['priority_score' => $priorityScore]);

        return response()->json([
            'data' => [
                'id' => $report->id,
                'movie_id' => $report->movie_id,
                'description_id' => $report->description_id,
                'type' => $report->type->value,
                'message' => $report->message,
                'suggested_fix' => $report->suggested_fix,
                'status' => $report->status->value,
                'priority_score' => (float) $report->priority_score,
                'created_at' => $report->created_at->toIso8601String(),
            ],
        ], 201);
    }

    /**
     * Bulk retrieve multiple movies by slugs.
     */
    public function bulk(BulkMoviesRequest $request): JsonResponse
    {
        $slugs = $request->getSlugs();
        $include = $request->getInclude();

        $result = $this->bulkRetrievalService->retrieve(
            $this->movieRepository,
            $slugs,
            $include,
            function (Movie $movie) {
                return MovieResource::make($movie)->additional([
                    '_links' => $this->hateoas->movieLinks($movie),
                ])->resolve();
            }
        );

        return response()->json($result, 200);
    }

    /**
     * Compare two movies.
     */
    public function compare(CompareMoviesRequest $request): JsonResponse
    {
        $slug1 = $request->validated()['slug1'];
        $slug2 = $request->validated()['slug2'];

        try {
            $comparison = $this->movieComparisonService->compare($slug1, $slug2);

            return response()->json($comparison);
        } catch (\InvalidArgumentException $e) {
            return $this->responseFormatter->formatNotFound();
        }
    }
}
