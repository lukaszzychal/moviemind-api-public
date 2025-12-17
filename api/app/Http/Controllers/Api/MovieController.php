<?php

namespace App\Http\Controllers\Api;

use App\Actions\QueueMovieGenerationAction;
use App\Enums\Locale;
use App\Helpers\SlugValidator;
use App\Http\Controllers\Controller;
use App\Http\Requests\SearchMovieRequest;
use App\Http\Resources\MovieResource;
use App\Http\Responses\MovieResponseFormatter;
use App\Models\Movie;
use App\Repositories\MovieRepository;
use App\Services\EntityVerificationServiceInterface;
use App\Services\HateoasService;
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

    private function cacheKey(string $slug, ?int $descriptionId = null): string
    {
        $suffix = $descriptionId !== null ? 'desc:'.$descriptionId : 'desc:default';

        return 'movie:'.$slug.':'.$suffix;
    }

    private function normalizeDescriptionId(mixed $descriptionId): null|int|false
    {
        if ($descriptionId === null || $descriptionId === '') {
            return null;
        }

        if (filter_var($descriptionId, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]) === false) {
            return false;
        }

        return (int) $descriptionId;
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
}
